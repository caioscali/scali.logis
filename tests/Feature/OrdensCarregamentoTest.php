<?php

namespace Tests\Feature;

use App\Domain\Finance\Models\ContaBancaria;
use App\Domain\Finance\Models\TransportadoraContaBancaria;
use App\Domain\LoadingOrders\Contracts\GoogleDriveClient;
use App\Domain\LoadingOrders\Contracts\GoogleSheetsClient;
use App\Domain\LoadingOrders\Enums\SituacaoOrdem;
use App\Domain\LoadingOrders\Exceptions\GeracaoPdfOrdemException;
use App\Domain\LoadingOrders\Models\OrdemCarregamento;
use App\Domain\LoadingOrders\Services\CadastroOrdensCarregamento;
use App\Domain\LoadingOrders\Services\GeradorPdfOrdem;
use App\Domain\Organizations\Enums\PerfilAcesso;
use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Domain\Registry\Enums\FuncaoPessoa;
use App\Domain\Registry\Models\ConjuntoTransporte;
use App\Domain\Registry\Models\Pessoa;
use App\Domain\Registry\Models\PessoaFuncao;
use App\Domain\Registry\Models\TransportadoraPessoa;
use App\Domain\Registry\Models\TransportadoraVeiculo;
use App\Domain\Registry\Models\Veiculo;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class OrdensCarregamentoTest extends TestCase
{
    use DatabaseMigrations;

    public function test_valid_order_calculates_balance_and_preserves_snapshot(): void
    {
        [$user, $transportadora] = $this->contexto();
        [$cliente, $conjunto] = $this->cadastrosOperacionais($transportadora);

        $response = $this->actingAs($user)->post(route('ordens-carregamento.store'), $this->dados($cliente, $conjunto));

        $response->assertRedirect();
        $ordem = OrdemCarregamento::query()->firstOrFail();
        $this->assertSame('750.00', $ordem->valor_saldo);
        $this->assertSame('Motorista Original', $ordem->snapshot_motorista_nome);
        $this->assertSame('Cliente Original', $ordem->snapshot_cliente_nome);
    }

    public function test_draft_can_be_edited_and_emitted_but_not_edited_afterwards(): void
    {
        [$user, $transportadora] = $this->contexto();
        [$cliente, $conjunto] = $this->cadastrosOperacionais($transportadora);
        $ordem = OrdemCarregamento::query()->create(['numero' => 1, 'transportadora_id' => $transportadora->id, 'filial_id' => Filial::query()->where('transportadora_id', $transportadora->id)->firstOrFail()->id, 'created_by' => $user->id]);

        $this->actingAs($user)->put(route('ordens-carregamento.update', $ordem), $this->dados($cliente, $conjunto))->assertRedirect();
        $this->actingAs($user)->post(route('ordens-carregamento.emit', $ordem))->assertRedirect();
        $this->assertSame(SituacaoOrdem::Emitida, $ordem->fresh()->situacao);
        $this->actingAs($user)->put(route('ordens-carregamento.update', $ordem), ['produto' => 'Alteração'])->assertSessionHasErrors('ordem');
    }

    public function test_cancel_is_recorded_and_physical_delete_is_blocked(): void
    {
        [$user, $transportadora] = $this->contexto();
        $ordem = OrdemCarregamento::factory()->create(['transportadora_id' => $transportadora->id, 'filial_id' => Filial::query()->where('transportadora_id', $transportadora->id)->firstOrFail()->id, 'created_by' => $user->id, 'situacao' => SituacaoOrdem::Emitida]);

        $this->actingAs($user)->post(route('ordens-carregamento.cancel', $ordem))->assertRedirect();
        $this->assertSame(SituacaoOrdem::Cancelada, $ordem->fresh()->situacao);
        $this->expectException(ValidationException::class);
        $ordem->delete();
    }

    public function test_customer_and_transport_set_must_belong_to_active_context(): void
    {
        [$user, $transportadora] = $this->contexto();
        [$outra] = $this->criarVinculo(User::factory()->create(), 'outra');
        [$cliente, $conjunto] = $this->cadastrosOperacionais($outra);

        $this->actingAs($user)->post(route('ordens-carregamento.store'), $this->dados($cliente, $conjunto))->assertSessionHasErrors('cliente_pessoa_id');
    }

    public function test_ineligible_set_is_rejected(): void
    {
        [$user, $transportadora] = $this->contexto();
        [$cliente, $conjunto] = $this->cadastrosOperacionais($transportadora, false);

        $this->actingAs($user)->post(route('ordens-carregamento.store'), $this->dados($cliente, $conjunto))->assertSessionHasErrors('conjunto_transporte_id');
    }

    public function test_context_switch_hides_orders_from_previous_branch(): void
    {
        $user = User::factory()->create();
        [$primeira, $filialA] = $this->criarVinculo($user, 'A');
        [$segunda, $filialB] = $this->criarVinculo($user, 'B');
        $ordem = OrdemCarregamento::factory()->create(['transportadora_id' => $primeira->id, 'filial_id' => $filialA->id, 'created_by' => $user->id]);
        $this->selecionarContexto($user, $segunda, $filialB);

        $this->actingAs($user)->get(route('ordens-carregamento.show', $ordem))->assertForbidden();
    }

    public function test_consulta_cannot_modify_and_operator_cannot_cancel(): void
    {
        [$consulta] = $this->contexto(PerfilAcesso::Consulta);
        $this->actingAs($consulta)->post(route('ordens-carregamento.store'), [])->assertForbidden();
        [$operador, $transportadora] = $this->contexto(PerfilAcesso::Operador);
        $ordem = OrdemCarregamento::factory()->create(['transportadora_id' => $transportadora->id, 'filial_id' => Filial::query()->where('transportadora_id', $transportadora->id)->firstOrFail()->id, 'created_by' => $operador->id, 'situacao' => SituacaoOrdem::Emitida]);

        $this->actingAs($operador)->post(route('ordens-carregamento.cancel', $ordem))->assertForbidden();
    }

    public function test_export_data_separates_operational_and_financial_values(): void
    {
        $ordem = OrdemCarregamento::factory()->make(['valor_frete' => '100.00', 'valor_adiantamento' => '25.00', 'valor_saldo' => '75.00']);
        $dados = app(CadastroOrdensCarregamento::class)->dadosExportaveis($ordem);

        $this->assertArrayHasKey('operacionais', $dados);
        $this->assertArrayHasKey('financeiros', $dados);
        $this->assertArrayNotHasKey('valor_frete', $dados['operacionais']);
    }

    public function test_emitted_order_generates_private_pdf_without_financial_payload(): void
    {
        [$user, $transportadora] = $this->contexto();
        $filial = Filial::query()->where('transportadora_id', $transportadora->id)->firstOrFail();
        $ordem = OrdemCarregamento::factory()->create(['transportadora_id' => $transportadora->id, 'filial_id' => $filial->id, 'created_by' => $user->id, 'situacao' => SituacaoOrdem::Emitida, 'valor_frete' => '1000.00', 'valor_adiantamento' => '250.00', 'valor_saldo' => '750.00']);
        Storage::fake('local');
        config(['google.sheets.template_id' => 'template-real', 'google.sheets.folder_id' => 'folder-real']);
        $sheets = Mockery::mock(GoogleSheetsClient::class);
        $drive = Mockery::mock(GoogleDriveClient::class);
        $sheets->shouldReceive('copiarModelo')->once()->andReturn('temporary-id');
        $sheets->shouldReceive('preencher')->once()->withArgs(function (string $id, array $values): bool {
            $payload = json_encode($values);

            return $id === 'temporary-id' && ! str_contains($payload, 'valor_frete') && ! str_contains($payload, '1000.00') && ! str_contains($payload, '750.00');
        });
        $drive->shouldReceive('exportarPdf')->once()->andReturn('%PDF-1.7 documento operacional');
        $sheets->shouldReceive('remover')->once()->with('temporary-id');
        $this->app->instance(GoogleSheetsClient::class, $sheets);
        $this->app->instance(GoogleDriveClient::class, $drive);

        $response = $this->actingAs($user)->post(route('ordens-carregamento.pdf.generate', $ordem));

        $response->assertRedirect()->assertSessionHas('success');
        $ordem = $ordem->fresh();
        $this->assertNotNull($ordem->pdf_caminho);
        Storage::disk('local')->assertExists($ordem->pdf_caminho);
    }

    public function test_pdf_generation_is_blocked_for_draft_and_consultation_users(): void
    {
        [$admin, $transportadora] = $this->contexto();
        $filial = Filial::query()->where('transportadora_id', $transportadora->id)->firstOrFail();
        $draft = OrdemCarregamento::factory()->create(['transportadora_id' => $transportadora->id, 'filial_id' => $filial->id, 'created_by' => $admin->id, 'situacao' => SituacaoOrdem::Rascunho]);
        $this->actingAs($admin)->post(route('ordens-carregamento.pdf.generate', $draft))->assertForbidden();
        [$consulta] = $this->contexto(PerfilAcesso::Consulta);
        $this->actingAs($consulta)->post(route('ordens-carregamento.pdf.generate', $draft))->assertForbidden();
    }

    public function test_pdf_download_is_authorized_and_does_not_accept_a_free_path(): void
    {
        [$user, $transportadora] = $this->contexto();
        $filial = Filial::query()->where('transportadora_id', $transportadora->id)->firstOrFail();
        Storage::fake('local');
        $path = 'ordens-carregamento/1/1/1/ORDEM-CARREGAMENTO-1.pdf';
        Storage::disk('local')->put($path, '%PDF-1.7');
        $ordem = OrdemCarregamento::factory()->create(['transportadora_id' => $transportadora->id, 'filial_id' => $filial->id, 'created_by' => $user->id, 'situacao' => SituacaoOrdem::Cancelada, 'pdf_caminho' => $path, 'pdf_gerado_por' => $user->id, 'pdf_gerado_em' => now()]);

        $this->actingAs($user)->get(route('ordens-carregamento.pdf.download', $ordem).'?path=../../.env')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame($path, $ordem->fresh()->pdf_caminho);
    }

    public function test_invalid_pdf_fails_without_persisting_document_and_cleanup_failure_is_non_fatal(): void
    {
        [$user, $transportadora] = $this->contexto();
        $filial = Filial::query()->where('transportadora_id', $transportadora->id)->firstOrFail();
        $ordem = OrdemCarregamento::factory()->create(['transportadora_id' => $transportadora->id, 'filial_id' => $filial->id, 'created_by' => $user->id, 'situacao' => SituacaoOrdem::Emitida]);
        Storage::fake('local');
        config(['google.sheets.template_id' => 'template-real']);
        $sheets = Mockery::mock(GoogleSheetsClient::class);
        $drive = Mockery::mock(GoogleDriveClient::class);
        $sheets->shouldReceive('copiarModelo')->once()->andReturn('temporary-id');
        $sheets->shouldReceive('preencher')->once();
        $drive->shouldReceive('exportarPdf')->once()->andReturn('not-a-pdf');
        $sheets->shouldReceive('remover')->once()->andThrow(new \RuntimeException('temporary cleanup unavailable'));
        $this->app->instance(GoogleSheetsClient::class, $sheets);
        $this->app->instance(GoogleDriveClient::class, $drive);

        $this->expectException(GeracaoPdfOrdemException::class);
        try {
            app(GeradorPdfOrdem::class)->gerar($ordem, $user);
        } finally {
            $this->assertNull($ordem->fresh()->pdf_caminho);
        }
    }

    public function test_missing_google_credentials_returns_safe_configuration_error(): void
    {
        [$user, $transportadora] = $this->contexto();
        $filial = Filial::query()->where('transportadora_id', $transportadora->id)->firstOrFail();
        $ordem = OrdemCarregamento::factory()->create(['transportadora_id' => $transportadora->id, 'filial_id' => $filial->id, 'created_by' => $user->id, 'situacao' => SituacaoOrdem::Emitida]);
        config(['google.sheets.template_id' => 'template-real', 'google.credentials' => null]);

        $this->actingAs($user)->post(route('ordens-carregamento.pdf.generate', $ordem))
            ->assertRedirect()
            ->assertSessionHasErrors(['pdf' => 'A integração Google não está configurada.']);
        $this->assertNull($ordem->fresh()->pdf_caminho);
    }

    public function test_existing_pdf_is_preserved_without_a_second_google_generation(): void
    {
        [$user, $transportadora] = $this->contexto();
        $filial = Filial::query()->where('transportadora_id', $transportadora->id)->firstOrFail();
        Storage::fake('local');
        $path = "ordens-carregamento/{$transportadora->id}/{$filial->id}/8/ORDEM-CARREGAMENTO-8.pdf";
        Storage::disk('local')->put($path, '%PDF-original');
        $ordem = OrdemCarregamento::factory()->create(['transportadora_id' => $transportadora->id, 'filial_id' => $filial->id, 'created_by' => $user->id, 'situacao' => SituacaoOrdem::Emitida, 'pdf_caminho' => $path, 'pdf_gerado_por' => $user->id, 'pdf_gerado_em' => now()]);
        $sheets = Mockery::mock(GoogleSheetsClient::class);
        $drive = Mockery::mock(GoogleDriveClient::class);
        $sheets->shouldReceive('copiarModelo')->never();
        $drive->shouldReceive('exportarPdf')->never();
        $this->app->instance(GoogleSheetsClient::class, $sheets);
        $this->app->instance(GoogleDriveClient::class, $drive);
        config(['google.sheets.template_id' => 'template-real']);

        $this->actingAs($user)->post(route('ordens-carregamento.pdf.generate', $ordem))->assertRedirect();
        $this->assertSame('%PDF-original', Storage::disk('local')->get($path));
    }

    public function test_other_transportadora_cannot_download_an_existing_pdf(): void
    {
        [$owner, $transportadora] = $this->contexto();
        $filial = Filial::query()->where('transportadora_id', $transportadora->id)->firstOrFail();
        $path = "ordens-carregamento/{$transportadora->id}/{$filial->id}/9/ORDEM-CARREGAMENTO-9.pdf";
        Storage::fake('local');
        Storage::disk('local')->put($path, '%PDF-1.7');
        $ordem = OrdemCarregamento::factory()->create(['transportadora_id' => $transportadora->id, 'filial_id' => $filial->id, 'created_by' => $owner->id, 'situacao' => SituacaoOrdem::Cancelada, 'pdf_caminho' => $path]);
        [$other] = $this->contexto();

        $this->actingAs($other)->get(route('ordens-carregamento.pdf.download', $ordem))->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function dados(Pessoa $cliente, ConjuntoTransporte $conjunto): array
    {
        return ['cliente_pessoa_id' => $cliente->id, 'conjunto_transporte_id' => $conjunto->id, 'produto' => 'Soja', 'quantidade' => '10.000', 'unidade_medida' => 'tonelada', 'origem_cidade' => 'Ribeirão Preto', 'origem_uf' => 'SP', 'local_carregamento' => 'Fazenda A', 'data_carregamento' => '2026-11-17', 'destino_cidade' => 'Santos', 'destino_uf' => 'SP', 'local_descarga' => 'Terminal B', 'data_previsao_entrega' => '2026-11-18', 'valor_frete' => '1000.00', 'valor_adiantamento' => '250.00'];
    }

    /** @return array{0: Pessoa, 1: ConjuntoTransporte} */
    private function cadastrosOperacionais(Transportadora $transportadora, bool $ativo = true): array
    {
        $cliente = $this->pessoaComFuncao($transportadora, 'Cliente Original', FuncaoPessoa::Cliente);
        $motorista = $this->pessoaComFuncao($transportadora, 'Motorista Original', FuncaoPessoa::Motorista);
        $transportador = $this->pessoaComFuncao($transportadora, 'Transportador Original', FuncaoPessoa::Transportador);
        $veiculo = Veiculo::factory()->create();
        TransportadoraVeiculo::factory()->for($transportadora)->for($veiculo)->create(['ativo' => $ativo]);
        $conta = ContaBancaria::factory()->for($motorista)->create();
        TransportadoraContaBancaria::factory()->for($transportadora)->for($conta)->create(['ativo' => $ativo]);
        $conjunto = ConjuntoTransporte::factory()->create(['transportadora_id' => $transportadora->id, 'motorista_pessoa_id' => $motorista->id, 'transportador_pessoa_id' => $transportador->id, 'veiculo_id' => $veiculo->id, 'conta_bancaria_id' => $conta->id, 'ativo' => $ativo]);

        return [$cliente, $conjunto];
    }

    private function pessoaComFuncao(Transportadora $transportadora, string $nome, FuncaoPessoa $funcao): Pessoa
    {
        $pessoa = Pessoa::factory()->create(['nome' => $nome]);
        $vinculo = TransportadoraPessoa::factory()->for($transportadora)->for($pessoa)->create(['ativo' => true]);
        PessoaFuncao::factory()->for($vinculo, 'transportadoraPessoa')->create(['funcao' => $funcao]);

        return $pessoa;
    }

    /** @return array{0: User, 1: Transportadora} */
    private function contexto(PerfilAcesso $perfil = PerfilAcesso::Administrador): array
    {
        $user = User::factory()->create();
        [$transportadora, $filial] = $this->criarVinculo($user, uniqid(), $perfil);
        $this->selecionarContexto($user, $transportadora, $filial);

        return [$user, $transportadora];
    }

    /** @return array{0: Transportadora, 1: Filial} */
    private function criarVinculo(User $user, string $sufixo, PerfilAcesso $perfil = PerfilAcesso::Administrador): array
    {
        $transportadora = Transportadora::factory()->create(['nome_fantasia' => 'Transportadora '.$sufixo]);
        $filial = Filial::factory()->for($transportadora)->create(['ativo' => true]);
        $vinculo = UsuarioTransportadora::factory()->for($user, 'usuario')->for($transportadora)->create(['perfil' => $perfil, 'ativo' => true]);
        $vinculo->usuarioFiliais()->create(['filial_id' => $filial->id]);

        return [$transportadora, $filial];
    }

    private function selecionarContexto(User $user, Transportadora $transportadora, Filial $filial): void
    {
        $this->actingAs($user)->post(route('contexto.selecionar.store'), ['transportadora_id' => $transportadora->id, 'filial_id' => $filial->id]);
    }
}

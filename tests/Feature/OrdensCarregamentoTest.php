<?php

namespace Tests\Feature;

use App\Domain\Finance\Models\ContaBancaria;
use App\Domain\Finance\Models\TransportadoraContaBancaria;
use App\Domain\LoadingOrders\Enums\SituacaoOrdem;
use App\Domain\LoadingOrders\Models\OrdemCarregamento;
use App\Domain\LoadingOrders\Services\CadastroOrdensCarregamento;
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
use Illuminate\Validation\ValidationException;
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

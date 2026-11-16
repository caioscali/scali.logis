<?php

namespace Tests\Feature;

use App\Domain\Finance\Enums\TipoChavePix;
use App\Domain\Finance\Models\ContaBancaria;
use App\Domain\Finance\Models\TransportadoraContaBancaria;
use App\Domain\Finance\Services\CadastroContasBancarias;
use App\Domain\Organizations\Enums\PerfilAcesso;
use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Domain\Registry\Models\Pessoa;
use App\Domain\Registry\Models\TransportadoraPessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContasBancariasTest extends TestCase
{
    use DatabaseMigrations;

    public function test_bank_account_is_created_and_sensitive_fields_are_encrypted_at_rest(): void
    {
        [$user, $transportadora, $pessoa] = $this->contexto();
        Log::spy();

        $this->actingAs($user)->post(route('contas-bancarias.store'), [
            'pessoa_id' => $pessoa->id,
            'banco_codigo' => '001',
            'banco_nome' => 'Banco do Brasil',
            'agencia' => '0001',
            'numero_conta' => '123456',
            'digito_conta' => '7',
            'tipo_conta' => 'corrente',
        ])->assertRedirect();

        $conta = ContaBancaria::query()->firstOrFail();
        $rawAgencia = DB::table('contas_bancarias')->whereKey($conta->id)->value('agencia');
        $this->assertNotSame('0001', $rawAgencia);
        $this->assertSame('0001', $conta->agencia);
        Log::shouldNotHaveReceived('info');
        $this->assertDatabaseHas('transportadoras_contas_bancarias', ['transportadora_id' => $transportadora->id, 'conta_bancaria_id' => $conta->id, 'ativo' => true]);
    }

    public function test_pix_only_and_bank_plus_pix_accounts_are_supported(): void
    {
        [$user, , $pessoa] = $this->contexto();

        $this->actingAs($user)->post(route('contas-bancarias.store'), [
            'pessoa_id' => $pessoa->id,
            'tipo_chave_pix' => 'email',
            'chave_pix' => 'PIX@EXEMPLO.COM',
        ])->assertRedirect();
        $this->actingAs($user)->post(route('contas-bancarias.store'), [
            'pessoa_id' => $pessoa->id,
            'banco_codigo' => '104',
            'banco_nome' => 'Caixa',
            'agencia' => '0012',
            'numero_conta' => '98765',
            'tipo_chave_pix' => 'aleatoria',
            'chave_pix' => 'abc-123-chave',
        ])->assertRedirect();

        $this->assertSame(2, ContaBancaria::query()->count());
        $this->assertSame(TipoChavePix::Email, ContaBancaria::query()->firstOrFail()->tipo_chave_pix);
    }

    public function test_empty_or_invalid_pix_payment_data_is_rejected(): void
    {
        [$user, , $pessoa] = $this->contexto();

        $this->actingAs($user)->post(route('contas-bancarias.store'), ['pessoa_id' => $pessoa->id])->assertSessionHasErrors('banco_codigo');
        $this->actingAs($user)->post(route('contas-bancarias.store'), ['pessoa_id' => $pessoa->id, 'tipo_chave_pix' => 'email', 'chave_pix' => 'invalido'])->assertSessionHasErrors('chave_pix');
        $this->actingAs($user)->post(route('contas-bancarias.store'), ['pessoa_id' => $pessoa->id, 'tipo_chave_pix' => 'telefone', 'chave_pix' => '123'])->assertSessionHasErrors('chave_pix');
    }

    public function test_person_can_have_multiple_distinct_accounts(): void
    {
        [$user, , $pessoa] = $this->contexto();
        $dados = ['pessoa_id' => $pessoa->id, 'banco_codigo' => '001', 'banco_nome' => 'Banco', 'agencia' => '0001', 'numero_conta' => '123', 'tipo_conta' => 'corrente'];

        $this->actingAs($user)->post(route('contas-bancarias.store'), $dados)->assertRedirect();
        $this->actingAs($user)->post(route('contas-bancarias.store'), [...$dados, 'numero_conta' => '456'])->assertRedirect();
        $response = $this->actingAs($user)->post(route('contas-bancarias.store'), $dados);
        $response->assertSessionHasErrors('conta_bancaria_id');

        $this->assertSame(2, ContaBancaria::query()->count());
    }

    public function test_existing_account_can_be_adopted_by_two_transportadoras_without_duplication(): void
    {
        $user = User::factory()->create();
        [$primeira, $filialA] = $this->criarVinculo($user, 'A');
        [$segunda, $filialB] = $this->criarVinculo($user, 'B');
        $pessoa = Pessoa::factory()->create();
        $this->vincularPessoa($pessoa, $primeira);
        $this->vincularPessoa($pessoa, $segunda);
        $this->selecionarContexto($user, $primeira, $filialA);

        $this->actingAs($user)->post(route('contas-bancarias.store'), ['pessoa_id' => $pessoa->id, 'tipo_chave_pix' => 'aleatoria', 'chave_pix' => 'chave-global'])->assertRedirect();
        $conta = ContaBancaria::query()->firstOrFail();
        $this->selecionarContexto($user, $segunda, $filialB);
        $this->actingAs($user)->post(route('contas-bancarias.store'), ['pessoa_id' => $pessoa->id, 'conta_bancaria_id' => $conta->id])->assertRedirect();

        $this->assertSame(1, ContaBancaria::query()->count());
        $this->assertSame(2, TransportadoraContaBancaria::query()->count());
    }

    public function test_titular_must_be_active_in_the_current_transportadora(): void
    {
        [$user, $transportadora] = $this->contexto();
        $outraPessoa = Pessoa::factory()->create();

        $this->actingAs($user)->post(route('contas-bancarias.store'), ['pessoa_id' => $outraPessoa->id, 'tipo_chave_pix' => 'aleatoria', 'chave_pix' => 'nao-autorizada'])->assertSessionHasErrors('pessoa_id');
        $this->assertDatabaseCount('contas_bancarias', 0);
        $this->assertNotNull($transportadora);
    }

    public function test_consulta_user_cannot_create_or_deactivate_accounts(): void
    {
        [$user, $transportadora, $pessoa] = $this->contexto(PerfilAcesso::Consulta);
        $conta = ContaBancaria::factory()->for($pessoa)->create();
        $vinculo = TransportadoraContaBancaria::factory()->for($transportadora)->for($conta)->create();

        $this->actingAs($user)->post(route('contas-bancarias.store'), ['pessoa_id' => $pessoa->id, 'tipo_chave_pix' => 'aleatoria', 'chave_pix' => 'sem-permissao'])->assertForbidden();
        $this->actingAs($user)->put(route('contas-bancarias.update', $vinculo), ['ativo' => false])->assertForbidden();
    }

    public function test_manipulated_account_identifier_and_context_switch_are_blocked(): void
    {
        $user = User::factory()->create();
        [$primeira, $filialA] = $this->criarVinculo($user, 'A');
        [$segunda, $filialB] = $this->criarVinculo($user, 'B');
        $pessoa = Pessoa::factory()->create();
        $this->vincularPessoa($pessoa, $segunda);
        $conta = ContaBancaria::factory()->for($pessoa)->create();
        $vinculo = TransportadoraContaBancaria::factory()->for($segunda)->for($conta)->create();
        $this->selecionarContexto($user, $primeira, $filialA);

        $this->actingAs($user)->put(route('contas-bancarias.update', $vinculo), ['ativo' => false])->assertForbidden();
        $this->actingAs($user)->get(route('contas-bancarias.show', $conta))->assertForbidden();
        $this->selecionarContexto($user, $segunda, $filialB);
        $this->actingAs($user)->put(route('contas-bancarias.update', $vinculo), ['ativo' => false])->assertRedirect();
    }

    public function test_masked_data_is_available_but_full_data_requires_administrator(): void
    {
        [$admin, $transportadora, $pessoa] = $this->contexto(PerfilAcesso::Administrador);
        $conta = ContaBancaria::factory()->for($pessoa)->create(['agencia' => '0001', 'numero_conta' => '123456', 'chave_pix' => 'pix@teste.com']);
        TransportadoraContaBancaria::factory()->for($transportadora)->for($conta)->create();

        $this->actingAs($admin)->get(route('contas-bancarias.index'))->assertInertia(fn ($page) => $page->where('contas.data.0.identificacao', fn (string $identificacao): bool => ! str_contains($identificacao, '123456')));
        $this->actingAs($admin)->get(route('contas-bancarias.show', $conta))->assertInertia(fn ($page) => $page->where('conta.dados_completos.numero_conta', '123456'));

        $operador = User::factory()->create();
        $vinculoUsuario = UsuarioTransportadora::factory()->for($operador, 'usuario')->for($transportadora)->create(['perfil' => PerfilAcesso::Operador]);
        $vinculoUsuario->usuarioFiliais()->create(['filial_id' => $this->filialDaTransportadora($transportadora)->id]);
        $this->selecionarContexto($operador, $transportadora, $this->filialDaTransportadora($transportadora));
        $this->actingAs($operador)->get(route('contas-bancarias.show', $conta))->assertInertia(fn ($page) => $page->where('conta.dados_completos', null));
    }

    public function test_inactive_link_is_not_returned_by_future_active_accounts_query(): void
    {
        [$user, $transportadora, $pessoa] = $this->contexto();
        $conta = ContaBancaria::factory()->for($pessoa)->create();
        $vinculo = TransportadoraContaBancaria::factory()->for($transportadora)->for($conta)->create(['ativo' => false]);

        $this->assertCount(0, app(CadastroContasBancarias::class)->contasAtivasDaPessoa($this->app['request'], $user, $pessoa));
        $this->assertFalse($vinculo->ativo);
    }

    public function test_account_with_dependencies_cannot_be_deleted(): void
    {
        [$user, $transportadora, $pessoa] = $this->contexto();
        $conta = ContaBancaria::factory()->for($pessoa)->create();
        TransportadoraContaBancaria::factory()->for($transportadora)->for($conta)->create();

        $this->expectException(ValidationException::class);
        $conta->delete();
    }

    /** @return array{0: User, 1: Transportadora, 2: Pessoa} */
    private function contexto(PerfilAcesso $perfil = PerfilAcesso::Administrador): array
    {
        $user = User::factory()->create();
        [$transportadora, $filial] = $this->criarVinculo($user, 'única', $perfil);
        $pessoa = Pessoa::factory()->create();
        $this->vincularPessoa($pessoa, $transportadora);
        $this->selecionarContexto($user, $transportadora, $filial);

        return [$user, $transportadora, $pessoa];
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

    private function vincularPessoa(Pessoa $pessoa, Transportadora $transportadora): void
    {
        TransportadoraPessoa::factory()->for($transportadora)->for($pessoa)->create(['ativo' => true]);
    }

    private function filialDaTransportadora(Transportadora $transportadora): Filial
    {
        return $transportadora->filiais()->firstOrFail();
    }

    private function selecionarContexto(User $user, Transportadora $transportadora, Filial $filial): void
    {
        $this->actingAs($user)->post(route('contexto.selecionar.store'), ['transportadora_id' => $transportadora->id, 'filial_id' => $filial->id]);
    }
}

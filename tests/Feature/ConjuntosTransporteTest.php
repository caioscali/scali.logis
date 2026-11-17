<?php

namespace Tests\Feature;

use App\Domain\Finance\Models\ContaBancaria;
use App\Domain\Finance\Models\TransportadoraContaBancaria;
use App\Domain\Organizations\Enums\PerfilAcesso;
use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Domain\Registry\Enums\FuncaoPessoa;
use App\Domain\Registry\Models\ConjuntoTransporte;
use App\Domain\Registry\Models\Implemento;
use App\Domain\Registry\Models\Pessoa;
use App\Domain\Registry\Models\PessoaFuncao;
use App\Domain\Registry\Models\TransportadoraImplemento;
use App\Domain\Registry\Models\TransportadoraPessoa;
use App\Domain\Registry\Models\TransportadoraVeiculo;
use App\Domain\Registry\Models\Veiculo;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ConjuntosTransporteTest extends TestCase
{
    use DatabaseMigrations;

    public function test_valid_set_with_different_driver_and_carrier_is_created(): void
    {
        [$user, $transportadora] = $this->contexto();
        $motorista = $this->pessoaComFuncao($transportadora, FuncaoPessoa::Motorista);
        $transportador = $this->pessoaComFuncao($transportadora, FuncaoPessoa::Transportador);
        $veiculo = $this->veiculo($transportadora);
        $conta = $this->conta($transportadora, $motorista);

        $this->actingAs($user)->post(route('conjuntos-transporte.store'), $this->dados($motorista, $transportador, $veiculo, $conta))->assertRedirect();

        $this->assertDatabaseHas('conjuntos_transporte', ['transportadora_id' => $transportadora->id, 'motorista_pessoa_id' => $motorista->id, 'transportador_pessoa_id' => $transportador->id]);
        $this->assertDatabaseCount('conjuntos_implementos', 0);
    }

    public function test_driver_and_carrier_can_be_the_same_person_with_both_functions(): void
    {
        [$user, $transportadora] = $this->contexto();
        $pessoa = $this->pessoaComFuncao($transportadora, FuncaoPessoa::Motorista, FuncaoPessoa::Transportador);
        $veiculo = $this->veiculo($transportadora);
        $conta = $this->conta($transportadora, $pessoa);

        $this->actingAs($user)->post(route('conjuntos-transporte.store'), $this->dados($pessoa, $pessoa, $veiculo, $conta))->assertRedirect();
        $this->assertDatabaseCount('conjuntos_transporte', 1);
    }

    public function test_cavalo_composition_requires_one_active_adopted_implement(): void
    {
        [$user, $transportadora] = $this->contexto();
        $motorista = $this->pessoaComFuncao($transportadora, FuncaoPessoa::Motorista);
        $transportador = $this->pessoaComFuncao($transportadora, FuncaoPessoa::Transportador);
        $veiculo = $this->veiculo($transportadora);
        $conta = $this->conta($transportadora, $motorista);
        $implemento = $this->implemento($transportadora);

        $dados = $this->dados($motorista, $transportador, $veiculo, $conta, [['implemento_id' => $implemento->id, 'posicao' => 1]]);
        $dados['tipo_composicao'] = 'cavalo_semirreboque';
        $this->actingAs($user)->post(route('conjuntos-transporte.store'), $dados)->assertRedirect();
        $this->assertDatabaseHas('conjuntos_implementos', ['implemento_id' => $implemento->id, 'posicao' => 1]);
    }

    public function test_people_without_required_function_or_inactive_equipment_are_rejected(): void
    {
        [$user, $transportadora] = $this->contexto();
        $motorista = $this->pessoaComFuncao($transportadora, FuncaoPessoa::Motorista);
        $transportador = Pessoa::factory()->create();
        $this->vincularPessoa($transportadora, $transportador);
        $veiculo = $this->veiculo($transportadora, false);
        $conta = $this->conta($transportadora, $motorista);

        $this->actingAs($user)->post(route('conjuntos-transporte.store'), $this->dados($motorista, $transportador, $veiculo, $conta))->assertSessionHasErrors('pessoa_id');
        $transportadorVinculo = TransportadoraPessoa::query()->where('transportadora_id', $transportadora->id)->where('pessoa_id', $transportador->id)->firstOrFail();
        PessoaFuncao::factory()->for($transportadorVinculo, 'transportadoraPessoa')->create(['funcao' => FuncaoPessoa::Transportador]);
        $this->actingAs($user)->post(route('conjuntos-transporte.store'), $this->dados($motorista, $transportador, $veiculo, $conta))->assertSessionHasErrors('veiculo_id');
    }

    public function test_duplicate_implementos_and_positions_are_rejected(): void
    {
        [$user, $transportadora] = $this->contexto();
        $motorista = $this->pessoaComFuncao($transportadora, FuncaoPessoa::Motorista);
        $transportador = $this->pessoaComFuncao($transportadora, FuncaoPessoa::Transportador);
        $veiculo = $this->veiculo($transportadora);
        $conta = $this->conta($transportadora, $motorista);
        $implemento = $this->implemento($transportadora);
        $dados = $this->dados($motorista, $transportador, $veiculo, $conta, [['implemento_id' => $implemento->id, 'posicao' => 1], ['implemento_id' => $implemento->id, 'posicao' => 1]]);
        $dados['tipo_composicao'] = 'bitrem';

        $this->actingAs($user)->post(route('conjuntos-transporte.store'), $dados)->assertSessionHasErrors('implementos');
    }

    public function test_payment_account_must_belong_to_driver_or_carrier_and_be_active(): void
    {
        [$user, $transportadora] = $this->contexto();
        $motorista = $this->pessoaComFuncao($transportadora, FuncaoPessoa::Motorista);
        $transportador = $this->pessoaComFuncao($transportadora, FuncaoPessoa::Transportador);
        $veiculo = $this->veiculo($transportadora);
        $terceiro = $this->pessoaComFuncao($transportadora, FuncaoPessoa::Proprietario);
        $conta = $this->conta($transportadora, $terceiro);

        $this->actingAs($user)->post(route('conjuntos-transporte.store'), $this->dados($motorista, $transportador, $veiculo, $conta))->assertSessionHasErrors('conta_bancaria_id');
        $contaMotorista = $this->conta($transportadora, $motorista, false);
        $this->actingAs($user)->post(route('conjuntos-transporte.store'), $this->dados($motorista, $transportador, $veiculo, $contaMotorista))->assertSessionHasErrors('conta_bancaria_id');
    }

    public function test_inactive_implemento_and_manipulated_transportadora_are_blocked(): void
    {
        $user = User::factory()->create();
        [$primeira, $filialA] = $this->criarVinculo($user, 'A');
        [$segunda] = $this->criarVinculo($user, 'B');
        $this->selecionarContexto($user, $primeira, $filialA);
        $motorista = $this->pessoaComFuncao($segunda, FuncaoPessoa::Motorista);
        $transportador = $this->pessoaComFuncao($segunda, FuncaoPessoa::Transportador);
        $veiculo = $this->veiculo($segunda);
        $conta = $this->conta($segunda, $motorista);

        $this->actingAs($user)->post(route('conjuntos-transporte.store'), [...$this->dados($motorista, $transportador, $veiculo, $conta), 'transportadora_id' => $segunda->id])->assertSessionHasErrors('pessoa_id');
    }

    public function test_consulta_cannot_create_or_change_set_status(): void
    {
        [$user, $transportadora] = $this->contexto(PerfilAcesso::Consulta);
        $this->actingAs($user)->post(route('conjuntos-transporte.store'), [])->assertForbidden();
        $conjunto = ConjuntoTransporte::factory()->create(['transportadora_id' => $transportadora->id]);
        $this->actingAs($user)->put(route('conjuntos-transporte.update', $conjunto), ['ativo' => false])->assertForbidden();
    }

    public function test_duplicate_creates_new_set_and_preserves_original(): void
    {
        [$user, $transportadora] = $this->contexto();
        $motorista = $this->pessoaComFuncao($transportadora, FuncaoPessoa::Motorista);
        $transportador = $this->pessoaComFuncao($transportadora, FuncaoPessoa::Transportador);
        $veiculo = $this->veiculo($transportadora);
        $conta = $this->conta($transportadora, $motorista);
        $dados = $this->dados($motorista, $transportador, $veiculo, $conta);
        $this->actingAs($user)->post(route('conjuntos-transporte.store'), $dados)->assertRedirect();
        $original = ConjuntoTransporte::query()->firstOrFail();

        $this->actingAs($user)->get(route('conjuntos-transporte.create', ['origem' => $original->id]))->assertOk();
        $this->actingAs($user)->post(route('conjuntos-transporte.store'), $dados)->assertRedirect();

        $this->assertDatabaseCount('conjuntos_transporte', 2);
        $this->assertDatabaseHas('conjuntos_transporte', ['id' => $original->id, 'ativo' => true]);
    }

    public function test_context_switch_cannot_view_or_change_another_carrier_set(): void
    {
        $user = User::factory()->create();
        [$primeira, $filialA] = $this->criarVinculo($user, 'A');
        [$segunda, $filialB] = $this->criarVinculo($user, 'B');
        $conjunto = ConjuntoTransporte::factory()->create(['transportadora_id' => $segunda->id]);
        $this->selecionarContexto($user, $primeira, $filialA);

        $this->actingAs($user)->get(route('conjuntos-transporte.show', $conjunto))->assertForbidden();
        $this->actingAs($user)->put(route('conjuntos-transporte.update', $conjunto), ['ativo' => false])->assertForbidden();
        $this->selecionarContexto($user, $segunda, $filialB);
        $this->actingAs($user)->put(route('conjuntos-transporte.update', $conjunto), ['ativo' => false])->assertRedirect();
    }

    /** @return array<string, mixed> */
    private function dados(Pessoa $motorista, Pessoa $transportador, Veiculo $veiculo, ContaBancaria $conta, array $implementos = []): array
    {
        return ['motorista_pessoa_id' => $motorista->id, 'transportador_pessoa_id' => $transportador->id, 'veiculo_id' => $veiculo->id, 'tipo_composicao' => 'caminhao', 'tipo_carroceria' => 'bau', 'conta_bancaria_id' => $conta->id, 'implementos' => $implementos];
    }

    private function pessoaComFuncao(Transportadora $transportadora, FuncaoPessoa ...$funcoes): Pessoa
    {
        $pessoa = Pessoa::factory()->create();
        $vinculo = $this->vincularPessoa($transportadora, $pessoa);
        foreach ($funcoes as $funcao) {
            PessoaFuncao::factory()->for($vinculo, 'transportadoraPessoa')->create(['funcao' => $funcao]);
        }

        return $pessoa;
    }

    private function vincularPessoa(Transportadora $transportadora, Pessoa $pessoa): TransportadoraPessoa
    {
        return TransportadoraPessoa::factory()->for($transportadora)->for($pessoa)->create(['ativo' => true]);
    }

    private function veiculo(Transportadora $transportadora, bool $ativo = true): Veiculo
    {
        $veiculo = Veiculo::factory()->create();
        TransportadoraVeiculo::factory()->for($transportadora)->for($veiculo)->create(['ativo' => $ativo]);

        return $veiculo;
    }

    private function implemento(Transportadora $transportadora): Implemento
    {
        $implemento = Implemento::factory()->create();
        TransportadoraImplemento::factory()->for($transportadora)->for($implemento)->create();

        return $implemento;
    }

    private function conta(Transportadora $transportadora, Pessoa $pessoa, bool $ativo = true): ContaBancaria
    {
        $conta = ContaBancaria::factory()->for($pessoa)->create();
        TransportadoraContaBancaria::factory()->for($transportadora)->for($conta)->create(['ativo' => $ativo]);

        return $conta;
    }

    /** @return array{0: User, 1: Transportadora} */
    private function contexto(PerfilAcesso $perfil = PerfilAcesso::Administrador): array
    {
        $user = User::factory()->create();
        [$transportadora, $filial] = $this->criarVinculo($user, 'única', $perfil);
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

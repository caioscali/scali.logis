<?php

namespace Tests\Feature;

use App\Domain\FreightContracts\Enums\SituacaoConferencia;
use App\Domain\FreightContracts\Enums\TipoContratoFrete;
use App\Domain\FreightContracts\Models\ContratoFreteCarreto;
use App\Domain\FreightContracts\Services\CadastroContratosFreteCarreto;
use App\Domain\LoadingOrders\Enums\SituacaoOrdem;
use App\Domain\LoadingOrders\Models\OrdemCarregamento;
use App\Domain\Organizations\Enums\PerfilAcesso;
use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Domain\Registry\Models\ConjuntoTransporte;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Tests\TestCase;

class ContratosFreteCarretoTest extends TestCase
{
    use DatabaseMigrations;

    public function test_registers_advance_and_balance_contracts_without_changing_order_values(): void
    {
        [$user, $carrier, $branch] = $this->context();
        $order = $this->order($user, $carrier, $branch);

        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.store', $order), ['tipo' => 'adiantamento', 'numero_contrato' => 'CFC-1', 'valor_contrato' => '800.00'])->assertRedirect();
        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.store', $order), ['tipo' => 'saldo', 'numero_contrato' => 'CFC-2', 'valor_contrato' => '200.00'])->assertRedirect();

        $this->assertCount(2, ContratoFreteCarreto::query()->where('ordem_carregamento_id', $order->id)->get());
        $this->assertSame('1000.00', $order->fresh()->valor_frete);
        $this->assertSame('800.00', $order->fresh()->valor_adiantamento);
        $this->assertSame('200.00', $order->fresh()->valor_saldo);
    }

    public function test_order_without_contract_is_reported_and_draft_cannot_register(): void
    {
        [$user, $carrier, $branch] = $this->context();
        $draft = $this->order($user, $carrier, $branch, SituacaoOrdem::Rascunho);

        $this->actingAs($user)->get(route('ordens-carregamento.show', $draft))->assertOk();
        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.store', $draft), ['tipo' => 'saldo', 'numero_contrato' => 'CFC-DRAFT', 'valor_contrato' => '200.00'])->assertForbidden();
        $this->assertDatabaseCount('contratos_frete_carreto', 0);
    }

    public function test_cancelled_order_keeps_existing_contracts_visible_but_cannot_register_new_ones(): void
    {
        [$user, $carrier, $branch] = $this->context();
        $order = $this->order($user, $carrier, $branch);
        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.store', $order), ['tipo' => 'saldo', 'numero_contrato' => 'CFC-CANCEL', 'valor_contrato' => '200.00']);
        $order->update(['situacao' => SituacaoOrdem::Cancelada]);

        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.store', $order), ['tipo' => 'adiantamento', 'numero_contrato' => 'CFC-CANCEL-2', 'valor_contrato' => '800.00'])->assertForbidden();
        $this->assertDatabaseHas('contratos_frete_carreto', ['ordem_carregamento_id' => $order->id, 'numero_contrato' => 'CFC-CANCEL']);
    }

    public function test_number_and_money_are_required_and_money_has_at_most_two_decimals(): void
    {
        [$user, $carrier, $branch] = $this->context();
        $order = $this->order($user, $carrier, $branch);

        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.store', $order), [])->assertSessionHasErrors(['tipo', 'numero_contrato', 'valor_contrato']);
        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.store', $order), ['tipo' => 'saldo', 'numero_contrato' => 'CFC-DECIMAL', 'valor_contrato' => '200.999'])->assertSessionHasErrors('valor_contrato');
    }

    public function test_equal_contract_can_be_confirmed_but_divergent_contract_cannot(): void
    {
        [$user, $carrier, $branch] = $this->context(PerfilAcesso::Administrador);
        $order = $this->order($user, $carrier, $branch);
        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.store', $order), ['tipo' => 'saldo', 'numero_contrato' => 'CFC-EQUAL', 'valor_contrato' => '200.00']);
        $contract = ContratoFreteCarreto::query()->firstOrFail();

        $this->assertSame(SituacaoConferencia::Pendente, $contract->situacao_conferencia);
        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.confirm', [$order, $contract]))->assertRedirect();
        $this->assertSame(SituacaoConferencia::Conferido, $contract->fresh()->situacao_conferencia);

        $divergent = $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.store', $order), ['tipo' => 'adiantamento', 'numero_contrato' => 'CFC-DIVERGENT', 'valor_contrato' => '700.00']);
        $divergent->assertRedirect();
        $contract = ContratoFreteCarreto::query()->where('tipo', TipoContratoFrete::Adiantamento)->firstOrFail();
        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.confirm', [$order, $contract]))->assertSessionHasErrors('contrato');
        $this->assertSame(SituacaoConferencia::Divergente, $contract->fresh()->situacao_conferencia);
    }

    public function test_editing_a_conferred_contract_invalidates_it_and_preserves_history(): void
    {
        [$user, $carrier, $branch] = $this->context();
        $order = $this->order($user, $carrier, $branch);
        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.store', $order), ['tipo' => 'saldo', 'numero_contrato' => 'CFC-HISTORY', 'valor_contrato' => '200.00']);
        $contract = ContratoFreteCarreto::query()->firstOrFail();
        $this->actingAs($user)->put(route('ordens-carregamento.contratos-frete.update', [$order, $contract]), ['numero_contrato' => 'CFC-HISTORY-2', 'valor_contrato' => '190.00'])->assertRedirect();

        $contract = $contract->fresh();
        $this->assertSame(SituacaoConferencia::Divergente, $contract->situacao_conferencia);
        $this->assertSame('CFC-HISTORY', $contract->historico()->firstOrFail()->numero_contrato);
        $this->assertNull($contract->conferido_por);
    }

    public function test_contract_number_is_unique_per_carrier_but_can_repeat_in_another_carrier(): void
    {
        [$user, $carrier, $branch] = $this->context();
        $order = $this->order($user, $carrier, $branch);
        $payload = ['tipo' => 'saldo', 'numero_contrato' => 'CFC-SAME', 'valor_contrato' => '200.00'];
        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.store', $order), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.store', $order), [...$payload, 'tipo' => 'adiantamento'])->assertSessionHasErrors('numero_contrato');
        [$other, $otherCarrier, $otherBranch] = $this->context();
        $otherOrder = $this->order($other, $otherCarrier, $otherBranch);
        $this->actingAs($other)->post(route('ordens-carregamento.contratos-frete.store', $otherOrder), $payload)->assertRedirect();
        $this->assertSame(2, ContratoFreteCarreto::query()->where('numero_contrato', 'CFC-SAME')->count());
    }

    public function test_same_order_cannot_have_two_contracts_of_the_same_type(): void
    {
        [$user, $carrier, $branch] = $this->context();
        $order = $this->order($user, $carrier, $branch);
        $payload = ['tipo' => 'saldo', 'numero_contrato' => 'CFC-FIRST', 'valor_contrato' => '200.00'];
        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.store', $order), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.store', $order), [...$payload, 'numero_contrato' => 'CFC-SECOND'])->assertSessionHasErrors('tipo');
        $this->assertDatabaseCount('contratos_frete_carreto', 1);
    }

    public function test_other_carrier_and_other_branch_are_isolated(): void
    {
        $user = User::factory()->create();
        [$carrier, $branch] = $this->vinculo($user, 'A');
        [$otherCarrier, $otherBranch] = $this->vinculo($user, 'B');
        $order = $this->order($user, $carrier, $branch);
        $this->selecionar($user, $otherCarrier, $otherBranch);
        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.store', $order), ['tipo' => 'saldo', 'numero_contrato' => 'CFC-ISOLATED', 'valor_contrato' => '200.00'])->assertForbidden();
    }

    public function test_authorized_user_cannot_cross_branch_within_same_carrier(): void
    {
        [$user, $carrier, $branch] = $this->context();
        $otherBranch = Filial::factory()->for($carrier)->create(['ativo' => true]);
        $link = UsuarioTransportadora::query()->where('user_id', $user->id)->where('transportadora_id', $carrier->id)->firstOrFail();
        $link->usuarioFiliais()->create(['filial_id' => $otherBranch->id]);
        $order = $this->order($user, $carrier, $branch);
        $this->selecionar($user, $carrier, $otherBranch);

        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.store', $order), ['tipo' => 'saldo', 'numero_contrato' => 'CFC-BRANCH', 'valor_contrato' => '200.00'])->assertForbidden();
    }

    public function test_consultation_user_cannot_register_or_confirm(): void
    {
        [$user, $carrier, $branch] = $this->context(PerfilAcesso::Consulta);
        $order = $this->order($user, $carrier, $branch);

        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.store', $order), ['tipo' => 'saldo', 'numero_contrato' => 'CFC-CONSULTA', 'valor_contrato' => '200.00'])->assertForbidden();
    }

    public function test_operator_can_register_but_cannot_confirm(): void
    {
        [$user, $carrier, $branch] = $this->context(PerfilAcesso::Operador);
        $order = $this->order($user, $carrier, $branch);
        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.store', $order), ['tipo' => 'saldo', 'numero_contrato' => 'CFC-OPERATOR', 'valor_contrato' => '200.00'])->assertRedirect();
        $contract = ContratoFreteCarreto::query()->firstOrFail();

        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.confirm', [$order, $contract]))->assertForbidden();
    }

    public function test_full_bank_data_is_only_returned_to_authorized_profile(): void
    {
        [$admin, $carrier, $branch] = $this->context(PerfilAcesso::Administrador);
        $set = ConjuntoTransporte::factory()->create(['transportadora_id' => $carrier->id]);
        $order = $this->order($admin, $carrier, $branch, SituacaoOrdem::Emitida, $set->id);
        $adminData = app(CadastroContratosFreteCarreto::class)->dadosParaConsulta($this->contextRequest($carrier, $branch), $admin, $order);
        $this->assertTrue($adminData['dados']['bancario']['autorizado']);
        $this->assertNotNull($adminData['dados']['bancario']['numero_conta']);

        $operator = User::factory()->create();
        $link = UsuarioTransportadora::factory()->for($operator, 'usuario')->for($carrier)->create(['perfil' => PerfilAcesso::Operador, 'ativo' => true]);
        $link->usuarioFiliais()->create(['filial_id' => $branch->id]);
        $operatorOrder = $this->order($operator, $carrier, $branch, SituacaoOrdem::Emitida, $set->id);
        $operatorData = app(CadastroContratosFreteCarreto::class)->dadosParaConsulta($this->contextRequest($carrier, $branch), $operator, $operatorOrder);
        $this->assertFalse($operatorData['dados']['bancario']['autorizado']);
    }

    /** @return array{0: User, 1: Transportadora, 2: Filial} */
    private function context(PerfilAcesso $perfil = PerfilAcesso::Administrador): array
    {
        $user = User::factory()->create();
        [$carrier, $branch] = $this->vinculo($user, uniqid('cfc-'), $perfil);
        $this->selecionar($user, $carrier, $branch);

        return [$user, $carrier, $branch];
    }

    /** @return array{0: Transportadora, 1: Filial} */
    private function vinculo(User $user, string $suffix, PerfilAcesso $perfil = PerfilAcesso::Administrador): array
    {
        $carrier = Transportadora::factory()->create(['nome_fantasia' => 'Transportadora '.$suffix]);
        $branch = Filial::factory()->for($carrier)->create(['ativo' => true]);
        $link = UsuarioTransportadora::factory()->for($user, 'usuario')->for($carrier)->create(['perfil' => $perfil, 'ativo' => true]);
        $link->usuarioFiliais()->create(['filial_id' => $branch->id]);

        return [$carrier, $branch];
    }

    private function selecionar(User $user, Transportadora $carrier, Filial $branch): void
    {
        $this->actingAs($user)->post(route('contexto.selecionar.store'), ['transportadora_id' => $carrier->id, 'filial_id' => $branch->id]);
    }

    private function contextRequest(Transportadora $carrier, Filial $branch): Request
    {
        $request = Request::create('/');
        $request->setLaravelSession(app('session.store'));
        $request->session()->put([
            'contexto.transportadora_id' => $carrier->id,
            'contexto.filial_id' => $branch->id,
        ]);

        return $request;
    }

    private function order(User $user, Transportadora $carrier, Filial $branch, SituacaoOrdem $situacao = SituacaoOrdem::Emitida, ?int $setId = null): OrdemCarregamento
    {
        return OrdemCarregamento::factory()->create(['transportadora_id' => $carrier->id, 'filial_id' => $branch->id, 'created_by' => $user->id, 'conjunto_transporte_id' => $setId, 'situacao' => $situacao, 'valor_frete' => '1000.00', 'valor_adiantamento' => '800.00', 'valor_saldo' => '200.00']);
    }
}

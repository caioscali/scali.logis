<?php

namespace Tests\Feature;

use App\Domain\Finance\Models\HistoricoLancamentoFinanceiro;
use App\Domain\Finance\Models\LancamentoFinanceiro;
use App\Domain\LoadingOrders\Enums\SituacaoOrdem;
use App\Domain\LoadingOrders\Models\OrdemCarregamento;
use App\Domain\Organizations\Enums\PerfilAcesso;
use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class LancamentosFinanceirosTest extends TestCase
{
    use DatabaseMigrations;

    public function test_conferred_contract_creates_one_pending_entry_and_repeated_confirmation_is_idempotent(): void
    {
        [$user, $order] = $this->context();
        $contract = $this->registerAndConfirm($user, $order, 'CFC-100', '200.00');

        $this->assertDatabaseHas('lancamentos_financeiros', ['contrato_frete_carreto_id' => $contract->id, 'situacao' => 'pendente', 'valor' => '200.00']);
        $this->assertDatabaseCount('lancamentos_financeiros', 1);
        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.confirm', [$order, $contract]))->assertRedirect();
        $this->assertDatabaseCount('lancamentos_financeiros', 1);
    }

    public function test_pending_or_divergent_contract_does_not_create_entry(): void
    {
        [$user, $order] = $this->context();
        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.store', $order), ['tipo' => 'saldo', 'numero_contrato' => 'CFC-PENDING', 'valor_contrato' => '200.00']);
        $this->assertDatabaseCount('lancamentos_financeiros', 0);
        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.store', $order), ['tipo' => 'adiantamento', 'numero_contrato' => 'CFC-DIVERGENT', 'valor_contrato' => '700.00']);
        $contract = $order->contratosFrete()->where('numero_contrato', 'CFC-DIVERGENT')->firstOrFail();
        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.confirm', [$order, $contract]))->assertSessionHasErrors('contrato');
        $this->assertDatabaseCount('lancamentos_financeiros', 0);
    }

    public function test_authorized_user_can_adjust_competence_and_approve_but_rejection_requires_reason(): void
    {
        [$user, $order] = $this->context(PerfilAcesso::Gerente);
        $contract = $this->registerAndConfirm($user, $order, 'CFC-101', '200.00');
        $entry = LancamentoFinanceiro::query()->firstOrFail();

        $this->actingAs($user)->put(route('lancamentos-financeiros.competencia', $entry), ['competencia' => '2026-10', 'motivo' => 'Competência contábil do fechamento.'])->assertRedirect();
        $this->assertSame('2026-10-01', $entry->fresh()->competencia->toDateString());
        $this->assertDatabaseHas('historicos_lancamentos_financeiros', ['lancamento_financeiro_id' => $entry->id, 'acao' => 'competencia_alterada']);

        $this->actingAs($user)->post(route('lancamentos-financeiros.reject', $entry), [])->assertSessionHasErrors('motivo_rejeicao');
        $this->actingAs($user)->post(route('lancamentos-financeiros.approve', $entry))->assertRedirect();
        $this->assertSame('aprovado', $entry->fresh()->situacao->value);
        $this->assertCount(3, HistoricoLancamentoFinanceiro::query()->where('lancamento_financeiro_id', $entry->id)->get());
        $this->assertNotNull($contract->fresh()->conferido_em);
    }

    public function test_operator_and_consultation_cannot_approve(): void
    {
        [$user, $order] = $this->context();
        $this->registerAndConfirm($user, $order, 'CFC-102', '200.00');
        $entry = LancamentoFinanceiro::query()->firstOrFail();
        $operator = User::factory()->create();
        $operatorLink = UsuarioTransportadora::factory()->for($operator, 'usuario')->for($order->transportadora)->create(['perfil' => PerfilAcesso::Operador, 'ativo' => true]);
        $operatorLink->usuarioFiliais()->create(['filial_id' => $order->filial_id]);
        $this->actingAs($operator)->post(route('contexto.selecionar.store'), ['transportadora_id' => $order->transportadora_id, 'filial_id' => $order->filial_id]);
        $this->actingAs($operator)->post(route('lancamentos-financeiros.approve', $entry))->assertForbidden();

        $consulta = User::factory()->create();
        $link = UsuarioTransportadora::factory()->for($consulta, 'usuario')->for($order->transportadora)->create(['perfil' => PerfilAcesso::Consulta, 'ativo' => true]);
        $link->usuarioFiliais()->create(['filial_id' => $order->filial_id]);
        $this->actingAs($consulta)->post(route('contexto.selecionar.store'), ['transportadora_id' => $order->transportadora_id, 'filial_id' => $order->filial_id]);
        $this->actingAs($consulta)->post(route('lancamentos-financeiros.approve', $entry))->assertForbidden();
    }

    public function test_rejects_entry_and_prevents_approval_after_rejection(): void
    {
        [$user, $order] = $this->context();
        $this->registerAndConfirm($user, $order, 'CFC-104', '200.00');
        $entry = LancamentoFinanceiro::query()->firstOrFail();
        $this->actingAs($user)->post(route('lancamentos-financeiros.reject', $entry), ['motivo_rejeicao' => 'Documento precisa ser revisado.'])->assertRedirect();
        $this->assertSame('rejeitado', $entry->fresh()->situacao->value);
        $this->actingAs($user)->post(route('lancamentos-financeiros.approve', $entry))->assertSessionHasErrors('lancamento');
    }

    public function test_entries_are_isolated_by_carrier_and_branch(): void
    {
        [$user, $order] = $this->context();
        $this->registerAndConfirm($user, $order, 'CFC-105', '200.00');
        $entry = LancamentoFinanceiro::query()->firstOrFail();
        [$otherUser, $otherOrder] = $this->context();
        $this->actingAs($otherUser)->get(route('lancamentos-financeiros.show', $entry))->assertForbidden();
        $this->actingAs($otherUser)->get(route('lancamentos-financeiros.index'))->assertOk();
        $this->assertSame([], $this->get(route('lancamentos-financeiros.index'))->inertiaProps('lancamentos.data'));
        $this->assertNotSame($order->transportadora_id, $otherOrder->transportadora_id);
    }

    public function test_editing_contract_cancels_pending_entry_and_approved_entry_is_protected(): void
    {
        [$user, $order] = $this->context();
        $contract = $this->registerAndConfirm($user, $order, 'CFC-106', '200.00');
        $entry = LancamentoFinanceiro::query()->firstOrFail();
        $this->actingAs($user)->put(route('ordens-carregamento.contratos-frete.update', [$order, $contract]), ['numero_contrato' => 'CFC-106B', 'valor_contrato' => '190.00'])->assertRedirect();
        $this->assertSame('cancelado', $entry->fresh()->situacao->value);
        $this->assertDatabaseHas('historicos_lancamentos_financeiros', ['acao' => 'cancelado_por_alteracao_cfc']);

        $contract = $contract->fresh();
        $this->actingAs($user)->put(route('ordens-carregamento.contratos-frete.update', [$order, $contract]), ['numero_contrato' => 'CFC-106C', 'valor_contrato' => '200.00']);
        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.confirm', [$order, $contract->fresh()]));
        $newEntry = LancamentoFinanceiro::query()->latest('id')->firstOrFail();
        $this->actingAs($user)->post(route('lancamentos-financeiros.approve', $newEntry));
        $this->actingAs($user)->put(route('ordens-carregamento.contratos-frete.update', [$order, $contract->fresh()]), ['numero_contrato' => 'CFC-106D', 'valor_contrato' => '200.00'])->assertSessionHasErrors('contrato');
    }

    /** @return array{0: User, 1: OrdemCarregamento} */
    private function context(PerfilAcesso $perfil = PerfilAcesso::Administrador): array
    {
        $user = User::factory()->create();
        $carrier = Transportadora::factory()->create();
        $branch = Filial::factory()->for($carrier)->create(['ativo' => true]);
        $link = UsuarioTransportadora::factory()->for($user, 'usuario')->for($carrier)->create(['perfil' => $perfil, 'ativo' => true]);
        $link->usuarioFiliais()->create(['filial_id' => $branch->id]);
        $this->actingAs($user)->post(route('contexto.selecionar.store'), ['transportadora_id' => $carrier->id, 'filial_id' => $branch->id]);
        $order = OrdemCarregamento::factory()->create(['transportadora_id' => $carrier->id, 'filial_id' => $branch->id, 'created_by' => $user->id, 'situacao' => SituacaoOrdem::Emitida, 'valor_frete' => '1000.00', 'valor_adiantamento' => '800.00', 'valor_saldo' => '200.00']);

        return [$user, $order];
    }

    private function registerAndConfirm(User $user, OrdemCarregamento $order, string $number, string $value): object
    {
        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.store', $order), ['tipo' => 'saldo', 'numero_contrato' => $number, 'valor_contrato' => $value])->assertRedirect();
        $contract = $order->contratosFrete()->where('numero_contrato', $number)->firstOrFail();
        $this->actingAs($user)->post(route('ordens-carregamento.contratos-frete.confirm', [$order, $contract]))->assertRedirect();

        return $contract->fresh();
    }
}

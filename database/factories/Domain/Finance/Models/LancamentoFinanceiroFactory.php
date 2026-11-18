<?php

namespace Database\Factories\Domain\Finance\Models;

use App\Domain\Finance\Enums\SituacaoLancamentoFinanceiro;
use App\Domain\Finance\Models\LancamentoFinanceiro;
use App\Domain\FreightContracts\Enums\TipoContratoFrete;
use App\Domain\FreightContracts\Models\ContratoFreteCarreto;
use App\Domain\LoadingOrders\Models\OrdemCarregamento;
use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\Transportadora;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LancamentoFinanceiro> */
class LancamentoFinanceiroFactory extends Factory
{
    protected $model = LancamentoFinanceiro::class;

    public function definition(): array
    {
        return [
            'transportadora_id' => Transportadora::factory(),
            'filial_id' => Filial::factory(),
            'ordem_carregamento_id' => OrdemCarregamento::factory(),
            'contrato_frete_carreto_id' => ContratoFreteCarreto::factory(),
            'tipo' => TipoContratoFrete::Saldo,
            'competencia' => now()->startOfMonth()->toDateString(),
            'numero_cfc' => fake()->unique()->numerify('CFC-#####'),
            'valor' => '200.00',
            'situacao' => SituacaoLancamentoFinanceiro::Pendente,
            'created_by' => User::factory(),
        ];
    }
}

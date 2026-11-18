<?php

namespace Database\Factories\Domain\FreightContracts\Models;

use App\Domain\FreightContracts\Enums\SituacaoConferencia;
use App\Domain\FreightContracts\Enums\TipoContratoFrete;
use App\Domain\FreightContracts\Models\ContratoFreteCarreto;
use App\Domain\LoadingOrders\Models\OrdemCarregamento;
use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\Transportadora;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContratoFreteCarreto> */
class ContratoFreteCarretoFactory extends Factory
{
    protected $model = ContratoFreteCarreto::class;

    public function definition(): array
    {
        return [
            'transportadora_id' => Transportadora::factory(),
            'filial_id' => Filial::factory(),
            'ordem_carregamento_id' => OrdemCarregamento::factory(),
            'tipo' => TipoContratoFrete::Adiantamento,
            'numero_contrato' => fake()->unique()->numerify('CFC-#####'),
            'valor_referencia' => '800.00',
            'valor_contrato' => '800.00',
            'situacao_conferencia' => SituacaoConferencia::Pendente,
            'created_by' => User::factory(),
        ];
    }
}

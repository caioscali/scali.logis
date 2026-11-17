<?php

namespace Database\Factories\Domain\Registry\Models;

use App\Domain\Finance\Models\ContaBancaria;
use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Registry\Enums\TipoCarroceria;
use App\Domain\Registry\Enums\TipoComposicao;
use App\Domain\Registry\Models\ConjuntoTransporte;
use App\Domain\Registry\Models\Pessoa;
use App\Domain\Registry\Models\Veiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConjuntoTransporte>
 */
class ConjuntoTransporteFactory extends Factory
{
    protected $model = ConjuntoTransporte::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transportadora_id' => Transportadora::factory(),
            'motorista_pessoa_id' => Pessoa::factory(),
            'transportador_pessoa_id' => Pessoa::factory(),
            'veiculo_id' => Veiculo::factory(),
            'tipo_composicao' => TipoComposicao::Caminhao,
            'tipo_carroceria' => TipoCarroceria::Bau,
            'conta_bancaria_id' => ContaBancaria::factory(),
            'ativo' => true,
        ];
    }
}

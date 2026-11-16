<?php

namespace Database\Factories\Domain\Registry\Models;

use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Registry\Models\TransportadoraVeiculo;
use App\Domain\Registry\Models\Veiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransportadoraVeiculo>
 */
class TransportadoraVeiculoFactory extends Factory
{
    protected $model = TransportadoraVeiculo::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['transportadora_id' => Transportadora::factory(), 'veiculo_id' => Veiculo::factory(), 'ativo' => true];
    }
}

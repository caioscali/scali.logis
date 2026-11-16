<?php

namespace Database\Factories\Domain\Finance\Models;

use App\Domain\Finance\Models\ContaBancaria;
use App\Domain\Finance\Models\TransportadoraContaBancaria;
use App\Domain\Organizations\Models\Transportadora;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransportadoraContaBancaria>
 */
class TransportadoraContaBancariaFactory extends Factory
{
    protected $model = TransportadoraContaBancaria::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['transportadora_id' => Transportadora::factory(), 'conta_bancaria_id' => ContaBancaria::factory(), 'ativo' => true];
    }
}

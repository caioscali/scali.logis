<?php

namespace Database\Factories\Domain\Organizations\Models;

use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\Transportadora;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Filial>
 */
class FilialFactory extends Factory
{
    protected $model = Filial::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transportadora_id' => Transportadora::factory(),
            'nome' => fake()->city(),
            'cnpj' => null,
            'cidade' => fake()->city(),
            'uf' => fake()->randomElement(['SP', 'RJ', 'MG']),
            'matriz' => false,
            'ativo' => true,
        ];
    }
}

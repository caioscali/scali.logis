<?php

namespace Database\Factories\Domain\Registry\Models;

use App\Domain\Registry\Models\Veiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Veiculo>
 */
class VeiculoFactory extends Factory
{
    protected $model = Veiculo::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['placa' => fake()->unique()->randomElement(['ABC1234', 'ABC1D23', 'DEF5678']), 'cidade' => fake()->city()];
    }
}

<?php

namespace Database\Factories\Domain\Registry\Models;

use App\Domain\Registry\Enums\TipoPessoa;
use App\Domain\Registry\Models\Pessoa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pessoa>
 */
class PessoaFactory extends Factory
{
    protected $model = Pessoa::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo' => TipoPessoa::Fisica,
            'nome' => fake()->name(),
            'documento' => fake()->unique()->numerify('###########'),
            'telefone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'cidade' => fake()->city(),
            'uf' => fake()->randomElement(['SP', 'RJ', 'MG']),
        ];
    }
}

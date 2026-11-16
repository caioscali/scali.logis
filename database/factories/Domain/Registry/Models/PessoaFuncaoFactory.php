<?php

namespace Database\Factories\Domain\Registry\Models;

use App\Domain\Registry\Enums\FuncaoPessoa;
use App\Domain\Registry\Models\PessoaFuncao;
use App\Domain\Registry\Models\TransportadoraPessoa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PessoaFuncao>
 */
class PessoaFuncaoFactory extends Factory
{
    protected $model = PessoaFuncao::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transportadora_pessoa_id' => TransportadoraPessoa::factory(),
            'funcao' => fake()->randomElement(FuncaoPessoa::cases()),
        ];
    }
}

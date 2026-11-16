<?php

namespace Database\Factories\Domain\Registry\Models;

use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Registry\Models\Pessoa;
use App\Domain\Registry\Models\TransportadoraPessoa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransportadoraPessoa>
 */
class TransportadoraPessoaFactory extends Factory
{
    protected $model = TransportadoraPessoa::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transportadora_id' => Transportadora::factory(),
            'pessoa_id' => Pessoa::factory(),
            'ativo' => true,
        ];
    }
}

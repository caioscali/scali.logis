<?php

namespace Database\Factories\Domain\Finance\Models;

use App\Domain\Finance\Enums\TipoChavePix;
use App\Domain\Finance\Enums\TipoConta;
use App\Domain\Finance\Models\ContaBancaria;
use App\Domain\Registry\Models\Pessoa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContaBancaria>
 */
class ContaBancariaFactory extends Factory
{
    protected $model = ContaBancaria::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pessoa_id' => Pessoa::factory(),
            'banco_codigo' => '001',
            'banco_nome' => 'Banco de Teste',
            'agencia' => '0001',
            'numero_conta' => '123456',
            'digito_conta' => '7',
            'tipo_conta' => TipoConta::Corrente,
            'tipo_chave_pix' => TipoChavePix::Email,
            'chave_pix' => fake()->safeEmail(),
        ];
    }
}

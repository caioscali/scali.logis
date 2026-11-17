<?php

namespace Database\Factories\Domain\LoadingOrders\Models;

use App\Domain\LoadingOrders\Enums\SituacaoOrdem;
use App\Domain\LoadingOrders\Models\OrdemCarregamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrdemCarregamento> */
class OrdemCarregamentoFactory extends Factory
{
    protected $model = OrdemCarregamento::class;

    public function definition(): array
    {
        return [
            'numero' => fake()->unique()->numberBetween(1, 999999),
            'situacao' => SituacaoOrdem::Rascunho,
            'produto' => fake()->word(),
            'valor_frete' => '1000.00',
            'valor_adiantamento' => '0.00',
            'valor_saldo' => '1000.00',
        ];
    }
}

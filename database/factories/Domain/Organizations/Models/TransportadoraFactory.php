<?php

namespace Database\Factories\Domain\Organizations\Models;

use App\Domain\Organizations\Models\GrupoEmpresarial;
use App\Domain\Organizations\Models\Transportadora;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transportadora>
 */
class TransportadoraFactory extends Factory
{
    protected $model = Transportadora::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grupo_empresarial_id' => GrupoEmpresarial::factory(),
            'razao_social' => fake()->company(),
            'nome_fantasia' => fake()->company(),
            'cnpj' => null,
            'ativo' => true,
        ];
    }
}

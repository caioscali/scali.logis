<?php

namespace Database\Factories\Domain\Organizations\Models;

use App\Domain\Organizations\Models\GrupoEmpresarial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GrupoEmpresarial>
 */
class GrupoEmpresarialFactory extends Factory
{
    protected $model = GrupoEmpresarial::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->company(),
            'ativo' => true,
        ];
    }
}

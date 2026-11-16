<?php

namespace Database\Factories\Domain\Registry\Models;

use App\Domain\Registry\Models\ConjuntoImplemento;
use App\Domain\Registry\Models\ConjuntoTransporte;
use App\Domain\Registry\Models\Implemento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConjuntoImplemento>
 */
class ConjuntoImplementoFactory extends Factory
{
    protected $model = ConjuntoImplemento::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['conjunto_transporte_id' => ConjuntoTransporte::factory(), 'implemento_id' => Implemento::factory(), 'posicao' => 1];
    }
}

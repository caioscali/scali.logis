<?php

namespace Database\Factories\Domain\Registry\Models;

use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Registry\Models\Implemento;
use App\Domain\Registry\Models\TransportadoraImplemento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransportadoraImplemento>
 */
class TransportadoraImplementoFactory extends Factory
{
    protected $model = TransportadoraImplemento::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['transportadora_id' => Transportadora::factory(), 'implemento_id' => Implemento::factory(), 'ativo' => true];
    }
}

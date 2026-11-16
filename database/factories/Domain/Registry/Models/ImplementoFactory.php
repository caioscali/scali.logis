<?php

namespace Database\Factories\Domain\Registry\Models;

use App\Domain\Registry\Models\Implemento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Implemento>
 */
class ImplementoFactory extends Factory
{
    protected $model = Implemento::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['placa' => fake()->unique()->randomElement(['GHI9012', 'JKL3M45', 'MNO6789'])];
    }
}

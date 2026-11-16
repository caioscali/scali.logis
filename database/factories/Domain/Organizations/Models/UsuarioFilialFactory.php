<?php

namespace Database\Factories\Domain\Organizations\Models;

use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\UsuarioFilial;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsuarioFilial>
 */
class UsuarioFilialFactory extends Factory
{
    protected $model = UsuarioFilial::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'usuario_transportadora_id' => UsuarioTransportadora::factory(),
            'filial_id' => Filial::factory(),
        ];
    }
}

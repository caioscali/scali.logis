<?php

namespace Database\Factories\Domain\Organizations\Models;

use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsuarioTransportadora>
 */
class UsuarioTransportadoraFactory extends Factory
{
    protected $model = UsuarioTransportadora::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'transportadora_id' => Transportadora::factory(),
            'perfil' => 'operador',
            'ativo' => true,
        ];
    }
}

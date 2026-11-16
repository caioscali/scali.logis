<?php

namespace Tests\Feature;

use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_verified_users_can_access_the_dashboard(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $transportadora = Transportadora::factory()->create();
        $filial = Filial::factory()->for($transportadora)->create(['ativo' => true]);
        $vinculo = UsuarioTransportadora::factory()->for($user, 'usuario')->for($transportadora)->create();
        $vinculo->usuarioFiliais()->create(['filial_id' => $filial->id]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Dashboard')
                ->has('auth.user')
                ->where('auth.user.email', $user->email));
    }
}

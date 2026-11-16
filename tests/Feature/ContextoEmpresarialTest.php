<?php

namespace Tests\Feature;

use App\Domain\Organizations\Enums\PerfilAcesso;
use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ContextoEmpresarialTest extends TestCase
{
    use DatabaseMigrations;

    public function test_single_authorized_context_is_selected_automatically(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        [$transportadora, $filial] = $this->createContext($user);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->assertSame($transportadora->id, session('contexto.transportadora_id'));
        $this->assertSame($filial->id, session('contexto.filial_id'));
    }

    public function test_multiple_transportadoras_require_context_selection(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->createContext($user, 'A');
        $this->createContext($user, 'B');

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('contexto.selecionar'));
        $this->assertNull(session('contexto.transportadora_id'));
    }

    public function test_multiple_filiais_can_be_selected_and_switched(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        [$primeira, $filialA] = $this->createContext($user, 'A');
        $filialB = Filial::factory()->for($primeira)->create(['ativo' => true]);
        $vinculo = $user->vinculosTransportadoras()->where('transportadora_id', $primeira->id)->firstOrFail();
        $vinculo->usuarioFiliais()->create(['filial_id' => $filialB->id]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('contexto.selecionar'));
        $this->actingAs($user)->post(route('contexto.selecionar.store'), [
            'transportadora_id' => $primeira->id,
            'filial_id' => $filialB->id,
        ])->assertRedirect(route('dashboard'));

        $this->assertSame($primeira->id, session('contexto.transportadora_id'));
        $this->assertSame($filialB->id, session('contexto.filial_id'));
        $this->assertNotSame($filialA->id, session('contexto.filial_id'));
    }

    public function test_user_without_vinculo_is_sent_to_no_access_page(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('contexto.sem-vinculo'));
    }

    public function test_inactive_vinculo_or_filial_does_not_create_context(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        [$transportadora, $filial] = $this->createContext($user);
        $filial->update(['ativo' => false]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('contexto.sem-vinculo'));

        $filial->update(['ativo' => true]);
        $user->vinculosTransportadoras()->where('transportadora_id', $transportadora->id)->update(['ativo' => false]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('contexto.sem-vinculo'));
    }

    public function test_unauthorized_transportadora_or_filial_cannot_be_selected(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        [$permitida] = $this->createContext($user);
        $outra = Transportadora::factory()->create();
        $filialEstranha = Filial::factory()->for($outra)->create(['ativo' => true]);

        $this->actingAs($user)->post(route('contexto.selecionar.store'), [
            'transportadora_id' => $outra->id,
            'filial_id' => $filialEstranha->id,
        ])->assertSessionHasErrors('filial_id');
        $this->assertNull(session('contexto.transportadora_id'));
    }

    public function test_revoked_context_is_cleared_on_next_operational_request(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        [$transportadora] = $this->createContext($user);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $user->vinculosTransportadoras()->where('transportadora_id', $transportadora->id)->update(['ativo' => false]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('contexto.sem-vinculo'));
        $this->assertNull(session('contexto.transportadora_id'));
        $this->assertNull(session('contexto.filial_id'));
    }

    public function test_context_is_cleared_when_user_logs_out(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->createContext($user);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(session('contexto.transportadora_id'));
        $this->assertNull(session('contexto.filial_id'));
    }

    public function test_platform_administrator_has_no_operational_context_automatically(): void
    {
        config(['app.platform_admin_emails' => ['plataforma@scali.dev']]);
        $platform = User::factory()->create(['email' => 'plataforma@scali.dev', 'email_verified_at' => now()]);

        $this->actingAs($platform)->get(route('dashboard'))->assertRedirect(route('contexto.sem-vinculo'));
    }

    /**
     * @return array{0: Transportadora, 1: Filial}
     */
    private function createContext(User $user, string $suffix = ''): array
    {
        $transportadora = Transportadora::factory()->create(['nome_fantasia' => 'Transportadora '.$suffix]);
        $filial = Filial::factory()->for($transportadora)->create(['ativo' => true]);
        $vinculo = UsuarioTransportadora::factory()->for($user, 'usuario')->for($transportadora)->create([
            'perfil' => PerfilAcesso::Operador,
            'ativo' => true,
        ]);
        $vinculo->usuarioFiliais()->create(['filial_id' => $filial->id]);

        return [$transportadora, $filial];
    }
}

<?php

namespace Tests\Feature;

use App\Domain\Organizations\Enums\PerfilAcesso;
use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Domain\Organizations\Services\AcessoEmpresarial;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class EnterpriseAccessTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.platform_admin_emails' => ['plataforma@scali.dev']]);
    }

    public function test_one_user_can_have_different_profiles_in_two_transportadoras(): void
    {
        $usuario = User::factory()->create();
        $primeira = Transportadora::factory()->create();
        $segunda = Transportadora::factory()->create();
        $acesso = app(AcessoEmpresarial::class);

        $acesso->salvarVinculo($usuario, $primeira, PerfilAcesso::Administrador, true, []);
        $acesso->salvarVinculo($usuario, $segunda, PerfilAcesso::Operador, true, []);

        $this->assertSame(2, $usuario->vinculosTransportadoras()->count());
        $this->assertSame(PerfilAcesso::Administrador, $usuario->vinculosTransportadoras()->where('transportadora_id', $primeira->id)->firstOrFail()->perfil);
        $this->assertSame(PerfilAcesso::Operador, $usuario->vinculosTransportadoras()->where('transportadora_id', $segunda->id)->firstOrFail()->perfil);
    }

    public function test_duplicate_transportadora_vinculo_is_rejected(): void
    {
        $admin = $this->platformAdmin();
        $usuario = User::factory()->create();
        $transportadora = Transportadora::factory()->create();

        $dados = ['email' => $usuario->email, 'perfil' => 'consulta', 'filial_ids' => []];
        $this->actingAs($admin)->post(route('admin.transportadoras.usuarios.store', $transportadora), $dados)->assertRedirect();
        $this->actingAs($admin)->post(route('admin.transportadoras.usuarios.store', $transportadora), $dados)->assertSessionHasErrors('email');

        $this->assertDatabaseCount('usuarios_transportadoras', 1);
    }

    public function test_inactive_link_does_not_grant_transportadora_access(): void
    {
        $usuario = User::factory()->create();
        $transportadora = Transportadora::factory()->create();
        $vinculo = UsuarioTransportadora::factory()->for($usuario, 'usuario')->for($transportadora)->create();
        $acesso = app(AcessoEmpresarial::class);

        $vinculo->update(['ativo' => false]);

        $this->assertFalse($acesso->podeAcessarTransportadora($usuario, $transportadora));
    }

    public function test_public_registration_does_not_create_enterprise_access(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Usuário Público',
            'email' => 'publico@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('usuarios_transportadoras', 0);
        $this->assertDatabaseCount('usuarios_filiais', 0);
    }

    public function test_user_can_access_only_the_authorized_active_filial(): void
    {
        $usuario = User::factory()->create();
        $transportadora = Transportadora::factory()->create();
        $permitida = Filial::factory()->for($transportadora)->create(['ativo' => true]);
        $outra = Filial::factory()->for($transportadora)->create(['ativo' => true]);
        $inativa = Filial::factory()->for($transportadora)->create(['ativo' => false]);
        $vinculo = UsuarioTransportadora::factory()->for($usuario, 'usuario')->for($transportadora)->create();
        $vinculo->usuarioFiliais()->create(['filial_id' => $permitida->id]);
        $acesso = app(AcessoEmpresarial::class);

        $this->assertTrue($acesso->podeAcessarFilial($usuario, $permitida));
        $this->assertFalse($acesso->podeAcessarFilial($usuario, $outra));
        $this->assertFalse($acesso->podeAcessarFilial($usuario, $inativa));
    }

    public function test_filial_from_another_transportadora_cannot_be_authorized(): void
    {
        $admin = $this->platformAdmin();
        $transportadora = Transportadora::factory()->create();
        $outraTransportadora = Transportadora::factory()->create();
        $usuario = User::factory()->create();
        $filialEstranha = Filial::factory()->for($outraTransportadora)->create();

        $response = $this->actingAs($admin)->post(route('admin.transportadoras.usuarios.store', $transportadora), [
            'email' => $usuario->email,
            'perfil' => 'operador',
            'filial_ids' => [$filialEstranha->id],
        ]);

        $response->assertSessionHasErrors('filial_ids');
        $this->assertDatabaseCount('usuarios_transportadoras', 0);
    }

    public function test_duplicate_filial_authorization_is_rejected_by_request(): void
    {
        $admin = $this->platformAdmin();
        $transportadora = Transportadora::factory()->create();
        $usuario = User::factory()->create();
        $filial = Filial::factory()->for($transportadora)->create();

        $response = $this->actingAs($admin)->post(route('admin.transportadoras.usuarios.store', $transportadora), [
            'email' => $usuario->email,
            'perfil' => 'operador',
            'filial_ids' => [$filial->id, $filial->id],
        ]);

        $response->assertSessionHasErrors('filial_ids.1');
    }

    public function test_only_administrator_can_manage_links_and_cannot_manage_another_transportadora(): void
    {
        $admin = User::factory()->create();
        $gerente = User::factory()->create();
        $operador = User::factory()->create();
        $consulta = User::factory()->create();
        $transportadora = Transportadora::factory()->create();
        $outraTransportadora = Transportadora::factory()->create();
        $acesso = app(AcessoEmpresarial::class);

        $acesso->salvarVinculo($admin, $transportadora, PerfilAcesso::Administrador, true, []);
        $acesso->salvarVinculo($gerente, $transportadora, PerfilAcesso::Gerente, true, []);
        $acesso->salvarVinculo($operador, $transportadora, PerfilAcesso::Operador, true, []);
        $acesso->salvarVinculo($consulta, $transportadora, PerfilAcesso::Consulta, true, []);

        $this->actingAs($admin)->get(route('admin.transportadoras.usuarios.index', $transportadora))->assertOk();
        $this->actingAs($gerente)->get(route('admin.transportadoras.usuarios.index', $transportadora))->assertForbidden();
        $this->actingAs($operador)->get(route('admin.transportadoras.usuarios.index', $transportadora))->assertForbidden();
        $this->actingAs($consulta)->get(route('admin.transportadoras.usuarios.index', $transportadora))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.transportadoras.usuarios.index', $outraTransportadora))->assertForbidden();
    }

    public function test_platform_administrator_can_manage_links_without_operational_access(): void
    {
        $plataforma = $this->platformAdmin();
        $transportadora = Transportadora::factory()->create();
        $acesso = app(AcessoEmpresarial::class);

        $this->assertTrue($acesso->podeAdministrarUsuarios($plataforma, $transportadora));
        $this->assertFalse($acesso->podeAcessarTransportadora($plataforma, $transportadora));
        $this->actingAs($plataforma)->get(route('admin.transportadoras.usuarios.index', $transportadora))->assertOk();
    }

    public function test_user_cannot_elevate_own_profile_or_access_global_administration(): void
    {
        $usuario = User::factory()->create(['email_verified_at' => now()]);
        $transportadora = Transportadora::factory()->create();
        $vinculo = UsuarioTransportadora::factory()->for($usuario, 'usuario')->for($transportadora)->create(['perfil' => PerfilAcesso::Operador]);

        $this->actingAs($usuario)->put(route('admin.transportadoras.usuarios.update', [$transportadora, $vinculo]), [
            'perfil' => 'administrador',
            'ativo' => true,
            'filial_ids' => [],
        ])->assertForbidden();

        $this->actingAs($usuario)->get(route('admin.transportadoras.index'))->assertForbidden();
    }

    public function test_last_active_administrator_cannot_be_removed(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $transportadora = Transportadora::factory()->create();
        $vinculo = UsuarioTransportadora::factory()->for($admin, 'usuario')->for($transportadora)->create(['perfil' => PerfilAcesso::Administrador]);

        $this->actingAs($admin)->put(route('admin.transportadoras.usuarios.update', [$transportadora, $vinculo]), [
            'perfil' => 'gerente',
            'ativo' => true,
            'filial_ids' => [],
        ])->assertSessionHasErrors('perfil');

        $this->assertDatabaseHas('usuarios_transportadoras', ['id' => $vinculo->id, 'perfil' => 'administrador', 'ativo' => true]);
    }

    private function platformAdmin(): User
    {
        return User::factory()->create(['email' => 'plataforma@scali.dev', 'email_verified_at' => now()]);
    }
}

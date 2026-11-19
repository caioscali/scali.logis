<?php

namespace Tests\Feature;

use App\Domain\Organizations\Enums\PerfilAcesso;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProvisionLocalAdminCommandTest extends TestCase
{
    use DatabaseMigrations;

    public function test_command_creates_a_verified_admin_with_an_authorized_demo_context(): void
    {
        $exitCode = Artisan::call('app:provision-local-admin');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Administrador local criado.', $output);
        $this->assertMatchesRegularExpression('/Senha inicial/', $output);

        preg_match('/Senha inicial[^\n]*\n([^\r\n]+)/', $output, $matches);
        $password = $matches[1] ?? '';
        $user = User::query()->where('email', 'admin@scali.dev')->firstOrFail();
        $vinculo = UsuarioTransportadora::query()->whereBelongsTo($user, 'usuario')->firstOrFail();

        $this->assertNotSame('', $password);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(PerfilAcesso::Administrador, $vinculo->perfil);
        $this->assertTrue($vinculo->ativo);
        $this->assertSame(1, $vinculo->usuarioFiliais()->count());
        $this->assertDatabaseCount('grupos_empresariais', 1);
        $this->assertDatabaseCount('transportadoras', 1);
        $this->assertDatabaseCount('filiais', 1);
    }

    public function test_command_is_idempotent_and_does_not_reissue_the_password(): void
    {
        Artisan::call('app:provision-local-admin');
        $user = User::query()->where('email', 'admin@scali.dev')->firstOrFail();
        $passwordHash = $user->password;

        $exitCode = Artisan::call('app:provision-local-admin');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('já existia e foi sincronizado.', $output);
        $this->assertStringNotContainsString('Senha inicial', $output);
        $this->assertSame($passwordHash, $user->fresh()->password);
        $this->assertDatabaseCount('usuarios_transportadoras', 1);
        $this->assertDatabaseCount('usuarios_filiais', 1);
    }
}

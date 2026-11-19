<?php

namespace App\Console\Commands;

use App\Domain\Organizations\Enums\PerfilAcesso;
use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\GrupoEmpresarial;
use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Domain\Organizations\Services\AcessoEmpresarial;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('app:provision-local-admin {--reset-password : Generate a new local password for the existing administrator}')]
#[Description('Provision the local Scali Logis administrator and demo context')]
class ProvisionLocalAdmin extends Command
{
    public function __construct(private AcessoEmpresarial $acessoEmpresarial)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Este comando está disponível somente fora de produção.');

            return self::FAILURE;
        }

        $email = 'admin@scali.dev';
        $password = null;
        $wasCreated = false;
        $resetPassword = (bool) $this->option('reset-password');

        DB::transaction(function () use (&$password, &$wasCreated, $email, $resetPassword): void {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                $password = Str::password(32);
                $user = User::create([
                    'name' => 'Caio Scali',
                    'email' => $email,
                    'password' => $password,
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
                $wasCreated = true;
            } else {
                $attributes = [
                    'name' => 'Caio Scali',
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ];

                if ($resetPassword) {
                    $password = Str::password(32);
                    $attributes['password'] = $password;
                }

                $user->forceFill($attributes)->save();
            }

            $grupo = GrupoEmpresarial::query()->firstOrCreate(
                ['nome' => 'Demonstração Scali Logis'],
                ['ativo' => true],
            );

            $transportadora = Transportadora::query()->firstOrCreate(
                [
                    'grupo_empresarial_id' => $grupo->id,
                    'nome_fantasia' => 'Scali Logis Transportes',
                ],
                [
                    'razao_social' => 'Scali Logis Transportes',
                    'cnpj' => null,
                    'ativo' => true,
                ],
            );

            $filial = Filial::query()->firstOrCreate(
                [
                    'transportadora_id' => $transportadora->id,
                    'nome' => 'Matriz Demonstração',
                ],
                [
                    'cnpj' => null,
                    'cidade' => 'São Paulo',
                    'uf' => 'SP',
                    'matriz' => true,
                    'ativo' => true,
                ],
            );

            $vinculo = UsuarioTransportadora::query()
                ->where('user_id', $user->id)
                ->where('transportadora_id', $transportadora->id)
                ->first();

            $this->acessoEmpresarial->salvarVinculo(
                $user,
                $transportadora,
                PerfilAcesso::Administrador,
                true,
                [$filial->id],
                $vinculo,
                $user,
            );
        });

        $this->info($wasCreated ? 'Administrador local criado.' : 'Administrador local já existia e foi sincronizado.');
        $this->line("E-mail: {$email}");
        $this->line('Contexto: Demonstração Scali Logis / Scali Logis Transportes / Matriz Demonstração');

        if ($password !== null) {
            $this->warn('Senha inicial — exiba, copie e guarde agora; ela não é salva pelo comando:');
            $this->line($password);
        } else {
            $this->comment('Senha não foi alterada. Use --reset-password somente se precisar emitir uma nova senha.');
        }

        return self::SUCCESS;
    }
}

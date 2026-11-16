<?php

namespace App\Domain\Organizations\Services;

use App\Domain\Organizations\Enums\PerfilAcesso;
use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AcessoEmpresarial
{
    public function vinculoAtivo(User $usuario, Transportadora $transportadora): ?UsuarioTransportadora
    {
        return $usuario->vinculosTransportadoras()
            ->where('transportadora_id', $transportadora->id)
            ->where('ativo', true)
            ->first();
    }

    public function podeAcessarTransportadora(User $usuario, Transportadora $transportadora): bool
    {
        return $this->vinculoAtivo($usuario, $transportadora) !== null;
    }

    public function podeAcessarFilial(User $usuario, Filial $filial): bool
    {
        if (! $filial->ativo) {
            return false;
        }

        $vinculo = $usuario->vinculosTransportadoras()
            ->where('transportadora_id', $filial->transportadora_id)
            ->where('ativo', true)
            ->first();

        return $vinculo !== null
            && $vinculo->usuarioFiliais()->where('filial_id', $filial->id)->exists();
    }

    public function podeAdministrarUsuarios(User $usuario, Transportadora $transportadora): bool
    {
        if ($usuario->isPlatformAdministrator()) {
            return true;
        }

        return $this->vinculoAtivo($usuario, $transportadora)?->perfil->podeAdministrarUsuarios() ?? false;
    }

    public function transportadorasAcessiveis(User $usuario): Builder
    {
        return Transportadora::query()
            ->whereHas('usuariosTransportadoras', function (Builder $query) use ($usuario): void {
                $query->where('user_id', $usuario->id)->where('ativo', true);
            })
            ->where('ativo', true);
    }

    public function filiaisAcessiveis(User $usuario, Transportadora $transportadora): Builder
    {
        $vinculo = $this->vinculoAtivo($usuario, $transportadora);

        if ($vinculo === null) {
            return Filial::query()->whereKey(0);
        }

        return Filial::query()
            ->where('transportadora_id', $transportadora->id)
            ->where('ativo', true)
            ->whereHas('usuariosFiliais', fn (Builder $query) => $query->where('usuario_transportadora_id', $vinculo->id));
    }

    /**
     * @param  array<int, int>  $filialIds
     */
    public function salvarVinculo(
        User $alvo,
        Transportadora $transportadora,
        PerfilAcesso $perfil,
        bool $ativo,
        array $filialIds,
        ?UsuarioTransportadora $vinculo = null,
        ?User $executor = null,
    ): UsuarioTransportadora {
        return DB::transaction(function () use ($alvo, $transportadora, $perfil, $ativo, $filialIds, $vinculo, $executor): UsuarioTransportadora {
            if ($vinculo === null && UsuarioTransportadora::query()
                ->where('user_id', $alvo->id)
                ->where('transportadora_id', $transportadora->id)
                ->exists()) {
                throw ValidationException::withMessages(['email' => 'Este usuário já possui vínculo com a transportadora.']);
            }

            $vinculo ??= UsuarioTransportadora::query()->firstOrNew([
                'user_id' => $alvo->id,
                'transportadora_id' => $transportadora->id,
            ]);

            if ($vinculo->exists && $vinculo->transportadora_id !== $transportadora->id) {
                throw ValidationException::withMessages(['transportadora' => 'O vínculo não pertence à transportadora informada.']);
            }

            $this->validarAdministracaoRemovida($vinculo, $perfil, $ativo);
            $this->validarFiliais($transportadora, $filialIds);

            $vinculo->fill([
                'user_id' => $alvo->id,
                'transportadora_id' => $transportadora->id,
                'perfil' => $perfil,
                'ativo' => $ativo,
            ])->save();

            if ($vinculo->wasChanged(['perfil', 'ativo'])) {
                Log::info('Vínculo empresarial atualizado', [
                    'usuario_transportadora_id' => $vinculo->id,
                    'user_id' => $alvo->id,
                    'transportadora_id' => $transportadora->id,
                    'executor_user_id' => $executor?->id,
                    'perfil' => $perfil->value,
                    'ativo' => $ativo,
                ]);
            }

            $vinculo->usuarioFiliais()->delete();
            $vinculo->usuarioFiliais()->createMany(array_map(
                fn (int $filialId): array => ['filial_id' => $filialId],
                $filialIds,
            ));

            return $vinculo->load(['usuario', 'transportadora', 'usuarioFiliais.filial']);
        });
    }

    public function desativarVinculo(UsuarioTransportadora $vinculo, ?User $executor = null): UsuarioTransportadora
    {
        return $this->salvarVinculo(
            $vinculo->usuario,
            $vinculo->transportadora,
            $vinculo->perfil,
            false,
            [],
            $vinculo,
            $executor,
        );
    }

    /**
     * @param  array<int, int>  $filialIds
     */
    private function validarFiliais(Transportadora $transportadora, array $filialIds): void
    {
        $filialIds = array_values(array_unique(array_map('intval', $filialIds)));

        if ($filialIds === []) {
            return;
        }

        $validas = Filial::query()
            ->whereIn('id', $filialIds)
            ->where('transportadora_id', $transportadora->id)
            ->where('ativo', true)
            ->count();

        if ($validas !== count($filialIds)) {
            throw ValidationException::withMessages(['filial_ids' => 'Selecione somente filiais ativas da transportadora do vínculo.']);
        }
    }

    private function validarAdministracaoRemovida(UsuarioTransportadora $vinculo, PerfilAcesso $perfil, bool $ativo): void
    {
        if (! $vinculo->exists || ! $vinculo->ativo || ! $vinculo->perfil->podeAdministrarUsuarios()) {
            return;
        }

        if ($ativo && $perfil->podeAdministrarUsuarios()) {
            return;
        }

        $outroAdministrador = UsuarioTransportadora::query()
            ->where('transportadora_id', $vinculo->transportadora_id)
            ->where('ativo', true)
            ->where('perfil', PerfilAcesso::Administrador->value)
            ->whereKeyNot($vinculo->id)
            ->exists();

        if (! $outroAdministrador) {
            throw ValidationException::withMessages(['perfil' => 'A transportadora precisa manter ao menos um administrador ativo.']);
        }
    }
}

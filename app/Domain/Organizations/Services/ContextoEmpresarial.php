<?php

namespace App\Domain\Organizations\Services;

use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ContextoEmpresarial
{
    public const TRANSPORTADORA_SESSION_KEY = 'contexto.transportadora_id';

    public const FILIAL_SESSION_KEY = 'contexto.filial_id';

    /**
     * @return array<int, array{
     *     transportadora: array{id: int, nome: string, grupo: string},
     *     perfil: string,
     *     filiais: array<int, array{id: int, nome: string, cidade: string, uf: string}>
     * }>
     */
    public function opcoes(User $usuario): array
    {
        return UsuarioTransportadora::query()
            ->with([
                'transportadora.grupoEmpresarial',
                'usuarioFiliais.filial',
            ])
            ->where('user_id', $usuario->id)
            ->where('ativo', true)
            ->whereHas('transportadora', fn ($query) => $query->where('ativo', true))
            ->get()
            ->map(function (UsuarioTransportadora $vinculo): ?array {
                $filiais = $vinculo->usuarioFiliais
                    ->pluck('filial')
                    ->filter(fn (?Filial $filial): bool => $filial?->ativo === true)
                    ->map(fn (Filial $filial): array => [
                        'id' => $filial->id,
                        'nome' => $filial->nome,
                        'cidade' => $filial->cidade,
                        'uf' => $filial->uf,
                    ])
                    ->values()
                    ->all();

                if ($filiais === []) {
                    return null;
                }

                return [
                    'transportadora' => [
                        'id' => $vinculo->transportadora->id,
                        'nome' => $vinculo->transportadora->nome_fantasia,
                        'grupo' => $vinculo->transportadora->grupoEmpresarial->nome,
                    ],
                    'perfil' => $vinculo->perfil->value,
                    'filiais' => $filiais,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     transportadora: array{id: int, nome: string, grupo: string},
     *     filial: array{id: int, nome: string, cidade: string, uf: string},
     *     perfil: string
     * }|null
     */
    public function atual(Request $request, User $usuario): ?array
    {
        $transportadoraId = $request->session()->get(self::TRANSPORTADORA_SESSION_KEY);
        $filialId = $request->session()->get(self::FILIAL_SESSION_KEY);

        if (! is_int($transportadoraId) && ! is_numeric($transportadoraId)) {
            return null;
        }

        if (! is_int($filialId) && ! is_numeric($filialId)) {
            return null;
        }

        $vinculo = UsuarioTransportadora::query()
            ->with('transportadora.grupoEmpresarial')
            ->where('user_id', $usuario->id)
            ->where('transportadora_id', (int) $transportadoraId)
            ->where('ativo', true)
            ->whereHas('transportadora', fn ($query) => $query->where('ativo', true))
            ->first();

        $filial = $vinculo?->usuarioFiliais()
            ->with('filial')
            ->where('filial_id', (int) $filialId)
            ->whereHas('filial', fn ($query) => $query->where('ativo', true))
            ->first()?->filial;

        if ($vinculo === null || $filial === null) {
            return null;
        }

        return [
            'transportadora' => [
                'id' => $vinculo->transportadora->id,
                'nome' => $vinculo->transportadora->nome_fantasia,
                'grupo' => $vinculo->transportadora->grupoEmpresarial->nome,
            ],
            'filial' => [
                'id' => $filial->id,
                'nome' => $filial->nome,
                'cidade' => $filial->cidade,
                'uf' => $filial->uf,
            ],
            'perfil' => $vinculo->perfil->value,
        ];
    }

    public function selecionar(Request $request, User $usuario, int $transportadoraId, int $filialId): void
    {
        $vinculo = UsuarioTransportadora::query()
            ->where('user_id', $usuario->id)
            ->where('transportadora_id', $transportadoraId)
            ->where('ativo', true)
            ->whereHas('transportadora', fn ($query) => $query->where('ativo', true))
            ->first();

        $autorizada = $vinculo?->usuarioFiliais()
            ->where('filial_id', $filialId)
            ->whereHas('filial', fn ($query) => $query->where('ativo', true))
            ->exists() ?? false;

        if ($vinculo === null || ! $autorizada) {
            throw ValidationException::withMessages(['filial_id' => 'O contexto selecionado não está autorizado para este usuário.']);
        }

        $request->session()->put([
            self::TRANSPORTADORA_SESSION_KEY => $transportadoraId,
            self::FILIAL_SESSION_KEY => $filialId,
        ]);
    }

    public function limpar(Request $request): void
    {
        $request->session()->forget([
            self::TRANSPORTADORA_SESSION_KEY,
            self::FILIAL_SESSION_KEY,
        ]);
    }

    /**
     * @return array{atual: array<string, mixed>|null, opcoes: array<int, array<string, mixed>>}
     */
    public function dadosCompartilhados(Request $request, ?User $usuario): array
    {
        if ($usuario === null) {
            return ['atual' => null, 'opcoes' => []];
        }

        return [
            'atual' => $this->atual($request, $usuario),
            'opcoes' => $this->opcoes($usuario),
        ];
    }
}

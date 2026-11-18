<?php

namespace App\Domain\LoadingOrders\Services;

use App\Domain\LoadingOrders\Models\OrdemCarregamento;

class MapeamentoOrdem
{
    public function __construct(private CadastroOrdensCarregamento $cadastro) {}

    /** @return array<int, array{range: string, values: array<int, array<int, string|int|float|null>>}> */
    public function valores(OrdemCarregamento $ordem): array
    {
        $ordem->loadMissing(['transportadora', 'filial', 'conjuntoTransporte']);
        $operacionais = $this->cadastro->dadosExportaveis($ordem)['operacionais'];
        $operacionais = [
            'numero' => $ordem->numero,
            'data_emissao' => $ordem->situacao_alterada_em?->toDateString() ?? $ordem->created_at?->toDateString(),
            'situacao' => $ordem->situacao?->value,
            'transportadora' => $ordem->transportadora?->nome_fantasia,
            'filial' => $ordem->filial?->nome,
            ...$operacionais,
            'tipo_composicao' => $ordem->conjuntoTransporte?->tipo_composicao?->value,
            'tipo_carroceria' => $ordem->conjuntoTransporte?->tipo_carroceria?->value,
        ];

        return collect(config('google.sheets.mapping', []))->map(function (string $range, string $campo) use ($operacionais): array {
            $valor = $operacionais[$campo] ?? null;
            $valor = is_array($valor) ? implode(' · ', $valor) : $valor;

            return ['range' => $range, 'values' => [[is_scalar($valor) || $valor === null ? $valor : (string) $valor]]];
        })->values()->all();
    }
}

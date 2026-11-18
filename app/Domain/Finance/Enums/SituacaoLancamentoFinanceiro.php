<?php

namespace App\Domain\Finance\Enums;

enum SituacaoLancamentoFinanceiro: string
{
    case Pendente = 'pendente';
    case Aprovado = 'aprovado';
    case Rejeitado = 'rejeitado';
    case Cancelado = 'cancelado';

    public function nome(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Aprovado => 'Aprovado',
            self::Rejeitado => 'Rejeitado',
            self::Cancelado => 'Cancelado',
        };
    }

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}

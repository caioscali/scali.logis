<?php

namespace App\Domain\FreightContracts\Enums;

enum TipoContratoFrete: string
{
    case Adiantamento = 'adiantamento';
    case Saldo = 'saldo';

    public function nome(): string
    {
        return match ($this) {
            self::Adiantamento => 'CFC — Adiantamento',
            self::Saldo => 'CFC — Saldo',
        };
    }

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}

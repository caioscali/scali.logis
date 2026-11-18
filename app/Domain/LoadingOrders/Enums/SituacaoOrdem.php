<?php

namespace App\Domain\LoadingOrders\Enums;

enum SituacaoOrdem: string
{
    case Rascunho = 'rascunho';
    case Emitida = 'emitida';
    case Cancelada = 'cancelada';

    public function nome(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Emitida => 'Emitida',
            self::Cancelada => 'Cancelada',
        };
    }

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}

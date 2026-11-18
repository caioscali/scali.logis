<?php

namespace App\Domain\FreightContracts\Enums;

enum SituacaoConferencia: string
{
    case Pendente = 'pendente';
    case Divergente = 'divergente';
    case Conferido = 'conferido';

    public function nome(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Divergente => 'Divergente',
            self::Conferido => 'Conferido',
        };
    }
}

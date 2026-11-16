<?php

namespace App\Domain\Registry\Enums;

enum TipoPessoa: string
{
    case Fisica = 'fisica';
    case Juridica = 'juridica';

    public function tamanhoDocumento(): int
    {
        return $this === self::Fisica ? 11 : 14;
    }
}

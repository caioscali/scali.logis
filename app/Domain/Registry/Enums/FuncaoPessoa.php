<?php

namespace App\Domain\Registry\Enums;

enum FuncaoPessoa: string
{
    case Motorista = 'motorista';
    case Transportador = 'transportador';
    case Proprietario = 'proprietario';
    case Cliente = 'cliente';
    case Fornecedor = 'fornecedor';

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}

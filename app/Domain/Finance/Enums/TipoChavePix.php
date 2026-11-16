<?php

namespace App\Domain\Finance\Enums;

enum TipoChavePix: string
{
    case CpfCnpj = 'cpf_cnpj';
    case Telefone = 'telefone';
    case Email = 'email';
    case Aleatoria = 'aleatoria';
}

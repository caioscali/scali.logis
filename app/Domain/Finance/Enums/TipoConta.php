<?php

namespace App\Domain\Finance\Enums;

enum TipoConta: string
{
    case Corrente = 'corrente';
    case Poupanca = 'poupanca';
}

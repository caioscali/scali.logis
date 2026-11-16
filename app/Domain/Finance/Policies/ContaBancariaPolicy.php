<?php

namespace App\Domain\Finance\Policies;

use App\Domain\Finance\Models\ContaBancaria;
use App\Domain\Finance\Services\CadastroContasBancarias;
use App\Models\User;
use Illuminate\Http\Request;

class ContaBancariaPolicy
{
    public function __construct(private CadastroContasBancarias $cadastroContasBancarias) {}

    public function viewAny(User $user): bool
    {
        return $this->cadastroContasBancarias->podeConsultar(app(Request::class), $user);
    }

    public function view(User $user, ContaBancaria $conta): bool
    {
        return $this->cadastroContasBancarias->contaNoContexto(app(Request::class), $user, $conta);
    }

    public function create(User $user): bool
    {
        return $this->cadastroContasBancarias->podeModificar(app(Request::class), $user);
    }

    public function update(User $user, ContaBancaria $conta): bool
    {
        return $this->cadastroContasBancarias->podeModificar(app(Request::class), $user)
            && $this->cadastroContasBancarias->contaNoContexto(app(Request::class), $user, $conta);
    }

    public function delete(User $user, ContaBancaria $conta): bool
    {
        return false;
    }
}

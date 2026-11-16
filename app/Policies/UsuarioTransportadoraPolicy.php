<?php

namespace App\Policies;

use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Domain\Organizations\Services\AcessoEmpresarial;
use App\Models\User;

class UsuarioTransportadoraPolicy
{
    public function __construct(private AcessoEmpresarial $acessoEmpresarial) {}

    public function viewAny(User $user, Transportadora $transportadora): bool
    {
        return $this->acessoEmpresarial->podeAdministrarUsuarios($user, $transportadora);
    }

    public function view(User $user, UsuarioTransportadora $vinculo): bool
    {
        return $this->acessoEmpresarial->podeAdministrarUsuarios($user, $vinculo->transportadora);
    }

    public function create(User $user, Transportadora $transportadora): bool
    {
        return $this->acessoEmpresarial->podeAdministrarUsuarios($user, $transportadora);
    }

    public function update(User $user, UsuarioTransportadora $vinculo): bool
    {
        return $this->acessoEmpresarial->podeAdministrarUsuarios($user, $vinculo->transportadora);
    }

    public function delete(User $user, UsuarioTransportadora $vinculo): bool
    {
        return $this->acessoEmpresarial->podeAdministrarUsuarios($user, $vinculo->transportadora);
    }
}

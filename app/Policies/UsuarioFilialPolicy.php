<?php

namespace App\Policies;

use App\Domain\Organizations\Models\UsuarioFilial;
use App\Domain\Organizations\Services\AcessoEmpresarial;
use App\Models\User;

class UsuarioFilialPolicy
{
    public function __construct(private AcessoEmpresarial $acessoEmpresarial) {}

    public function update(User $user, UsuarioFilial $usuarioFilial): bool
    {
        return $this->acessoEmpresarial->podeAdministrarUsuarios($user, $usuarioFilial->usuarioTransportadora->transportadora);
    }

    public function delete(User $user, UsuarioFilial $usuarioFilial): bool
    {
        return $this->acessoEmpresarial->podeAdministrarUsuarios($user, $usuarioFilial->usuarioTransportadora->transportadora);
    }
}

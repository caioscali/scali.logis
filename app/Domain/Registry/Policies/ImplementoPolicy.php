<?php

namespace App\Domain\Registry\Policies;

use App\Domain\Registry\Models\Implemento;
use App\Domain\Registry\Services\CadastroEquipamentos;
use App\Models\User;
use Illuminate\Http\Request;

class ImplementoPolicy
{
    public function __construct(private CadastroEquipamentos $cadastroEquipamentos) {}

    public function viewAny(User $user): bool
    {
        return $this->cadastroEquipamentos->podeConsultar(app(Request::class), $user);
    }

    public function view(User $user, Implemento $implemento): bool
    {
        return $this->cadastroEquipamentos->implementoNoContexto(app(Request::class), $user, $implemento);
    }

    public function create(User $user): bool
    {
        return $this->cadastroEquipamentos->podeModificar(app(Request::class), $user);
    }

    public function update(User $user, Implemento $implemento): bool
    {
        return $this->cadastroEquipamentos->podeModificar(app(Request::class), $user)
            && $this->cadastroEquipamentos->implementoNoContexto(app(Request::class), $user, $implemento);
    }

    public function delete(User $user, Implemento $implemento): bool
    {
        return false;
    }
}

<?php

namespace App\Domain\Registry\Policies;

use App\Domain\Registry\Models\ConjuntoTransporte;
use App\Domain\Registry\Services\CadastroConjuntos;
use App\Models\User;
use Illuminate\Http\Request;

class ConjuntoTransportePolicy
{
    public function __construct(private CadastroConjuntos $cadastroConjuntos) {}

    public function viewAny(User $user): bool
    {
        return $this->cadastroConjuntos->podeConsultar(app(Request::class), $user);
    }

    public function view(User $user, ConjuntoTransporte $conjunto): bool
    {
        return $this->cadastroConjuntos->pertenceAoContexto(app(Request::class), $user, $conjunto);
    }

    public function create(User $user): bool
    {
        return $this->cadastroConjuntos->podeModificar(app(Request::class), $user);
    }

    public function update(User $user, ConjuntoTransporte $conjunto): bool
    {
        return $this->cadastroConjuntos->podeModificar(app(Request::class), $user)
            && $this->cadastroConjuntos->pertenceAoContexto(app(Request::class), $user, $conjunto);
    }

    public function delete(User $user, ConjuntoTransporte $conjunto): bool
    {
        return false;
    }
}

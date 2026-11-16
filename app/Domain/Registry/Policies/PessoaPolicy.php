<?php

namespace App\Domain\Registry\Policies;

use App\Domain\Registry\Models\Pessoa;
use App\Domain\Registry\Services\CadastroPessoas;
use App\Models\User;
use Illuminate\Http\Request;

class PessoaPolicy
{
    public function __construct(private CadastroPessoas $cadastroPessoas) {}

    public function viewAny(User $user): bool
    {
        return $this->cadastroPessoas->podeConsultar(app(Request::class), $user);
    }

    public function view(User $user, Pessoa $pessoa): bool
    {
        return $this->cadastroPessoas->podeConsultar(app(Request::class), $user)
            && $this->cadastroPessoas->pessoaNoContexto(app(Request::class), $user, $pessoa);
    }

    public function create(User $user): bool
    {
        return $this->cadastroPessoas->podeModificar(app(Request::class), $user);
    }

    public function update(User $user, Pessoa $pessoa): bool
    {
        return $this->cadastroPessoas->podeModificar(app(Request::class), $user)
            && $this->cadastroPessoas->pessoaNoContexto(app(Request::class), $user, $pessoa);
    }

    public function delete(User $user, Pessoa $pessoa): bool
    {
        return false;
    }
}

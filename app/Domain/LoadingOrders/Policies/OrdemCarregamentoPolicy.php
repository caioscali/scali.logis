<?php

namespace App\Domain\LoadingOrders\Policies;

use App\Domain\LoadingOrders\Enums\SituacaoOrdem;
use App\Domain\LoadingOrders\Models\OrdemCarregamento;
use App\Domain\LoadingOrders\Services\CadastroOrdensCarregamento;
use App\Models\User;
use Illuminate\Http\Request;

class OrdemCarregamentoPolicy
{
    public function __construct(private CadastroOrdensCarregamento $cadastro) {}

    public function viewAny(User $user): bool
    {
        return $this->cadastro->podeConsultar(app(Request::class), $user);
    }

    public function view(User $user, OrdemCarregamento $ordem): bool
    {
        return $this->cadastro->pertenceAoContexto(app(Request::class), $user, $ordem);
    }

    public function create(User $user): bool
    {
        return $this->cadastro->podeCriar(app(Request::class), $user);
    }

    public function update(User $user, OrdemCarregamento $ordem): bool
    {
        return $this->cadastro->podeCriar(app(Request::class), $user) && $this->cadastro->pertenceAoContexto(app(Request::class), $user, $ordem);
    }

    public function emit(User $user, OrdemCarregamento $ordem): bool
    {
        return $this->update($user, $ordem);
    }

    public function cancel(User $user, OrdemCarregamento $ordem): bool
    {
        return $this->cadastro->podeCancelar(app(Request::class), $user) && $this->cadastro->pertenceAoContexto(app(Request::class), $user, $ordem);
    }

    public function delete(User $user, OrdemCarregamento $ordem): bool
    {
        return false;
    }

    public function generatePdf(User $user, OrdemCarregamento $ordem): bool
    {
        return $this->cadastro->podeCriar(app(Request::class), $user)
            && $this->cadastro->pertenceAoContexto(app(Request::class), $user, $ordem)
            && $ordem->situacao === SituacaoOrdem::Emitida;
    }

    public function downloadPdf(User $user, OrdemCarregamento $ordem): bool
    {
        return $this->cadastro->podeConsultar(app(Request::class), $user)
            && $this->cadastro->pertenceAoContexto(app(Request::class), $user, $ordem)
            && $ordem->pdf_caminho !== null;
    }
}

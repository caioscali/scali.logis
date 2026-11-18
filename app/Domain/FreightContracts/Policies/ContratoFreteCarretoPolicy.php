<?php

namespace App\Domain\FreightContracts\Policies;

use App\Domain\FreightContracts\Models\ContratoFreteCarreto;
use App\Domain\FreightContracts\Services\CadastroContratosFreteCarreto;
use App\Domain\LoadingOrders\Models\OrdemCarregamento;
use App\Models\User;
use Illuminate\Http\Request;

class ContratoFreteCarretoPolicy
{
    public function __construct(private CadastroContratosFreteCarreto $cadastro) {}

    public function view(User $user, OrdemCarregamento $ordem): bool
    {
        return $this->cadastro->podeConsultar(app(Request::class), $user, $ordem);
    }

    public function create(User $user, OrdemCarregamento $ordem): bool
    {
        return $this->cadastro->podeRegistrar(app(Request::class), $user, $ordem);
    }

    public function update(User $user, ContratoFreteCarreto $contrato, OrdemCarregamento $ordem): bool
    {
        return $this->cadastro->podeRegistrar(app(Request::class), $user, $ordem)
            && $contrato->ordem_carregamento_id === $ordem->id
            && $contrato->transportadora_id === $ordem->transportadora_id
            && $contrato->filial_id === $ordem->filial_id;
    }

    public function confirm(User $user, ContratoFreteCarreto $contrato, OrdemCarregamento $ordem): bool
    {
        return $this->cadastro->podeConferir(app(Request::class), $user, $ordem)
            && $contrato->ordem_carregamento_id === $ordem->id
            && $contrato->transportadora_id === $ordem->transportadora_id
            && $contrato->filial_id === $ordem->filial_id;
    }
}

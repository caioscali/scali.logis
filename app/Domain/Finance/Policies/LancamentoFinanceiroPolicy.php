<?php

namespace App\Domain\Finance\Policies;

use App\Domain\Finance\Models\LancamentoFinanceiro;
use App\Domain\Finance\Services\CadastroLancamentosFinanceiros;
use App\Domain\Organizations\Services\ContextoEmpresarial;
use App\Models\User;
use Illuminate\Http\Request;

class LancamentoFinanceiroPolicy
{
    public function __construct(private CadastroLancamentosFinanceiros $cadastro) {}

    public function viewAny(User $user): bool
    {
        return $this->cadastro->podeConsultar(app(Request::class), $user);
    }

    public function view(User $user, LancamentoFinanceiro $lancamento): bool
    {
        return $this->cadastro->podeConsultar(app(Request::class), $user)
            && $this->cadastroNoContexto(app(Request::class), $user, $lancamento);
    }

    public function approve(User $user, LancamentoFinanceiro $lancamento): bool
    {
        return $this->cadastro->podeAprovar(app(Request::class), $user)
            && $this->cadastroNoContexto(app(Request::class), $user, $lancamento);
    }

    public function reject(User $user, LancamentoFinanceiro $lancamento): bool
    {
        return $this->approve($user, $lancamento);
    }

    public function adjustCompetencia(User $user, LancamentoFinanceiro $lancamento): bool
    {
        return $this->approve($user, $lancamento);
    }

    private function cadastroNoContexto(Request $request, User $user, LancamentoFinanceiro $lancamento): bool
    {
        $contexto = app(ContextoEmpresarial::class)->atual($request, $user);

        return $contexto !== null
            && $lancamento->transportadora_id === $contexto['transportadora']['id']
            && $lancamento->filial_id === $contexto['filial']['id'];
    }
}

<?php

namespace App\Domain\Finance\Http\Controllers;

use App\Domain\Finance\Http\Requests\AdjustCompetenciaLancamentoRequest;
use App\Domain\Finance\Http\Requests\RejectLancamentoFinanceiroRequest;
use App\Domain\Finance\Models\LancamentoFinanceiro;
use App\Domain\Finance\Services\CadastroLancamentosFinanceiros;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LancamentoFinanceiroController extends Controller
{
    public function __construct(private CadastroLancamentosFinanceiros $cadastro) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', LancamentoFinanceiro::class);

        return Inertia::render('Finance/Entries/Index', $this->cadastro->listar($request, $request->user()));
    }

    public function show(Request $request, LancamentoFinanceiro $lancamento): Response
    {
        Gate::authorize('view', $lancamento);

        return Inertia::render('Finance/Entries/Show', ['lancamento' => $this->cadastro->obter($request, $request->user(), $lancamento), 'pode_aprovar' => Gate::forUser($request->user())->allows('approve', $lancamento)]);
    }

    public function approve(Request $request, LancamentoFinanceiro $lancamento): RedirectResponse
    {
        Gate::authorize('approve', $lancamento);
        $this->cadastro->aprovar($request, $request->user(), $lancamento);

        return back()->with('success', 'Lançamento aprovado.');
    }

    public function reject(RejectLancamentoFinanceiroRequest $request, LancamentoFinanceiro $lancamento): RedirectResponse
    {
        Gate::authorize('reject', $lancamento);
        $this->cadastro->rejeitar($request, $request->user(), $lancamento, $request->validated('motivo_rejeicao'));

        return back()->with('success', 'Lançamento rejeitado.');
    }

    public function adjustCompetencia(AdjustCompetenciaLancamentoRequest $request, LancamentoFinanceiro $lancamento): RedirectResponse
    {
        Gate::authorize('adjustCompetencia', $lancamento);
        $this->cadastro->ajustarCompetencia($request, $request->user(), $lancamento, $request->validated('competencia'), $request->validated('motivo'));

        return back()->with('success', 'Competência ajustada com histórico.');
    }
}

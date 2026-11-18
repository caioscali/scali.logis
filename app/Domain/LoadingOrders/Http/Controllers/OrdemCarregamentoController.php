<?php

namespace App\Domain\LoadingOrders\Http\Controllers;

use App\Domain\LoadingOrders\Http\Requests\StoreOrdemCarregamentoRequest;
use App\Domain\LoadingOrders\Http\Requests\UpdateOrdemCarregamentoRequest;
use App\Domain\LoadingOrders\Models\OrdemCarregamento;
use App\Domain\LoadingOrders\Services\CadastroOrdensCarregamento;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrdemCarregamentoController extends Controller
{
    public function __construct(private CadastroOrdensCarregamento $cadastro) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', OrdemCarregamento::class);

        return Inertia::render('LoadingOrders/Index', $this->cadastro->listar($request, $request->user()));
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', OrdemCarregamento::class);

        return Inertia::render('LoadingOrders/Form', $this->cadastro->opcoes($request, $request->user()));
    }

    public function store(StoreOrdemCarregamentoRequest $request): RedirectResponse
    {
        $ordem = $this->cadastro->criar($request, $request->user(), $request->validated());

        return to_route('ordens-carregamento.show', $ordem)->with('success', 'Rascunho criado.');
    }

    public function edit(Request $request, OrdemCarregamento $ordem): Response
    {
        Gate::authorize('update', $ordem);
        $ordem = $this->cadastro->obter($request, $request->user(), $ordem);

        return Inertia::render('LoadingOrders/Form', [...$this->cadastro->opcoes($request, $request->user()), 'ordem' => $ordem]);
    }

    public function update(UpdateOrdemCarregamentoRequest $request, OrdemCarregamento $ordem): RedirectResponse
    {
        Gate::authorize('update', $ordem);
        $ordem = $this->cadastro->atualizar($request, $request->user(), $ordem, $request->validated());

        return to_route('ordens-carregamento.show', $ordem)->with('success', 'Rascunho atualizado.');
    }

    public function show(Request $request, OrdemCarregamento $ordem): Response
    {
        Gate::authorize('view', $ordem);
        $ordem = $this->cadastro->obter($request, $request->user(), $ordem);

        return Inertia::render('LoadingOrders/Show', ['ordem' => $ordem, 'pode_emitir' => $this->cadastro->podeEmitir($request, $request->user()), 'pode_cancelar' => $this->cadastro->podeCancelar($request, $request->user())]);
    }

    public function emit(Request $request, OrdemCarregamento $ordem): RedirectResponse
    {
        Gate::authorize('emit', $ordem);
        $this->cadastro->emitir($request, $request->user(), $ordem);

        return back()->with('success', 'Ordem emitida.');
    }

    public function cancel(Request $request, OrdemCarregamento $ordem): RedirectResponse
    {
        Gate::authorize('cancel', $ordem);
        $this->cadastro->cancelar($request, $request->user(), $ordem);

        return back()->with('success', 'Ordem cancelada.');
    }
}

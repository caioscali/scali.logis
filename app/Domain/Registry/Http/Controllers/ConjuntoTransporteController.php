<?php

namespace App\Domain\Registry\Http\Controllers;

use App\Domain\Registry\Http\Requests\StoreConjuntoTransporteRequest;
use App\Domain\Registry\Http\Requests\UpdateConjuntoTransporteRequest;
use App\Domain\Registry\Models\ConjuntoTransporte;
use App\Domain\Registry\Services\CadastroConjuntos;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ConjuntoTransporteController extends Controller
{
    public function __construct(private CadastroConjuntos $cadastroConjuntos) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ConjuntoTransporte::class);

        return Inertia::render('Registry/TransportSets/Index', $this->cadastroConjuntos->listar($request, $request->user()));
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', ConjuntoTransporte::class);
        $dados = $this->cadastroConjuntos->opcoes($request, $request->user());

        if ($request->filled('origem')) {
            $dados['valores'] = $this->cadastroConjuntos->dadosParaDuplicar($request, $request->user(), ConjuntoTransporte::query()->findOrFail((int) $request->integer('origem')));
        }

        return Inertia::render('Registry/TransportSets/Form', $dados);
    }

    public function store(StoreConjuntoTransporteRequest $request): RedirectResponse
    {
        $conjunto = $this->cadastroConjuntos->criar($request, $request->user(), $request->validated());

        return to_route('conjuntos-transporte.show', $conjunto)->with('success', 'Conjunto de transporte criado sem alterar configurações anteriores.');
    }

    public function show(Request $request, ConjuntoTransporte $conjunto): Response
    {
        Gate::authorize('view', $conjunto);

        return Inertia::render('Registry/TransportSets/Show', ['conjunto' => $this->cadastroConjuntos->obter($request, $request->user(), $conjunto)]);
    }

    public function update(UpdateConjuntoTransporteRequest $request, ConjuntoTransporte $conjunto): RedirectResponse
    {
        Gate::authorize('update', $conjunto);
        $conjunto = $this->cadastroConjuntos->atualizarSituacao($request, $request->user(), $conjunto, (bool) $request->validated('ativo'));

        return to_route('conjuntos-transporte.show', $conjunto)->with('success', 'Situação do conjunto atualizada.');
    }
}

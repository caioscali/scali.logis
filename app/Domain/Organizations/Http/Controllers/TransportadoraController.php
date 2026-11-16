<?php

namespace App\Domain\Organizations\Http\Controllers;

use App\Domain\Organizations\Http\Requests\StoreTransportadoraRequest;
use App\Domain\Organizations\Http\Requests\UpdateTransportadoraRequest;
use App\Domain\Organizations\Models\GrupoEmpresarial;
use App\Domain\Organizations\Models\Transportadora;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TransportadoraController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('Organizations/Carriers/Index', [
            'transportadoras' => Transportadora::query()->with('grupoEmpresarial')->withCount('filiais')->orderBy('nome_fantasia')->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Organizations/Carriers/Form', ['transportadora' => null, 'grupos' => GrupoEmpresarial::query()->where('ativo', true)->orderBy('nome')->get()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTransportadoraRequest $request): RedirectResponse
    {
        $transportadora = DB::transaction(function () use ($request): Transportadora {
            $transportadora = Transportadora::create($request->safe()->except('filial_matriz'));
            $transportadora->filiais()->create([
                ...$request->validated('filial_matriz'),
                'matriz' => true,
                'ativo' => true,
            ]);

            return $transportadora;
        });

        return to_route('admin.transportadoras.show', $transportadora)->with('success', 'Transportadora criada com filial matriz.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Transportadora $transportadora): Response
    {
        return Inertia::render('Organizations/Carriers/Show', [
            'transportadora' => $transportadora->load('grupoEmpresarial', 'filiais'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Transportadora $transportadora): Response
    {
        return Inertia::render('Organizations/Carriers/Form', ['transportadora' => $transportadora, 'grupos' => GrupoEmpresarial::query()->where('ativo', true)->orderBy('nome')->get()]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTransportadoraRequest $request, Transportadora $transportadora): RedirectResponse
    {
        $transportadora->update($request->validated());

        return to_route('admin.transportadoras.show', $transportadora)->with('success', 'Transportadora atualizada.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Transportadora $transportadora): RedirectResponse
    {
        if ($transportadora->filiais()->exists()) {
            return back()->withErrors(['transportadora' => 'Não é possível excluir uma transportadora com filiais vinculadas.']);
        }

        $transportadora->delete();

        return to_route('admin.transportadoras.index')->with('success', 'Transportadora excluída.');
    }
}

<?php

namespace App\Domain\Organizations\Http\Controllers;

use App\Domain\Organizations\Http\Requests\StoreFilialRequest;
use App\Domain\Organizations\Http\Requests\UpdateFilialRequest;
use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\Transportadora;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FilialController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Transportadora $transportadora): Response
    {
        return Inertia::render('Organizations/Branches/Index', [
            'transportadora' => $transportadora->load('grupoEmpresarial'),
            'filiais' => $transportadora->filiais()->orderByDesc('matriz')->orderBy('nome')->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Transportadora $transportadora): Response
    {
        return Inertia::render('Organizations/Branches/Form', ['transportadora' => $transportadora, 'filial' => null]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFilialRequest $request, Transportadora $transportadora): RedirectResponse
    {
        $transportadora->filiais()->create($request->validated());

        return to_route('admin.transportadoras.filiais.index', $transportadora)->with('success', 'Filial criada.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Transportadora $transportadora, Filial $filial): Response
    {
        abort_unless($filial->transportadora_id === $transportadora->id, 404);

        return Inertia::render('Organizations/Branches/Show', ['transportadora' => $transportadora, 'filial' => $filial]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Transportadora $transportadora, Filial $filial): Response
    {
        abort_unless($filial->transportadora_id === $transportadora->id, 404);

        return Inertia::render('Organizations/Branches/Form', ['transportadora' => $transportadora, 'filial' => $filial]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFilialRequest $request, Transportadora $transportadora, Filial $filial): RedirectResponse
    {
        abort_unless($filial->transportadora_id === $transportadora->id, 404);
        $filial->update($request->validated());

        return to_route('admin.transportadoras.filiais.index', $transportadora)->with('success', 'Filial atualizada.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Transportadora $transportadora, Filial $filial): RedirectResponse
    {
        abort_unless($filial->transportadora_id === $transportadora->id, 404);

        if ($filial->matriz) {
            return back()->withErrors(['filial' => 'A filial matriz não pode ser excluída.']);
        }

        $filial->delete();

        return to_route('admin.transportadoras.filiais.index', $transportadora)->with('success', 'Filial excluída.');
    }
}

<?php

namespace App\Domain\Organizations\Http\Controllers;

use App\Domain\Organizations\Http\Requests\StoreGrupoEmpresarialRequest;
use App\Domain\Organizations\Http\Requests\UpdateGrupoEmpresarialRequest;
use App\Domain\Organizations\Models\GrupoEmpresarial;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GrupoEmpresarialController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('Organizations/Groups/Index', [
            'grupos' => GrupoEmpresarial::query()->withCount('transportadoras')->orderBy('nome')->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Organizations/Groups/Form', ['grupo' => null]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGrupoEmpresarialRequest $request): RedirectResponse
    {
        GrupoEmpresarial::create($request->validated());

        return to_route('admin.grupos-empresariais.index')->with('success', 'Grupo empresarial criado.');
    }

    /**
     * Display the specified resource.
     */
    public function show(GrupoEmpresarial $grupoEmpresarial): Response
    {
        return Inertia::render('Organizations/Groups/Show', ['grupo' => $grupoEmpresarial->load('transportadoras')]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(GrupoEmpresarial $grupoEmpresarial): Response
    {
        return Inertia::render('Organizations/Groups/Form', ['grupo' => $grupoEmpresarial]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGrupoEmpresarialRequest $request, GrupoEmpresarial $grupoEmpresarial): RedirectResponse
    {
        $grupoEmpresarial->update($request->validated());

        return to_route('admin.grupos-empresariais.index')->with('success', 'Grupo empresarial atualizado.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GrupoEmpresarial $grupoEmpresarial): RedirectResponse
    {
        if ($grupoEmpresarial->transportadoras()->exists()) {
            return back()->withErrors(['grupo' => 'Não é possível excluir um grupo com transportadoras vinculadas.']);
        }

        $grupoEmpresarial->delete();

        return to_route('admin.grupos-empresariais.index')->with('success', 'Grupo empresarial excluído.');
    }
}

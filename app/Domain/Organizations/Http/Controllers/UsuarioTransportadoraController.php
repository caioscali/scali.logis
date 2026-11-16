<?php

namespace App\Domain\Organizations\Http\Controllers;

use App\Domain\Organizations\Enums\PerfilAcesso;
use App\Domain\Organizations\Http\Requests\StoreUsuarioTransportadoraRequest;
use App\Domain\Organizations\Http\Requests\UpdateUsuarioTransportadoraRequest;
use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Domain\Organizations\Services\AcessoEmpresarial;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioTransportadoraController extends Controller
{
    public function __construct(private AcessoEmpresarial $acessoEmpresarial) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, Transportadora $transportadora): Response
    {
        Gate::authorize('viewAny', [UsuarioTransportadora::class, $transportadora]);
        $busca = trim((string) $request->string('busca'));
        $vinculos = UsuarioTransportadora::query()
            ->with(['usuario', 'usuarioFiliais.filial'])
            ->where('transportadora_id', $transportadora->id)
            ->when($busca !== '', fn ($query) => $query->whereHas('usuario', fn ($userQuery) => $userQuery
                ->where('name', 'like', "%{$busca}%")
                ->orWhere('email', 'like', "%{$busca}%")))
            ->orderByDesc('ativo')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Organizations/Users/Index', [
            'transportadora' => $transportadora,
            'vinculos' => $vinculos,
            'busca' => $busca,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Transportadora $transportadora): Response
    {
        Gate::authorize('create', [UsuarioTransportadora::class, $transportadora]);

        return Inertia::render('Organizations/Users/Form', [
            'transportadora' => $transportadora,
            'vinculo' => null,
            'filiais' => Filial::query()->where('transportadora_id', $transportadora->id)->where('ativo', true)->orderBy('nome')->get(),
            'perfis' => $this->perfis(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUsuarioTransportadoraRequest $request, Transportadora $transportadora): RedirectResponse
    {
        $usuario = User::query()->where('email', $request->validated('email'))->firstOrFail();
        $dados = $request->validated();
        $vinculo = $this->acessoEmpresarial->salvarVinculo(
            $usuario,
            $transportadora,
            PerfilAcesso::from($dados['perfil']),
            (bool) ($dados['ativo'] ?? true),
            array_map('intval', $dados['filial_ids'] ?? []),
            null,
            $request->user(),
        );

        return to_route('admin.transportadoras.usuarios.show', [$transportadora, $vinculo])->with('success', 'Vínculo criado.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Transportadora $transportadora, UsuarioTransportadora $usuarioTransportadora): Response
    {
        abort_unless($usuarioTransportadora->transportadora_id === $transportadora->id, 404);
        Gate::authorize('view', $usuarioTransportadora);

        return Inertia::render('Organizations/Users/Show', [
            'transportadora' => $transportadora,
            'vinculo' => $usuarioTransportadora->load(['usuario', 'usuarioFiliais.filial']),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Transportadora $transportadora, UsuarioTransportadora $usuarioTransportadora): Response
    {
        abort_unless($usuarioTransportadora->transportadora_id === $transportadora->id, 404);
        Gate::authorize('update', $usuarioTransportadora);

        return Inertia::render('Organizations/Users/Form', [
            'transportadora' => $transportadora,
            'vinculo' => $usuarioTransportadora->load('usuarioFiliais'),
            'filiais' => Filial::query()->where('transportadora_id', $transportadora->id)->where('ativo', true)->orderBy('nome')->get(),
            'perfis' => $this->perfis(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUsuarioTransportadoraRequest $request, Transportadora $transportadora, UsuarioTransportadora $usuarioTransportadora): RedirectResponse
    {
        abort_unless($usuarioTransportadora->transportadora_id === $transportadora->id, 404);
        Gate::authorize('update', $usuarioTransportadora);
        $dados = $request->validated();
        $vinculo = $this->acessoEmpresarial->salvarVinculo(
            $usuarioTransportadora->usuario,
            $transportadora,
            PerfilAcesso::from($dados['perfil']),
            (bool) $dados['ativo'],
            array_map('intval', $dados['filial_ids'] ?? []),
            $usuarioTransportadora,
            $request->user(),
        );

        return to_route('admin.transportadoras.usuarios.show', [$transportadora, $vinculo])->with('success', 'Vínculo atualizado.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Transportadora $transportadora, UsuarioTransportadora $usuarioTransportadora): RedirectResponse
    {
        abort_unless($usuarioTransportadora->transportadora_id === $transportadora->id, 404);
        Gate::authorize('delete', $usuarioTransportadora);
        $this->acessoEmpresarial->desativarVinculo($usuarioTransportadora, $request->user());

        return to_route('admin.transportadoras.usuarios.index', $transportadora)->with('success', 'Vínculo desativado.');
    }

    /**
     * @return array<int, array{value: string, name: string}>
     */
    private function perfis(): array
    {
        return array_map(
            fn (PerfilAcesso $perfil): array => ['value' => $perfil->value, 'name' => ucfirst($perfil->value)],
            PerfilAcesso::cases(),
        );
    }
}

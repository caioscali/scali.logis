<?php

namespace App\Domain\Registry\Http\Controllers;

use App\Domain\Registry\Enums\TipoEquipamento;
use App\Domain\Registry\Http\Requests\PesquisarEquipamentoRequest;
use App\Domain\Registry\Http\Requests\StoreEquipamentoRequest;
use App\Domain\Registry\Http\Requests\UpdateEquipamentoRequest;
use App\Domain\Registry\Models\Implemento;
use App\Domain\Registry\Models\TransportadoraImplemento;
use App\Domain\Registry\Models\TransportadoraVeiculo;
use App\Domain\Registry\Models\Veiculo;
use App\Domain\Registry\Services\CadastroEquipamentos;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EquipamentoController extends Controller
{
    public function __construct(private CadastroEquipamentos $cadastroEquipamentos) {}

    public function veiculos(Request $request): Response
    {
        Gate::authorize('viewAny', Veiculo::class);

        return Inertia::render('Registry/Equipment/Index', [
            ...$this->cadastroEquipamentos->listarVeiculos($request, $request->user()),
            'tipo' => TipoEquipamento::Veiculo->value,
            'titulo' => 'Veículos',
        ]);
    }

    public function criarVeiculo(): Response
    {
        Gate::authorize('create', Veiculo::class);

        return Inertia::render('Registry/Equipment/Form', ['tipo' => TipoEquipamento::Veiculo->value, 'titulo' => 'Veículo']);
    }

    public function armazenarVeiculo(StoreEquipamentoRequest $request): RedirectResponse
    {
        $vinculo = $this->cadastroEquipamentos->adotarVeiculo($request, $request->user(), $request->validated());

        return to_route('veiculos.show', $vinculo->veiculo)->with('success', 'Veículo adotado na transportadora ativa.');
    }

    public function pesquisarVeiculo(PesquisarEquipamentoRequest $request): JsonResponse
    {
        return response()->json($this->cadastroEquipamentos->pesquisar($request, $request->user(), TipoEquipamento::Veiculo, $request->validated('placa')));
    }

    public function mostrarVeiculo(Request $request, Veiculo $veiculo): Response
    {
        Gate::authorize('view', $veiculo);
        $vinculo = $this->cadastroEquipamentos->obterVeiculo($request, $request->user(), $veiculo);

        return Inertia::render('Registry/Equipment/Show', ['tipo' => 'veiculo', 'titulo' => 'Veículo', 'equipamento' => $vinculo->load('veiculo')]);
    }

    public function editarVeiculo(Request $request, Veiculo $veiculo): Response
    {
        Gate::authorize('update', $veiculo);
        $vinculo = $this->cadastroEquipamentos->obterVeiculo($request, $request->user(), $veiculo);

        return Inertia::render('Registry/Equipment/Form', ['tipo' => 'veiculo', 'titulo' => 'Veículo', 'equipamento' => $vinculo->load('veiculo')]);
    }

    public function atualizarVeiculo(UpdateEquipamentoRequest $request, TransportadoraVeiculo $transportadoraVeiculo): RedirectResponse
    {
        $vinculo = $this->cadastroEquipamentos->atualizarVeiculo($request, $request->user(), $transportadoraVeiculo, (bool) $request->validated('ativo'));

        return to_route('veiculos.show', $vinculo->veiculo)->with('success', 'Situação do vínculo atualizada.');
    }

    public function implementos(Request $request): Response
    {
        Gate::authorize('viewAny', Implemento::class);

        return Inertia::render('Registry/Equipment/Index', [
            ...$this->cadastroEquipamentos->listarImplementos($request, $request->user()),
            'tipo' => TipoEquipamento::Implemento->value,
            'titulo' => 'Implementos',
        ]);
    }

    public function criarImplemento(): Response
    {
        Gate::authorize('create', Implemento::class);

        return Inertia::render('Registry/Equipment/Form', ['tipo' => TipoEquipamento::Implemento->value, 'titulo' => 'Implemento']);
    }

    public function armazenarImplemento(StoreEquipamentoRequest $request): RedirectResponse
    {
        $vinculo = $this->cadastroEquipamentos->adotarImplemento($request, $request->user(), $request->validated());

        return to_route('implementos.show', $vinculo->implemento)->with('success', 'Implemento adotado na transportadora ativa.');
    }

    public function pesquisarImplemento(PesquisarEquipamentoRequest $request): JsonResponse
    {
        return response()->json($this->cadastroEquipamentos->pesquisar($request, $request->user(), TipoEquipamento::Implemento, $request->validated('placa')));
    }

    public function mostrarImplemento(Request $request, Implemento $implemento): Response
    {
        Gate::authorize('view', $implemento);
        $vinculo = $this->cadastroEquipamentos->obterImplemento($request, $request->user(), $implemento);

        return Inertia::render('Registry/Equipment/Show', ['tipo' => 'implemento', 'titulo' => 'Implemento', 'equipamento' => $vinculo->load('implemento')]);
    }

    public function editarImplemento(Request $request, Implemento $implemento): Response
    {
        Gate::authorize('update', $implemento);
        $vinculo = $this->cadastroEquipamentos->obterImplemento($request, $request->user(), $implemento);

        return Inertia::render('Registry/Equipment/Form', ['tipo' => 'implemento', 'titulo' => 'Implemento', 'equipamento' => $vinculo->load('implemento')]);
    }

    public function atualizarImplemento(UpdateEquipamentoRequest $request, TransportadoraImplemento $transportadoraImplemento): RedirectResponse
    {
        $vinculo = $this->cadastroEquipamentos->atualizarImplemento($request, $request->user(), $transportadoraImplemento, (bool) $request->validated('ativo'));

        return to_route('implementos.show', $vinculo->implemento)->with('success', 'Situação do vínculo atualizada.');
    }
}

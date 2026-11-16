<?php

namespace App\Domain\Registry\Http\Controllers;

use App\Domain\Registry\Enums\FuncaoPessoa;
use App\Domain\Registry\Enums\TipoPessoa;
use App\Domain\Registry\Http\Requests\PesquisarPessoaRequest;
use App\Domain\Registry\Http\Requests\StorePessoaRequest;
use App\Domain\Registry\Http\Requests\UpdatePessoaRequest;
use App\Domain\Registry\Models\Pessoa;
use App\Domain\Registry\Models\TransportadoraPessoa;
use App\Domain\Registry\Services\CadastroPessoas;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PessoaController extends Controller
{
    public function __construct(private CadastroPessoas $cadastroPessoas) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Pessoa::class);

        return Inertia::render('Registry/People/Index', $this->cadastroPessoas->listar($request, $request->user()));
    }

    public function create(): Response
    {
        Gate::authorize('create', Pessoa::class);

        return Inertia::render('Registry/People/Form', [
            'pessoa' => null,
            'tipos' => array_map(fn (TipoPessoa $tipo): array => ['value' => $tipo->value, 'nome' => $tipo === TipoPessoa::Fisica ? 'Pessoa física' : 'Pessoa jurídica'], TipoPessoa::cases()),
            'funcoes' => array_map(fn (FuncaoPessoa $funcao): array => ['value' => $funcao->value, 'nome' => ucfirst($funcao->value)], FuncaoPessoa::cases()),
        ]);
    }

    public function store(StorePessoaRequest $request): RedirectResponse
    {
        $vinculo = $this->cadastroPessoas->criar($request, $request->user(), $request->validated());

        return to_route('pessoas.show', $vinculo->pessoa)->with('success', 'Pessoa adotada na transportadora ativa.');
    }

    public function pesquisar(PesquisarPessoaRequest $request): JsonResponse
    {
        $dados = $request->validated();
        $tipo = TipoPessoa::from($dados['tipo']);

        return response()->json($this->cadastroPessoas->pesquisar($request, $request->user(), $tipo, $dados['documento']));
    }

    public function show(Request $request, Pessoa $pessoa): Response
    {
        Gate::authorize('view', $pessoa);
        $vinculo = $this->cadastroPessoas->obter($request, $request->user(), $pessoa);

        return Inertia::render('Registry/People/Show', [
            'pessoa' => $vinculo->load(['pessoa', 'funcoes']),
        ]);
    }

    public function edit(Request $request, Pessoa $pessoa): Response
    {
        Gate::authorize('update', $pessoa);
        $vinculo = $this->cadastroPessoas->obter($request, $request->user(), $pessoa);

        return Inertia::render('Registry/People/Form', [
            'pessoa' => [
                'vinculo_id' => $vinculo->id,
                'id' => $pessoa->id,
                'tipo' => $pessoa->tipo->value,
                'nome' => $pessoa->nome,
                'documento' => $pessoa->documento,
                'telefone' => $pessoa->telefone,
                'email' => $pessoa->email,
                'cidade' => $pessoa->cidade,
                'uf' => $pessoa->uf,
                'ativo' => $vinculo->ativo,
                'funcoes' => $vinculo->funcoes->pluck('funcao')->map(fn (FuncaoPessoa $funcao): string => $funcao->value)->values()->all(),
            ],
            'tipos' => array_map(fn (TipoPessoa $tipo): array => ['value' => $tipo->value, 'nome' => $tipo === TipoPessoa::Fisica ? 'Pessoa física' : 'Pessoa jurídica'], TipoPessoa::cases()),
            'funcoes' => array_map(fn (FuncaoPessoa $funcao): array => ['value' => $funcao->value, 'nome' => ucfirst($funcao->value)], FuncaoPessoa::cases()),
        ]);
    }

    public function update(UpdatePessoaRequest $request, TransportadoraPessoa $transportadoraPessoa): RedirectResponse
    {
        $vinculo = $this->cadastroPessoas->atualizar($request, $request->user(), $transportadoraPessoa, $request->validated());

        return to_route('pessoas.show', $vinculo->pessoa)->with('success', 'Vínculo e funções atualizados.');
    }
}

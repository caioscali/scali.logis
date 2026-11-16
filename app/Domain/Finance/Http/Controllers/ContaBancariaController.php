<?php

namespace App\Domain\Finance\Http\Controllers;

use App\Domain\Finance\Http\Requests\PesquisarContasPessoaRequest;
use App\Domain\Finance\Http\Requests\StoreContaBancariaRequest;
use App\Domain\Finance\Http\Requests\UpdateContaBancariaRequest;
use App\Domain\Finance\Models\ContaBancaria;
use App\Domain\Finance\Models\TransportadoraContaBancaria;
use App\Domain\Finance\Services\CadastroContasBancarias;
use App\Domain\Organizations\Services\ContextoEmpresarial;
use App\Domain\Registry\Models\Pessoa;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ContaBancariaController extends Controller
{
    public function __construct(private CadastroContasBancarias $cadastroContasBancarias) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ContaBancaria::class);

        return Inertia::render('Finance/Accounts/Index', $this->cadastroContasBancarias->listar($request, $request->user()));
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', ContaBancaria::class);

        return Inertia::render('Finance/Accounts/Form', [
            'pessoas' => $this->pessoasDisponiveis($request),
            'tiposConta' => [['value' => 'corrente', 'nome' => 'Conta corrente'], ['value' => 'poupanca', 'nome' => 'Poupança']],
            'tiposPix' => [['value' => 'cpf_cnpj', 'nome' => 'CPF/CNPJ'], ['value' => 'telefone', 'nome' => 'Telefone'], ['value' => 'email', 'nome' => 'E-mail'], ['value' => 'aleatoria', 'nome' => 'Chave aleatória']],
            'conta' => null,
        ]);
    }

    public function store(StoreContaBancariaRequest $request): RedirectResponse
    {
        $vinculo = $this->cadastroContasBancarias->criar($request, $request->user(), $request->validated());

        return to_route('contas-bancarias.show', $vinculo->contaBancaria)->with('success', 'Conta bancária adotada na transportadora ativa.');
    }

    public function pesquisarPessoa(PesquisarContasPessoaRequest $request): JsonResponse
    {
        $pessoa = Pessoa::query()->findOrFail((int) $request->validated('pessoa_id'));

        return response()->json(['contas' => $this->cadastroContasBancarias->contasDaPessoa($request, $request->user(), $pessoa)]);
    }

    public function show(Request $request, ContaBancaria $contaBancaria): Response
    {
        Gate::authorize('view', $contaBancaria);

        return Inertia::render('Finance/Accounts/Show', ['conta' => $this->cadastroContasBancarias->detalhes($request, $request->user(), $contaBancaria)]);
    }

    public function edit(Request $request, ContaBancaria $contaBancaria): Response
    {
        Gate::authorize('update', $contaBancaria);
        $vinculo = $this->cadastroContasBancarias->obter($request, $request->user(), $contaBancaria);

        return Inertia::render('Finance/Accounts/Form', ['conta' => ['id' => $vinculo->id, 'conta_bancaria_id' => $contaBancaria->id, 'titular' => $contaBancaria->pessoa->nome, 'identificacao' => $this->cadastroContasBancarias->detalhes($request, $request->user(), $contaBancaria)['identificacao'], 'ativo' => $vinculo->ativo], 'pessoas' => [], 'tiposConta' => [], 'tiposPix' => []]);
    }

    public function update(UpdateContaBancariaRequest $request, TransportadoraContaBancaria $transportadoraContaBancaria): RedirectResponse
    {
        $vinculo = $this->cadastroContasBancarias->atualizar($request, $request->user(), $transportadoraContaBancaria, (bool) $request->validated('ativo'));

        return to_route('contas-bancarias.show', $vinculo->contaBancaria)->with('success', 'Situação do vínculo atualizada.');
    }

    /**
     * @return array<int, array{id: int, nome: string, documento: string}>
     */
    private function pessoasDisponiveis(Request $request): array
    {
        $vinculo = app(ContextoEmpresarial::class)->vinculoAtual($request, $request->user());

        return Pessoa::query()->whereHas('transportadoras', fn ($query) => $query->where('transportadora_id', $vinculo?->transportadora_id)->where('ativo', true))->orderBy('nome')->get(['id', 'nome', 'documento'])->map(fn (Pessoa $pessoa): array => ['id' => $pessoa->id, 'nome' => $pessoa->nome, 'documento' => substr($pessoa->documento, 0, 3).'.***.***-'.substr($pessoa->documento, -2)])->all();
    }
}

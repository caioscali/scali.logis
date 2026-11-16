<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Enums\TipoChavePix;
use App\Domain\Finance\Models\ContaBancaria;
use App\Domain\Finance\Models\TransportadoraContaBancaria;
use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Domain\Organizations\Services\ContextoEmpresarial;
use App\Domain\Registry\Models\Pessoa;
use App\Domain\Registry\Models\TransportadoraPessoa;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CadastroContasBancarias
{
    public function __construct(private ContextoEmpresarial $contextoEmpresarial) {}

    public function podeConsultar(Request $request, User $usuario): bool
    {
        return $this->vinculoAtual($request, $usuario) !== null;
    }

    public function podeModificar(Request $request, User $usuario): bool
    {
        return $this->vinculoAtual($request, $usuario)?->perfil->podeGerenciarPessoas() ?? false;
    }

    public function contaNoContexto(Request $request, User $usuario, ContaBancaria $conta): bool
    {
        $transportadora = $this->transportadoraAtual($request, $usuario);

        return $conta->vinculos()->where('transportadora_id', $transportadora->id)->where('ativo', true)->exists();
    }

    /**
     * @return array{transportadora: array{id: int, nome: string}, contas: LengthAwarePaginator, paginator: array<string, mixed>, busca: string}
     */
    public function listar(Request $request, User $usuario): array
    {
        $transportadora = $this->transportadoraAtual($request, $usuario);
        $busca = trim((string) $request->string('busca'));
        $contas = $this->consultaVinculos($transportadora)
            ->when($busca !== '', fn (Builder $query) => $query->whereHas('contaBancaria.pessoa', fn (Builder $pessoa) => $pessoa->where('nome', 'like', "%{$busca}%")))
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return [
            'transportadora' => ['id' => $transportadora->id, 'nome' => $transportadora->nome_fantasia],
            'contas' => $contas->through(fn (TransportadoraContaBancaria $vinculo): array => $this->resumo($vinculo)),
            'paginator' => ['current_page' => $contas->currentPage(), 'last_page' => $contas->lastPage(), 'links' => $contas->linkCollection()->toArray()],
            'busca' => $busca,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function contasDaPessoa(Request $request, User $usuario, Pessoa $pessoa): array
    {
        $transportadora = $this->transportadoraAtual($request, $usuario);
        $this->validarPessoaTitular($pessoa, $transportadora);

        return $pessoa->contasBancarias()
            ->with(['vinculos' => fn ($query) => $query->where('transportadora_id', $transportadora->id)])
            ->get()
            ->map(fn (ContaBancaria $conta): array => [
                'id' => $conta->id,
                'placa' => null,
                'banco' => $conta->banco_nome,
                'identificacao' => $this->identificacaoMascarada($conta),
                'ja_vinculada' => $conta->vinculos->first()?->ativo === true,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function criar(Request $request, User $usuario, array $dados): TransportadoraContaBancaria
    {
        $transportadora = $this->transportadoraParaModificar($request, $usuario);
        $pessoa = Pessoa::query()->findOrFail((int) $dados['pessoa_id']);
        $this->validarPessoaTitular($pessoa, $transportadora);

        return DB::transaction(function () use ($transportadora, $pessoa, $dados): TransportadoraContaBancaria {
            $conta = isset($dados['conta_bancaria_id']) && $dados['conta_bancaria_id'] !== null
                ? $this->adotarContaExistente($transportadora, $pessoa, (int) $dados['conta_bancaria_id'])
                : $this->criarContaGlobal($pessoa, $dados);

            $vinculo = TransportadoraContaBancaria::query()->firstOrNew([
                'transportadora_id' => $transportadora->id,
                'conta_bancaria_id' => $conta->id,
            ]);

            if ($vinculo->exists && $vinculo->ativo) {
                throw ValidationException::withMessages(['conta_bancaria_id' => 'Esta conta já está adotada pela transportadora.']);
            }

            $vinculo->ativo = true;
            $vinculo->save();

            return $vinculo->load(['contaBancaria.pessoa']);
        });
    }

    public function atualizar(Request $request, User $usuario, TransportadoraContaBancaria $vinculo, bool $ativo): TransportadoraContaBancaria
    {
        $this->autorizarVinculo($request, $usuario, $vinculo);
        $vinculo->update(['ativo' => $ativo]);

        return $vinculo->load(['contaBancaria.pessoa']);
    }

    public function obter(Request $request, User $usuario, ContaBancaria $conta): TransportadoraContaBancaria
    {
        $transportadora = $this->transportadoraAtual($request, $usuario);

        return $this->consultaVinculos($transportadora)->where('conta_bancaria_id', $conta->id)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    public function detalhes(Request $request, User $usuario, ContaBancaria $conta): array
    {
        $vinculo = $this->obter($request, $usuario, $conta);
        $dados = $this->resumo($vinculo);

        if ($this->dadosCompletosPermitidos($request, $usuario)) {
            $dados['dados_completos'] = [
                'banco_codigo' => $conta->banco_codigo,
                'banco_nome' => $conta->banco_nome,
                'agencia' => $conta->agencia,
                'numero_conta' => $conta->numero_conta,
                'digito_conta' => $conta->digito_conta,
                'tipo_conta' => $conta->tipo_conta?->value,
                'tipo_chave_pix' => $conta->tipo_chave_pix?->value,
                'chave_pix' => $conta->chave_pix,
            ];
        } else {
            $dados['dados_completos'] = null;
        }

        return $dados;
    }

    /**
     * Consulted by the future transport-settlement module.
     *
     * @return Collection<int, ContaBancaria>
     */
    public function contasAtivasDaPessoa(Request $request, User $usuario, Pessoa $pessoa): Collection
    {
        $transportadora = $this->transportadoraAtual($request, $usuario);
        $this->validarPessoaTitular($pessoa, $transportadora);

        return ContaBancaria::query()
            ->where('pessoa_id', $pessoa->id)
            ->whereHas('vinculos', fn (Builder $query) => $query->where('transportadora_id', $transportadora->id)->where('ativo', true))
            ->get();
    }

    public function dadosCompletosPermitidos(Request $request, User $usuario): bool
    {
        return $this->vinculoAtual($request, $usuario)?->perfil->value === 'administrador';
    }

    private function criarContaGlobal(Pessoa $pessoa, array $dados): ContaBancaria
    {
        $normalizados = $this->normalizarDados($dados);
        $this->validarDadosPagamento($pessoa, $normalizados);
        $existente = $pessoa->contasBancarias()->lockForUpdate()->get()->first(fn (ContaBancaria $conta): bool => $this->dadosIguais($conta, $normalizados));

        if ($existente !== null) {
            return $existente;
        }

        return $pessoa->contasBancarias()->create($normalizados);
    }

    private function adotarContaExistente(Transportadora $transportadora, Pessoa $pessoa, int $contaId): ContaBancaria
    {
        $conta = ContaBancaria::query()->whereKey($contaId)->where('pessoa_id', $pessoa->id)->first();

        if ($conta === null) {
            throw ValidationException::withMessages(['conta_bancaria_id' => 'A conta não pertence à pessoa selecionada.']);
        }

        return $conta;
    }

    private function validarPessoaTitular(Pessoa $pessoa, Transportadora $transportadora): void
    {
        if (! TransportadoraPessoa::query()->where('transportadora_id', $transportadora->id)->where('pessoa_id', $pessoa->id)->where('ativo', true)->exists()) {
            throw ValidationException::withMessages(['pessoa_id' => 'A pessoa titular precisa estar vinculada e ativa na transportadora.']);
        }
    }

    private function autorizarVinculo(Request $request, User $usuario, TransportadoraContaBancaria $vinculo): void
    {
        $contexto = $this->vinculoAtual($request, $usuario);

        if ($contexto === null || $contexto->transportadora_id !== $vinculo->transportadora_id || ! $contexto->perfil->podeGerenciarPessoas()) {
            abort(403);
        }
    }

    private function transportadoraParaModificar(Request $request, User $usuario): Transportadora
    {
        $vinculo = $this->vinculoAtual($request, $usuario);

        if ($vinculo === null || ! $vinculo->perfil->podeGerenciarPessoas()) {
            abort(403);
        }

        return $vinculo->transportadora;
    }

    private function transportadoraAtual(Request $request, User $usuario): Transportadora
    {
        $vinculo = $this->vinculoAtual($request, $usuario);

        if ($vinculo === null) {
            abort(403);
        }

        return $vinculo->transportadora;
    }

    private function vinculoAtual(Request $request, User $usuario): ?UsuarioTransportadora
    {
        return $this->contextoEmpresarial->vinculoAtual($request, $usuario);
    }

    private function consultaVinculos(Transportadora $transportadora): Builder
    {
        return TransportadoraContaBancaria::query()->with(['contaBancaria.pessoa'])->where('transportadora_id', $transportadora->id)->where('ativo', true);
    }

    /**
     * @return array<string, mixed>
     */
    private function resumo(TransportadoraContaBancaria $vinculo): array
    {
        $conta = $vinculo->contaBancaria;

        return [
            'id' => $vinculo->id,
            'conta_bancaria_id' => $conta->id,
            'titular' => $conta->pessoa->nome,
            'banco' => $conta->banco_nome ?: $conta->banco_codigo,
            'identificacao' => $this->identificacaoMascarada($conta),
            'ativo' => $vinculo->ativo,
        ];
    }

    private function identificacaoMascarada(ContaBancaria $conta): string
    {
        if ($conta->chave_pix !== null) {
            return 'PIX '.mb_strtoupper($conta->tipo_chave_pix?->value ?? 'chave').' · '.$this->mascarar($conta->chave_pix);
        }

        return 'Ag. '.$this->mascarar($conta->agencia).' · Conta '.$this->mascarar($conta->numero_conta);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizarDados(array $dados): array
    {
        return [
            'pessoa_id' => $dados['pessoa_id'],
            'banco_codigo' => $dados['banco_codigo'] ?? null,
            'banco_nome' => isset($dados['banco_nome']) ? trim((string) $dados['banco_nome']) : null,
            'agencia' => $dados['agencia'] ?? null,
            'numero_conta' => $dados['numero_conta'] ?? null,
            'digito_conta' => $dados['digito_conta'] ?? null,
            'tipo_conta' => $dados['tipo_conta'] ?? null,
            'tipo_chave_pix' => $dados['tipo_chave_pix'] ?? null,
            'chave_pix' => $this->normalizarPix($dados['tipo_chave_pix'] ?? null, $dados['chave_pix'] ?? null),
        ];
    }

    private function normalizarPix(?string $tipo, ?string $chave): ?string
    {
        if ($chave === null || trim($chave) === '') {
            return null;
        }

        return match ($tipo) {
            TipoChavePix::CpfCnpj->value, TipoChavePix::Telefone->value => preg_replace('/\D+/', '', $chave) ?? '',
            TipoChavePix::Email->value => mb_strtolower(trim($chave)),
            default => trim($chave),
        } ?: null;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function validarDadosPagamento(Pessoa $pessoa, array $dados): void
    {
        $temBanco = collect(['banco_codigo', 'banco_nome', 'agencia', 'numero_conta', 'digito_conta', 'tipo_conta'])->contains(fn (string $campo): bool => filled($dados[$campo] ?? null));
        $temPix = filled($dados['tipo_chave_pix'] ?? null) || filled($dados['chave_pix'] ?? null);

        if (! $temBanco && ! $temPix) {
            throw ValidationException::withMessages(['banco_codigo' => 'Informe dados bancários ou uma chave PIX.']);
        }

        if ($temBanco && (blank($dados['banco_codigo'] ?? null) || blank($dados['banco_nome'] ?? null) || blank($dados['agencia'] ?? null) || blank($dados['numero_conta'] ?? null))) {
            throw ValidationException::withMessages(['banco_codigo' => 'Informe banco, código, agência e número da conta.']);
        }

        if ($temPix && (blank($dados['tipo_chave_pix'] ?? null) || blank($dados['chave_pix'] ?? null))) {
            throw ValidationException::withMessages(['chave_pix' => 'Informe o tipo e o valor da chave PIX.']);
        }

        if (($dados['tipo_chave_pix'] ?? null) === TipoChavePix::Email->value && filter_var($dados['chave_pix'], FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages(['chave_pix' => 'Informe um e-mail válido para a chave PIX.']);
        }

        if (($dados['tipo_chave_pix'] ?? null) === TipoChavePix::Telefone->value && ! preg_match('/^\d{10,13}$/', (string) $dados['chave_pix'])) {
            throw ValidationException::withMessages(['chave_pix' => 'Informe um telefone válido para a chave PIX.']);
        }

        if (($dados['tipo_chave_pix'] ?? null) === TipoChavePix::CpfCnpj->value) {
            $tamanho = $pessoa->tipo->tamanhoDocumento();

            if (! preg_match('/^\d{'.$tamanho.'}$/', (string) $dados['chave_pix'])) {
                throw ValidationException::withMessages(['chave_pix' => 'A chave PIX CPF/CNPJ deve ter o formato do documento do titular.']);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function dadosIguais(ContaBancaria $conta, array $dados): bool
    {
        foreach (['banco_codigo', 'banco_nome', 'agencia', 'numero_conta', 'digito_conta', 'tipo_conta', 'tipo_chave_pix', 'chave_pix'] as $campo) {
            $atual = $conta->{$campo};
            $atual = $atual instanceof \BackedEnum ? $atual->value : $atual;

            if ($atual !== ($dados[$campo] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function mascarar(?string $valor): string
    {
        if ($valor === null || $valor === '') {
            return 'não informado';
        }

        return str_repeat('•', max(0, mb_strlen($valor) - 2)).mb_substr($valor, -2);
    }
}

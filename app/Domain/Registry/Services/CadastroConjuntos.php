<?php

namespace App\Domain\Registry\Services;

use App\Domain\Finance\Models\ContaBancaria;
use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Domain\Organizations\Services\ContextoEmpresarial;
use App\Domain\Registry\Enums\FuncaoPessoa;
use App\Domain\Registry\Enums\TipoCarroceria;
use App\Domain\Registry\Enums\TipoComposicao;
use App\Domain\Registry\Models\ConjuntoImplemento;
use App\Domain\Registry\Models\ConjuntoTransporte;
use App\Domain\Registry\Models\Pessoa;
use App\Domain\Registry\Models\TransportadoraImplemento;
use App\Domain\Registry\Models\TransportadoraPessoa;
use App\Domain\Registry\Models\TransportadoraVeiculo;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CadastroConjuntos
{
    public function __construct(private ContextoEmpresarial $contextoEmpresarial) {}

    public function podeConsultar(Request $request, User $usuario): bool
    {
        return $this->vinculoAtual($request, $usuario) !== null;
    }

    public function podeModificar(Request $request, User $usuario): bool
    {
        return $this->vinculoAtual($request, $usuario)?->perfil->podeAlterarDados() ?? false;
    }

    public function pertenceAoContexto(Request $request, User $usuario, ConjuntoTransporte $conjunto): bool
    {
        $vinculo = $this->vinculoAtual($request, $usuario);

        return $vinculo !== null && $conjunto->transportadora_id === $vinculo->transportadora_id;
    }

    /** @return array{transportadora: array{id: int, nome: string}, conjuntos: LengthAwarePaginator, paginator: array<string, mixed>, busca: string} */
    public function listar(Request $request, User $usuario): array
    {
        $transportadora = $this->transportadoraAtual($request, $usuario);
        $busca = trim((string) $request->string('busca'));
        $conjuntos = $this->consultaContexto($transportadora)
            ->when($busca !== '', fn (Builder $query) => $query->where(function (Builder $subquery) use ($busca): void {
                $subquery->whereHas('motorista', fn (Builder $pessoa) => $pessoa->where('nome', 'like', "%{$busca}%"))
                    ->orWhereHas('transportador', fn (Builder $pessoa) => $pessoa->where('nome', 'like', "%{$busca}%"))
                    ->orWhereHas('veiculo', fn (Builder $veiculo) => $veiculo->where('placa', 'like', "%{$busca}%"));
            }))
            ->latest('id')->paginate(15)->withQueryString();

        return [
            'transportadora' => ['id' => $transportadora->id, 'nome' => $transportadora->nome_fantasia],
            'conjuntos' => $conjuntos->through(fn (ConjuntoTransporte $conjunto): array => $this->resumo($conjunto)),
            'paginator' => ['current_page' => $conjuntos->currentPage(), 'last_page' => $conjuntos->lastPage(), 'links' => $conjuntos->linkCollection()->toArray()],
            'busca' => $busca,
        ];
    }

    /** @return array<string, mixed> */
    public function opcoes(Request $request, User $usuario): array
    {
        $transportadora = $this->transportadoraAtual($request, $usuario);
        $pessoas = TransportadoraPessoa::query()->with(['pessoa', 'funcoes'])
            ->where('transportadora_id', $transportadora->id)->where('ativo', true)->get();

        $pessoasPorFuncao = static function (FuncaoPessoa $funcao) use ($pessoas): array {
            return $pessoas->filter(fn (TransportadoraPessoa $vinculo): bool => $vinculo->funcoes->contains(fn ($item): bool => $item->funcao === $funcao))
                ->map(fn (TransportadoraPessoa $vinculo): array => ['id' => $vinculo->pessoa_id, 'nome' => $vinculo->pessoa->nome])->values()->all();
        };

        $contas = ContaBancaria::query()->with('pessoa')->whereHas('vinculos', fn (Builder $query) => $query->where('transportadora_id', $transportadora->id)->where('ativo', true))
            ->whereIn('pessoa_id', $pessoas->pluck('pessoa_id'))->get()->map(fn (ContaBancaria $conta): array => [
                'id' => $conta->id,
                'pessoa_id' => $conta->pessoa_id,
                'titular' => $conta->pessoa->nome,
                'identificacao' => $this->identificacaoConta($conta),
            ])->all();

        return [
            'motoristas' => $pessoasPorFuncao(FuncaoPessoa::Motorista),
            'transportadores' => $pessoasPorFuncao(FuncaoPessoa::Transportador),
            'veiculos' => TransportadoraVeiculo::query()->with('veiculo')->where('transportadora_id', $transportadora->id)->where('ativo', true)->get()->map(fn (TransportadoraVeiculo $vinculo): array => ['id' => $vinculo->veiculo_id, 'placa' => $vinculo->veiculo->placa, 'cidade' => $vinculo->veiculo->cidade])->all(),
            'implementos' => TransportadoraImplemento::query()->with('implemento')->where('transportadora_id', $transportadora->id)->where('ativo', true)->get()->map(fn (TransportadoraImplemento $vinculo): array => ['id' => $vinculo->implemento_id, 'placa' => $vinculo->implemento->placa])->all(),
            'contas' => $contas,
            'composicoes' => TipoComposicao::opcoes(),
            'carrocerias' => TipoCarroceria::opcoes(),
        ];
    }

    /** @param array<string, mixed> $dados */
    public function criar(Request $request, User $usuario, array $dados): ConjuntoTransporte
    {
        $transportadora = $this->transportadoraParaModificar($request, $usuario);
        $composicao = TipoComposicao::from((string) $dados['tipo_composicao']);
        $implementos = $this->normalizarImplementos($dados['implementos'] ?? []);

        $this->validarQuantidade($composicao, $implementos);

        return DB::transaction(function () use ($transportadora, $dados, $composicao, $implementos): ConjuntoTransporte {
            $motorista = $this->pessoaComFuncao($transportadora, (int) $dados['motorista_pessoa_id'], FuncaoPessoa::Motorista);
            $transportador = $this->pessoaComFuncao($transportadora, (int) $dados['transportador_pessoa_id'], FuncaoPessoa::Transportador);
            $this->validarVeiculo($transportadora, (int) $dados['veiculo_id']);
            $this->validarImplementos($transportadora, $implementos);
            $conta = $this->validarConta($transportadora, (int) $dados['conta_bancaria_id'], [$motorista->id, $transportador->id]);

            $conjunto = ConjuntoTransporte::create([
                'transportadora_id' => $transportadora->id,
                'motorista_pessoa_id' => $motorista->id,
                'transportador_pessoa_id' => $transportador->id,
                'veiculo_id' => (int) $dados['veiculo_id'],
                'tipo_composicao' => $composicao,
                'tipo_carroceria' => $dados['tipo_carroceria'],
                'conta_bancaria_id' => $conta->id,
                'ativo' => true,
            ]);

            $conjunto->implementos()->createMany($implementos);

            return $conjunto->load(['motorista', 'transportador', 'veiculo', 'contaBancaria.pessoa', 'implementos.implemento']);
        });
    }

    public function atualizarSituacao(Request $request, User $usuario, ConjuntoTransporte $conjunto, bool $ativo): ConjuntoTransporte
    {
        $this->autorizarConjunto($request, $usuario, $conjunto);
        $conjunto->update(['ativo' => $ativo]);

        return $conjunto->fresh(['motorista', 'transportador', 'veiculo', 'contaBancaria.pessoa', 'implementos.implemento']);
    }

    public function obter(Request $request, User $usuario, ConjuntoTransporte $conjunto): ConjuntoTransporte
    {
        $transportadora = $this->transportadoraAtual($request, $usuario);

        return $this->consultaContexto($transportadora)->whereKey($conjunto->id)->firstOrFail();
    }

    /** @return array<string, mixed> */
    public function dadosParaDuplicar(Request $request, User $usuario, ConjuntoTransporte $conjunto): array
    {
        $conjunto = $this->obter($request, $usuario, $conjunto);

        return [
            'motorista_pessoa_id' => $conjunto->motorista_pessoa_id,
            'transportador_pessoa_id' => $conjunto->transportador_pessoa_id,
            'veiculo_id' => $conjunto->veiculo_id,
            'tipo_composicao' => $conjunto->tipo_composicao->value,
            'tipo_carroceria' => $conjunto->tipo_carroceria->value,
            'conta_bancaria_id' => $conjunto->conta_bancaria_id,
            'implementos' => $conjunto->implementos->map(fn (ConjuntoImplemento $item): array => ['implemento_id' => $item->implemento_id, 'posicao' => $item->posicao])->values()->all(),
        ];
    }

    private function validarQuantidade(TipoComposicao $composicao, array $implementos): void
    {
        if (count($implementos) !== $composicao->quantidadeImplementos()) {
            throw ValidationException::withMessages(['implementos' => "A composição {$composicao->nome()} exige {$composicao->quantidadeImplementos()} implemento(s)."]);
        }
    }

    /** @param array<int, mixed> $implementos */
    private function normalizarImplementos(array $implementos): array
    {
        $resultado = array_map(fn (array $item): array => ['implemento_id' => (int) $item['implemento_id'], 'posicao' => (int) $item['posicao']], $implementos);
        $ids = array_column($resultado, 'implemento_id');
        $posicoes = array_column($resultado, 'posicao');

        if (count($ids) !== count(array_unique($ids)) || count($posicoes) !== count(array_unique($posicoes))) {
            throw ValidationException::withMessages(['implementos' => 'Não repita implementos ou posições no conjunto.']);
        }

        return $resultado;
    }

    private function pessoaComFuncao(Transportadora $transportadora, int $pessoaId, FuncaoPessoa $funcao): Pessoa
    {
        $vinculo = TransportadoraPessoa::query()->where('transportadora_id', $transportadora->id)->where('pessoa_id', $pessoaId)->where('ativo', true)->whereHas('funcoes', fn (Builder $query) => $query->where('funcao', $funcao->value))->with('pessoa')->first();

        if ($vinculo === null) {
            throw ValidationException::withMessages(['pessoa_id' => "A pessoa precisa estar ativa e possuir a função {$funcao->value} na transportadora."]);
        }

        return $vinculo->pessoa;
    }

    private function validarVeiculo(Transportadora $transportadora, int $veiculoId): void
    {
        if (! TransportadoraVeiculo::query()->where('transportadora_id', $transportadora->id)->where('veiculo_id', $veiculoId)->where('ativo', true)->exists()) {
            throw ValidationException::withMessages(['veiculo_id' => 'O veículo não está adotado e ativo na transportadora.']);
        }
    }

    private function validarImplementos(Transportadora $transportadora, array $implementos): void
    {
        $ids = array_column($implementos, 'implemento_id');
        $quantidade = TransportadoraImplemento::query()->where('transportadora_id', $transportadora->id)->where('ativo', true)->whereIn('implemento_id', $ids)->count();

        if ($quantidade !== count($ids)) {
            throw ValidationException::withMessages(['implementos' => 'Todos os implementos precisam estar adotados e ativos na transportadora.']);
        }
    }

    /** @param array<int, int> $titulares */
    private function validarConta(Transportadora $transportadora, int $contaId, array $titulares): ContaBancaria
    {
        $conta = ContaBancaria::query()->whereKey($contaId)->whereIn('pessoa_id', $titulares)->whereHas('vinculos', fn (Builder $query) => $query->where('transportadora_id', $transportadora->id)->where('ativo', true))->with('pessoa')->first();

        if ($conta === null || ! TransportadoraPessoa::query()->where('transportadora_id', $transportadora->id)->where('pessoa_id', $conta->pessoa_id)->where('ativo', true)->exists()) {
            throw ValidationException::withMessages(['conta_bancaria_id' => 'A conta deve pertencer ao motorista ou transportador e estar ativa na transportadora.']);
        }

        return $conta;
    }

    private function autorizarConjunto(Request $request, User $usuario, ConjuntoTransporte $conjunto): void
    {
        if (! $this->podeModificar($request, $usuario) || ! $this->pertenceAoContexto($request, $usuario, $conjunto)) {
            abort(403);
        }
    }

    private function transportadoraParaModificar(Request $request, User $usuario): Transportadora
    {
        if (! $this->podeModificar($request, $usuario)) {
            abort(403);
        }

        return $this->transportadoraAtual($request, $usuario);
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

    private function consultaContexto(Transportadora $transportadora): Builder
    {
        return ConjuntoTransporte::query()->with(['motorista', 'transportador', 'veiculo', 'contaBancaria.pessoa', 'implementos.implemento'])->where('transportadora_id', $transportadora->id);
    }

    /** @return array<string, mixed> */
    private function resumo(ConjuntoTransporte $conjunto): array
    {
        return ['id' => $conjunto->id, 'motorista' => $conjunto->motorista->nome, 'transportador' => $conjunto->transportador->nome, 'veiculo' => $conjunto->veiculo->placa, 'implementos' => $conjunto->implementos->pluck('implemento.placa')->values()->all(), 'tipo_composicao' => $conjunto->tipo_composicao->value, 'tipo_composicao_nome' => $conjunto->tipo_composicao->nome(), 'tipo_carroceria' => $conjunto->tipo_carroceria->nome(), 'conta' => $conjunto->contaBancaria->pessoa->nome, 'ativo' => $conjunto->ativo];
    }

    private function identificacaoConta(ContaBancaria $conta): string
    {
        if ($conta->chave_pix !== null) {
            return 'PIX ••••'.substr($conta->chave_pix, -4);
        }

        return 'Conta ••••'.substr((string) $conta->numero_conta, -4);
    }
}

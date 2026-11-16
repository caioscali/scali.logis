<?php

namespace App\Domain\Registry\Services;

use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Domain\Organizations\Services\ContextoEmpresarial;
use App\Domain\Registry\Enums\FuncaoPessoa;
use App\Domain\Registry\Enums\TipoPessoa;
use App\Domain\Registry\Models\Pessoa;
use App\Domain\Registry\Models\TransportadoraPessoa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CadastroPessoas
{
    public function __construct(private ContextoEmpresarial $contextoEmpresarial) {}

    public function podeConsultar(Request $request, User $usuario): bool
    {
        return $this->contextoEmpresarial->vinculoAtual($request, $usuario) !== null;
    }

    public function podeModificar(Request $request, User $usuario): bool
    {
        return $this->contextoEmpresarial->vinculoAtual($request, $usuario)?->perfil->podeGerenciarPessoas() ?? false;
    }

    /**
     * @return array{transportadora: array{id: int, nome: string}, pessoas: array<int, array<string, mixed>>, paginator: array<string, mixed>, busca: string}
     */
    public function listar(Request $request, User $usuario): array
    {
        $transportadora = $this->transportadoraAtual($request, $usuario);
        $busca = trim((string) $request->string('busca'));
        $pessoas = $this->consultaVinculos($transportadora)
            ->when($busca !== '', function (Builder $query) use ($busca): void {
                $documento = preg_replace('/\D+/', '', $busca) ?? '';
                $query->whereHas('pessoa', function (Builder $pessoaQuery) use ($busca, $documento): void {
                    $pessoaQuery->where('nome', 'like', "%{$busca}%")
                        ->when($documento !== '', fn (Builder $query) => $query->orWhere('documento', $documento));
                });
            })
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return [
            'transportadora' => ['id' => $transportadora->id, 'nome' => $transportadora->nome_fantasia],
            'pessoas' => $pessoas->through(fn (TransportadoraPessoa $vinculo): array => $this->resumo($vinculo)),
            'paginator' => [
                'current_page' => $pessoas->currentPage(),
                'last_page' => $pessoas->lastPage(),
                'links' => $pessoas->linkCollection()->toArray(),
            ],
            'busca' => $busca,
        ];
    }

    /**
     * @return array{existe: bool, pessoa: array<string, mixed>|null}
     */
    public function pesquisar(Request $request, User $usuario, TipoPessoa $tipo, string $documento): array
    {
        $this->transportadoraAtual($request, $usuario);
        $pessoa = Pessoa::query()->where('tipo', $tipo->value)->where('documento', $documento)->first();

        if ($pessoa === null) {
            return ['existe' => false, 'pessoa' => null];
        }

        $vinculoAtual = $this->vinculoAtual($request, $usuario);
        $vinculada = $pessoa->transportadoras()->where('transportadora_id', $vinculoAtual->transportadora_id)->exists();

        return [
            'existe' => true,
            'pessoa' => [
                'tipo' => $pessoa->tipo->value,
                'documento' => $this->mascararDocumento($pessoa->documento),
                'nome' => $vinculada ? $pessoa->nome : null,
                'ja_vinculada' => $vinculada,
            ],
        ];
    }

    /**
     * @param  array{tipo: string, nome: string, documento: string, telefone?: ?string, email?: ?string, cidade?: ?string, uf?: ?string, funcoes?: array<int, string>}  $dados
     */
    public function criar(Request $request, User $usuario, array $dados): TransportadoraPessoa
    {
        $transportadora = $this->transportadoraAtual($request, $usuario);
        $this->autorizarModificacao($request, $usuario);

        try {
            return DB::transaction(function () use ($transportadora, $dados): TransportadoraPessoa {
                $pessoa = Pessoa::query()->where('documento', $dados['documento'])->first();

                if ($pessoa === null) {
                    $pessoa = Pessoa::create([
                        'tipo' => $dados['tipo'],
                        'nome' => $dados['nome'],
                        'documento' => $dados['documento'],
                        'telefone' => $dados['telefone'] ?? null,
                        'email' => $dados['email'] ?? null,
                        'cidade' => $dados['cidade'] ?? null,
                        'uf' => $dados['uf'] ?? null,
                    ]);
                } elseif ($pessoa->tipo->value !== $dados['tipo']) {
                    throw ValidationException::withMessages(['documento' => 'O documento já está cadastrado com outro tipo de pessoa.']);
                }

                $vinculo = TransportadoraPessoa::query()->firstOrNew([
                    'transportadora_id' => $transportadora->id,
                    'pessoa_id' => $pessoa->id,
                ]);

                if ($vinculo->exists && $vinculo->ativo) {
                    throw ValidationException::withMessages(['documento' => 'Esta pessoa já está vinculada à transportadora.']);
                }

                $vinculo->ativo = true;
                $vinculo->save();
                $this->sincronizarFuncoes($vinculo, $dados['funcoes'] ?? []);

                return $vinculo->load(['pessoa', 'funcoes']);
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505' || str_contains(strtolower($exception->getMessage()), 'unique')) {
                throw ValidationException::withMessages(['documento' => 'O cadastro foi alterado por outra operação. Pesquise o documento novamente e confirme a adoção.']);
            }

            throw $exception;
        }
    }

    /**
     * @param  array{ativo: bool, funcoes?: array<int, string>}  $dados
     */
    public function atualizar(Request $request, User $usuario, TransportadoraPessoa $vinculo, array $dados): TransportadoraPessoa
    {
        $this->autorizarVinculo($request, $usuario, $vinculo);

        return DB::transaction(function () use ($vinculo, $dados): TransportadoraPessoa {
            $vinculo->update(['ativo' => (bool) $dados['ativo']]);
            $this->sincronizarFuncoes($vinculo, $dados['funcoes'] ?? []);

            return $vinculo->load(['pessoa', 'funcoes']);
        });
    }

    public function obter(Request $request, User $usuario, Pessoa $pessoa): TransportadoraPessoa
    {
        $transportadora = $this->transportadoraAtual($request, $usuario);

        return $this->consultaVinculos($transportadora)->where('pessoa_id', $pessoa->id)->firstOrFail();
    }

    public function excluirPessoa(Pessoa $pessoa): never
    {
        if ($pessoa->transportadoras()->exists()) {
            throw ValidationException::withMessages(['pessoa' => 'Pessoas com vínculos não podem ser excluídas.']);
        }

        throw ValidationException::withMessages(['pessoa' => 'A exclusão de pessoas não está disponível.']);
    }

    private function autorizarModificacao(Request $request, User $usuario): void
    {
        if (! $this->podeModificar($request, $usuario)) {
            abort(403);
        }
    }

    private function autorizarVinculo(Request $request, User $usuario, TransportadoraPessoa $vinculo): void
    {
        $contexto = $this->vinculoAtual($request, $usuario);

        if ($contexto === null || $contexto->transportadora_id !== $vinculo->transportadora_id || ! $contexto->perfil->podeGerenciarPessoas()) {
            abort(403);
        }
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
        return TransportadoraPessoa::query()
            ->with(['pessoa', 'funcoes'])
            ->where('transportadora_id', $transportadora->id)
            ->where('ativo', true);
    }

    /**
     * @return array<string, mixed>
     */
    private function resumo(TransportadoraPessoa $vinculo): array
    {
        return [
            'id' => $vinculo->id,
            'pessoa_id' => $vinculo->pessoa_id,
            'nome' => $vinculo->pessoa->nome,
            'tipo' => $vinculo->pessoa->tipo->value,
            'documento' => $this->mascararDocumento($vinculo->pessoa->documento),
            'funcoes' => $vinculo->funcoes->pluck('funcao')->map(fn (FuncaoPessoa $funcao): string => $funcao->value)->values()->all(),
            'ativo' => $vinculo->ativo,
        ];
    }

    /**
     * @param  array<int, string>  $funcoes
     */
    private function sincronizarFuncoes(TransportadoraPessoa $vinculo, array $funcoes): void
    {
        $vinculo->funcoes()->delete();
        $vinculo->funcoes()->createMany(array_map(
            fn (string $funcao): array => ['funcao' => $funcao],
            array_values(array_unique($funcoes)),
        ));
    }

    private function mascararDocumento(string $documento): string
    {
        if (strlen($documento) === 11) {
            return substr($documento, 0, 3).'.***.***-'.substr($documento, -2);
        }

        return substr($documento, 0, 2).'.***.***/****-'.substr($documento, -2);
    }
}

<?php

namespace App\Domain\Registry\Services;

use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Domain\Organizations\Services\ContextoEmpresarial;
use App\Domain\Registry\Enums\TipoEquipamento;
use App\Domain\Registry\Models\Implemento;
use App\Domain\Registry\Models\TransportadoraImplemento;
use App\Domain\Registry\Models\TransportadoraVeiculo;
use App\Domain\Registry\Models\Veiculo;
use App\Models\User;
use App\Rules\Placa;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CadastroEquipamentos
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

    public function veiculoNoContexto(Request $request, User $usuario, Veiculo $veiculo): bool
    {
        $transportadora = $this->transportadoraAtual($request, $usuario);

        return TransportadoraVeiculo::query()->where('transportadora_id', $transportadora->id)->where('veiculo_id', $veiculo->id)->where('ativo', true)->exists();
    }

    public function implementoNoContexto(Request $request, User $usuario, Implemento $implemento): bool
    {
        $transportadora = $this->transportadoraAtual($request, $usuario);

        return TransportadoraImplemento::query()->where('transportadora_id', $transportadora->id)->where('implemento_id', $implemento->id)->where('ativo', true)->exists();
    }

    /**
     * @return array{transportadora: array{id: int, nome: string}, equipamentos: LengthAwarePaginator, paginator: array<string, mixed>, busca: string}
     */
    public function listarVeiculos(Request $request, User $usuario): array
    {
        $transportadora = $this->transportadoraAtual($request, $usuario);
        $busca = trim((string) $request->string('busca'));
        $equipamentos = TransportadoraVeiculo::query()
            ->with('veiculo')
            ->where('transportadora_id', $transportadora->id)
            ->where('ativo', true)
            ->when($busca !== '', fn (Builder $query) => $query->whereHas('veiculo', fn (Builder $veiculo) => $veiculo->where('placa', 'like', '%'.Placa::normalizar($busca).'%')->orWhere('cidade', 'like', "%{$busca}%")))
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return $this->resultadoLista($transportadora, $equipamentos, fn (TransportadoraVeiculo $vinculo): array => [
            'id' => $vinculo->id,
            'equipamento_id' => $vinculo->veiculo_id,
            'placa' => $vinculo->veiculo->placa,
            'cidade' => $vinculo->veiculo->cidade,
            'ativo' => $vinculo->ativo,
        ], $busca);
    }

    /**
     * @return array{transportadora: array{id: int, nome: string}, equipamentos: LengthAwarePaginator, paginator: array<string, mixed>, busca: string}
     */
    public function listarImplementos(Request $request, User $usuario): array
    {
        $transportadora = $this->transportadoraAtual($request, $usuario);
        $busca = trim((string) $request->string('busca'));
        $equipamentos = TransportadoraImplemento::query()
            ->with('implemento')
            ->where('transportadora_id', $transportadora->id)
            ->where('ativo', true)
            ->when($busca !== '', fn (Builder $query) => $query->whereHas('implemento', fn (Builder $implemento) => $implemento->where('placa', 'like', '%'.Placa::normalizar($busca).'%')))
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return $this->resultadoLista($transportadora, $equipamentos, fn (TransportadoraImplemento $vinculo): array => [
            'id' => $vinculo->id,
            'equipamento_id' => $vinculo->implemento_id,
            'placa' => $vinculo->implemento->placa,
            'ativo' => $vinculo->ativo,
        ], $busca);
    }

    /**
     * @return array{existe: bool, equipamento: array<string, mixed>|null}
     */
    public function pesquisar(Request $request, User $usuario, TipoEquipamento $tipo, string $placa): array
    {
        $transportadora = $this->transportadoraAtual($request, $usuario);
        $equipamento = $tipo === TipoEquipamento::Veiculo
            ? Veiculo::query()->where('placa', $placa)->first()
            : Implemento::query()->where('placa', $placa)->first();

        if ($equipamento === null) {
            return ['existe' => false, 'equipamento' => null];
        }

        $vinculada = $tipo === TipoEquipamento::Veiculo
            ? $equipamento->vinculos()->where('transportadora_id', $transportadora->id)->exists()
            : $equipamento->vinculos()->where('transportadora_id', $transportadora->id)->exists();

        return [
            'existe' => true,
            'equipamento' => [
                'tipo' => $tipo->value,
                'placa' => $equipamento->placa,
                'cidade' => $vinculada && $tipo === TipoEquipamento::Veiculo ? $equipamento->cidade : null,
                'ja_vinculado' => $vinculada,
            ],
        ];
    }

    /**
     * @param  array{placa: string, cidade?: ?string}  $dados
     */
    public function adotarVeiculo(Request $request, User $usuario, array $dados): TransportadoraVeiculo
    {
        $transportadora = $this->transportadoraParaModificar($request, $usuario);

        return DB::transaction(function () use ($transportadora, $dados): TransportadoraVeiculo {
            $this->bloquearPlaca($dados['placa']);
            $implemento = Implemento::query()->where('placa', $dados['placa'])->exists();

            if ($implemento) {
                throw ValidationException::withMessages(['placa' => 'A placa já está cadastrada como implemento.']);
            }

            $veiculo = Veiculo::query()->where('placa', $dados['placa'])->first();

            if ($veiculo === null) {
                if (blank($dados['cidade'] ?? null)) {
                    throw ValidationException::withMessages(['cidade' => 'Informe a cidade para cadastrar um veículo novo.']);
                }

                $veiculo = Veiculo::create(['placa' => $dados['placa'], 'cidade' => $dados['cidade']]);
            }

            $vinculo = TransportadoraVeiculo::query()->firstOrNew(['transportadora_id' => $transportadora->id, 'veiculo_id' => $veiculo->id]);

            if ($vinculo->exists && $vinculo->ativo) {
                throw ValidationException::withMessages(['placa' => 'Este veículo já está vinculado à transportadora.']);
            }

            $vinculo->ativo = true;
            $vinculo->save();

            return $vinculo->load('veiculo');
        });
    }

    /**
     * @param  array{placa: string}  $dados
     */
    public function adotarImplemento(Request $request, User $usuario, array $dados): TransportadoraImplemento
    {
        $transportadora = $this->transportadoraParaModificar($request, $usuario);

        return DB::transaction(function () use ($transportadora, $dados): TransportadoraImplemento {
            $this->bloquearPlaca($dados['placa']);
            $veiculo = Veiculo::query()->where('placa', $dados['placa'])->exists();

            if ($veiculo) {
                throw ValidationException::withMessages(['placa' => 'A placa já está cadastrada como veículo.']);
            }

            $implemento = Implemento::query()->firstOrCreate(['placa' => $dados['placa']]);
            $vinculo = TransportadoraImplemento::query()->firstOrNew(['transportadora_id' => $transportadora->id, 'implemento_id' => $implemento->id]);

            if ($vinculo->exists && $vinculo->ativo) {
                throw ValidationException::withMessages(['placa' => 'Este implemento já está vinculado à transportadora.']);
            }

            $vinculo->ativo = true;
            $vinculo->save();

            return $vinculo->load('implemento');
        });
    }

    public function atualizarVeiculo(Request $request, User $usuario, TransportadoraVeiculo $vinculo, bool $ativo): TransportadoraVeiculo
    {
        $this->autorizarVinculo($request, $usuario, $vinculo->transportadora_id);
        $vinculo->update(['ativo' => $ativo]);

        return $vinculo->load('veiculo');
    }

    public function obterVeiculo(Request $request, User $usuario, Veiculo $veiculo): TransportadoraVeiculo
    {
        $transportadora = $this->transportadoraAtual($request, $usuario);

        return TransportadoraVeiculo::query()->with('veiculo')->where('transportadora_id', $transportadora->id)->where('veiculo_id', $veiculo->id)->where('ativo', true)->firstOrFail();
    }

    public function atualizarImplemento(Request $request, User $usuario, TransportadoraImplemento $vinculo, bool $ativo): TransportadoraImplemento
    {
        $this->autorizarVinculo($request, $usuario, $vinculo->transportadora_id);
        $vinculo->update(['ativo' => $ativo]);

        return $vinculo->load('implemento');
    }

    public function obterImplemento(Request $request, User $usuario, Implemento $implemento): TransportadoraImplemento
    {
        $transportadora = $this->transportadoraAtual($request, $usuario);

        return TransportadoraImplemento::query()->with('implemento')->where('transportadora_id', $transportadora->id)->where('implemento_id', $implemento->id)->where('ativo', true)->firstOrFail();
    }

    public function excluirVeiculo(Veiculo $veiculo): never
    {
        if ($veiculo->vinculos()->exists()) {
            throw ValidationException::withMessages(['veiculo' => 'Veículos com vínculos não podem ser excluídos.']);
        }

        throw ValidationException::withMessages(['veiculo' => 'A exclusão de veículos não está disponível.']);
    }

    public function excluirImplemento(Implemento $implemento): never
    {
        if ($implemento->vinculos()->exists()) {
            throw ValidationException::withMessages(['implemento' => 'Implementos com vínculos não podem ser excluídos.']);
        }

        throw ValidationException::withMessages(['implemento' => 'A exclusão de implementos não está disponível.']);
    }

    /**
     * @param  callable(mixed): array<string, mixed>  $mapear
     * @return array{transportadora: array{id: int, nome: string}, equipamentos: LengthAwarePaginator, paginator: array<string, mixed>, busca: string}
     */
    private function resultadoLista(Transportadora $transportadora, mixed $equipamentos, callable $mapear, string $busca): array
    {
        return [
            'transportadora' => ['id' => $transportadora->id, 'nome' => $transportadora->nome_fantasia],
            'equipamentos' => $equipamentos->through($mapear),
            'paginator' => ['current_page' => $equipamentos->currentPage(), 'last_page' => $equipamentos->lastPage(), 'links' => $equipamentos->linkCollection()->toArray()],
            'busca' => $busca,
        ];
    }

    private function bloquearPlaca(string $placa): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['scali-logis:placa:'.$placa]);
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

    private function autorizarVinculo(Request $request, User $usuario, int $transportadoraId): void
    {
        $contexto = $this->vinculoAtual($request, $usuario);

        if ($contexto === null || $contexto->transportadora_id !== $transportadoraId || ! $contexto->perfil->podeGerenciarPessoas()) {
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
}

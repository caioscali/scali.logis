<?php

namespace App\Domain\LoadingOrders\Services;

use App\Domain\LoadingOrders\Enums\SituacaoOrdem;
use App\Domain\LoadingOrders\Models\OrdemCarregamento;
use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Services\ContextoEmpresarial;
use App\Domain\Registry\Enums\FuncaoPessoa;
use App\Domain\Registry\Models\ConjuntoTransporte;
use App\Domain\Registry\Models\TransportadoraPessoa;
use App\Domain\Registry\Models\TransportadoraVeiculo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CadastroOrdensCarregamento
{
    public function __construct(private ContextoEmpresarial $contextoEmpresarial) {}

    public function podeConsultar(Request $request, User $usuario): bool
    {
        return $this->contextoEmpresarial->vinculoAtual($request, $usuario) !== null;
    }

    public function podeCriar(Request $request, User $usuario): bool
    {
        return $this->contextoEmpresarial->vinculoAtual($request, $usuario)?->perfil->podeAlterarDados() ?? false;
    }

    public function podeEmitir(Request $request, User $usuario): bool
    {
        return $this->podeCriar($request, $usuario);
    }

    public function podeCancelar(Request $request, User $usuario): bool
    {
        return $this->contextoEmpresarial->vinculoAtual($request, $usuario)?->perfil->podeCancelarOrdem() ?? false;
    }

    public function pertenceAoContexto(Request $request, User $usuario, OrdemCarregamento $ordem): bool
    {
        $contexto = $this->contextoEmpresarial->atual($request, $usuario);

        return $contexto !== null
            && $ordem->transportadora_id === $contexto['transportadora']['id']
            && $ordem->filial_id === $contexto['filial']['id'];
    }

    /** @return array<string, mixed> */
    public function listar(Request $request, User $usuario): array
    {
        [$transportadora, $filial] = $this->contexto($request, $usuario);
        $busca = trim((string) $request->string('busca'));
        $situacao = $request->string('situacao')->toString();
        $ordens = OrdemCarregamento::query()
            ->with(['cliente', 'conjuntoTransporte.motorista', 'conjuntoTransporte.veiculo'])
            ->where('transportadora_id', $transportadora->id)
            ->where('filial_id', $filial->id)
            ->when($situacao !== '', fn (Builder $query) => $query->where('situacao', $situacao))
            ->when($busca !== '', fn (Builder $query) => $query->where(function (Builder $subquery) use ($busca): void {
                $subquery->where('numero', 'like', "%{$busca}%")
                    ->orWhereHas('cliente', fn (Builder $cliente) => $cliente->where('nome', 'like', "%{$busca}%"))
                    ->orWhereHas('conjuntoTransporte.motorista', fn (Builder $motorista) => $motorista->where('nome', 'like', "%{$busca}%"))
                    ->orWhereHas('conjuntoTransporte.veiculo', fn (Builder $veiculo) => $veiculo->where('placa', 'like', "%{$busca}%"));
            }))
            ->latest('created_at')->latest('id')->paginate(15)->withQueryString();

        return [
            'ordens' => $ordens->through(fn (OrdemCarregamento $ordem): array => $this->resumo($ordem)),
            'paginator' => ['current_page' => $ordens->currentPage(), 'last_page' => $ordens->lastPage(), 'links' => $ordens->linkCollection()->toArray()],
            'busca' => $busca, 'situacao' => $situacao, 'situacoes' => array_map(fn (SituacaoOrdem $item): array => ['valor' => $item->value, 'nome' => $item->nome()], SituacaoOrdem::cases()),
        ];
    }

    /** @return array<string, mixed> */
    public function opcoes(Request $request, User $usuario): array
    {
        [$transportadora, $filial] = $this->contexto($request, $usuario);
        $clientes = TransportadoraPessoa::query()->with('pessoa')->where('transportadora_id', $transportadora->id)->where('ativo', true)
            ->whereHas('funcoes', fn (Builder $query) => $query->where('funcao', FuncaoPessoa::Cliente->value))->get()
            ->map(fn (TransportadoraPessoa $vinculo): array => ['id' => $vinculo->pessoa_id, 'nome' => $vinculo->pessoa->nome])->values()->all();
        $conjuntos = ConjuntoTransporte::query()->with(['motorista', 'transportador', 'veiculo', 'implementos.implemento', 'contaBancaria.pessoa'])
            ->where('transportadora_id', $transportadora->id)->where('ativo', true)->get()
            ->map(fn (ConjuntoTransporte $conjunto): array => $this->resumoConjunto($conjunto))->values()->all();

        return ['filial' => ['id' => $filial->id, 'nome' => $filial->nome], 'clientes' => $clientes, 'conjuntos' => $conjuntos];
    }

    /** @param array<string, mixed> $dados */
    public function criar(Request $request, User $usuario, array $dados): OrdemCarregamento
    {
        [$transportadora, $filial] = $this->contexto($request, $usuario);

        return DB::transaction(function () use ($transportadora, $filial, $usuario, $dados): OrdemCarregamento {
            Transportadora::query()->whereKey($transportadora->id)->lockForUpdate()->firstOrFail();
            $numero = ((int) OrdemCarregamento::query()->where('transportadora_id', $transportadora->id)->lockForUpdate()->max('numero')) + 1;
            $ordem = OrdemCarregamento::create([...$this->dadosBase($dados), 'numero' => $numero, 'transportadora_id' => $transportadora->id, 'filial_id' => $filial->id, 'created_by' => $usuario->id, 'situacao' => SituacaoOrdem::Rascunho]);
            $this->validarReferencias($transportadora, $ordem);

            return $this->atualizarSnapshot($ordem->fresh(), $usuario);
        });
    }

    /** @param array<string, mixed> $dados */
    public function atualizar(Request $request, User $usuario, OrdemCarregamento $ordem, array $dados): OrdemCarregamento
    {
        $this->autorizarContexto($request, $usuario, $ordem);
        if ($ordem->situacao !== SituacaoOrdem::Rascunho) {
            throw ValidationException::withMessages(['ordem' => 'Somente rascunhos podem ser editados.']);
        }
        [$transportadora] = $this->contexto($request, $usuario);
        $ordem->update($this->dadosBase($dados));
        $this->validarReferencias($transportadora, $ordem);

        return $this->atualizarSnapshot($ordem->fresh(), $usuario);
    }

    public function emitir(Request $request, User $usuario, OrdemCarregamento $ordem): OrdemCarregamento
    {
        $this->autorizarContexto($request, $usuario, $ordem);
        if ($ordem->situacao !== SituacaoOrdem::Rascunho) {
            throw ValidationException::withMessages(['ordem' => 'A ordem não está em rascunho.']);
        }
        [$transportadora] = $this->contexto($request, $usuario);
        $this->validarCompleta($ordem);
        $this->validarReferencias($transportadora, $ordem);
        $ordem->update(['situacao' => SituacaoOrdem::Emitida, 'situacao_alterada_por' => $usuario->id, 'situacao_alterada_em' => now()]);

        return $ordem->fresh($this->relacoes());
    }

    public function cancelar(Request $request, User $usuario, OrdemCarregamento $ordem): OrdemCarregamento
    {
        $this->autorizarContexto($request, $usuario, $ordem);
        if ($ordem->situacao === SituacaoOrdem::Cancelada) {
            throw ValidationException::withMessages(['ordem' => 'A ordem já está cancelada.']);
        }
        $ordem->update(['situacao' => SituacaoOrdem::Cancelada, 'situacao_alterada_por' => $usuario->id, 'situacao_alterada_em' => now()]);

        return $ordem->fresh($this->relacoes());
    }

    public function obter(Request $request, User $usuario, OrdemCarregamento $ordem): OrdemCarregamento
    {
        $this->autorizarContexto($request, $usuario, $ordem);

        return $ordem->load($this->relacoes());
    }

    /** @return array<string, mixed> */
    public function dadosExportaveis(OrdemCarregamento $ordem): array
    {
        return ['operacionais' => ['numero' => $ordem->numero, 'cliente' => $ordem->snapshot_cliente_nome, 'motorista' => $ordem->snapshot_motorista_nome, 'transportador' => $ordem->snapshot_transportador_nome, 'veiculo' => $ordem->snapshot_veiculo_placa, 'implementos' => $ordem->snapshot_implementos_placas, 'produto' => $ordem->produto, 'quantidade' => $ordem->quantidade, 'unidade_medida' => $ordem->unidade_medida, 'origem' => $ordem->origem_cidade.' / '.$ordem->origem_uf, 'local_carregamento' => $ordem->local_carregamento, 'data_carregamento' => $ordem->data_carregamento?->toDateString(), 'destino' => $ordem->destino_cidade.' / '.$ordem->destino_uf, 'local_descarga' => $ordem->local_descarga, 'data_previsao_entrega' => $ordem->data_previsao_entrega?->toDateString(), 'observacoes' => $ordem->observacoes], 'financeiros' => ['valor_frete' => $ordem->valor_frete, 'valor_adiantamento' => $ordem->valor_adiantamento, 'valor_saldo' => $ordem->valor_saldo]];
    }

    /** @return array<int, string> */
    private function relacoes(): array
    {
        return ['transportadora', 'filial', 'cliente', 'conjuntoTransporte.motorista', 'conjuntoTransporte.transportador', 'conjuntoTransporte.veiculo', 'conjuntoTransporte.implementos.implemento', 'criador', 'situacaoAlteradaPor'];
    }

    /** @return array{0: Transportadora, 1: Filial} */
    private function contexto(Request $request, User $usuario): array
    {
        $contexto = $this->contextoEmpresarial->atual($request, $usuario);
        if ($contexto === null) {
            throw ValidationException::withMessages(['contexto' => 'Selecione uma transportadora e uma filial autorizadas.']);
        }
        $transportadora = Transportadora::query()->whereKey($contexto['transportadora']['id'])->where('ativo', true)->firstOrFail();
        $filial = Filial::query()->whereKey($contexto['filial']['id'])->where('transportadora_id', $transportadora->id)->where('ativo', true)->firstOrFail();

        return [$transportadora, $filial];
    }

    private function autorizarContexto(Request $request, User $usuario, OrdemCarregamento $ordem): void
    {
        if (! $this->pertenceAoContexto($request, $usuario, $ordem)) {
            abort(403);
        }
    }

    /** @param array<string, mixed> $dados */
    private function dadosBase(array $dados): array
    {
        $frete = $dados['valor_frete'] ?? null;
        $adiantamento = $dados['valor_adiantamento'] ?? null;

        return [...collect($dados)->only(['conjunto_transporte_id', 'cliente_pessoa_id', 'produto', 'quantidade', 'unidade_medida', 'origem_cidade', 'origem_uf', 'local_carregamento', 'data_carregamento', 'destino_cidade', 'destino_uf', 'local_descarga', 'data_previsao_entrega', 'valor_frete', 'valor_adiantamento', 'observacoes'])->all(), 'valor_saldo' => $frete !== null && $adiantamento !== null ? $this->subtrairValores((string) $frete, (string) $adiantamento) : null];
    }

    private function validarReferencias(Transportadora $transportadora, OrdemCarregamento $ordem): void
    {
        if ($ordem->cliente_pessoa_id !== null && ! TransportadoraPessoa::query()->where('transportadora_id', $transportadora->id)->where('pessoa_id', $ordem->cliente_pessoa_id)->where('ativo', true)->whereHas('funcoes', fn (Builder $query) => $query->where('funcao', FuncaoPessoa::Cliente->value))->exists()) {
            throw ValidationException::withMessages(['cliente_pessoa_id' => 'O cliente não está ativo e autorizado na transportadora.']);
        }
        if ($ordem->conjunto_transporte_id !== null) {
            $conjunto = ConjuntoTransporte::query()->with(['motorista', 'transportador', 'veiculo', 'implementos.implemento', 'contaBancaria.pessoa'])->whereKey($ordem->conjunto_transporte_id)->where('transportadora_id', $transportadora->id)->where('ativo', true)->first();
            if ($conjunto === null || $conjunto->contaBancaria === null || ! $this->conjuntoElegivel($transportadora, $conjunto)) {
                throw ValidationException::withMessages(['conjunto_transporte_id' => 'O conjunto precisa estar ativo, pertencer à transportadora e possuir conta elegível.']);
            }
        }
    }

    private function validarCompleta(OrdemCarregamento $ordem): void
    {
        $campos = ['conjunto_transporte_id' => 'Conjunto obrigatório.', 'cliente_pessoa_id' => 'Cliente obrigatório.', 'produto' => 'Produto obrigatório.', 'origem_cidade' => 'Origem obrigatória.', 'destino_cidade' => 'Destino obrigatório.', 'valor_frete' => 'Valor do frete obrigatório.'];
        foreach ($campos as $campo => $mensagem) {
            if (blank($ordem->{$campo})) {
                throw ValidationException::withMessages([$campo => $mensagem]);
            }
        }
        if ($this->centavos((string) $ordem->valor_frete) < 0 || $this->centavos((string) ($ordem->valor_adiantamento ?? '0')) < 0 || $this->centavos((string) ($ordem->valor_adiantamento ?? '0')) > $this->centavos((string) $ordem->valor_frete)) {
            throw ValidationException::withMessages(['valor_adiantamento' => 'O adiantamento deve ser não negativo e não superior ao frete.']);
        }
        if ($ordem->data_carregamento !== null && $ordem->data_previsao_entrega !== null && $ordem->data_previsao_entrega->lt($ordem->data_carregamento)) {
            throw ValidationException::withMessages(['data_previsao_entrega' => 'A previsão de entrega não pode ser anterior ao carregamento.']);
        }
    }

    private function subtrairValores(string $frete, string $adiantamento): string
    {
        $resultado = $this->centavos($frete) - $this->centavos($adiantamento);
        $sinal = $resultado < 0 ? '-' : '';
        $resultado = abs($resultado);

        return $sinal.intdiv($resultado, 100).'.'.str_pad((string) ($resultado % 100), 2, '0', STR_PAD_LEFT);
    }

    private function centavos(string $valor): int
    {
        $normalizado = str_replace(',', '.', trim($valor));
        [$inteiro, $decimal] = array_pad(explode('.', $normalizado, 2), 2, '0');

        return ((int) $inteiro * 100) + (int) str_pad(substr($decimal, 0, 2), 2, '0');
    }

    private function atualizarSnapshot(OrdemCarregamento $ordem, User $usuario): OrdemCarregamento
    {
        $ordem->load(['cliente', 'conjuntoTransporte.motorista', 'conjuntoTransporte.transportador', 'conjuntoTransporte.veiculo', 'conjuntoTransporte.implementos.implemento', 'conjuntoTransporte.contaBancaria']);
        if ($ordem->conjuntoTransporte !== null) {
            $conjunto = $ordem->conjuntoTransporte;
            $conta = $conjunto->contaBancaria;
            $ordem->update(['snapshot_motorista_nome' => $conjunto->motorista?->nome, 'snapshot_transportador_nome' => $conjunto->transportador?->nome, 'snapshot_veiculo_placa' => $conjunto->veiculo?->placa, 'snapshot_implementos_placas' => $conjunto->implementos->map(fn ($item): string => $item->implemento->placa)->values()->all(), 'snapshot_cliente_nome' => $ordem->cliente?->nome, 'snapshot_conta_pagamento' => $conta === null ? null : 'Conta ••••'.substr((string) $conta->numero_conta, -4)]);
        } elseif ($ordem->cliente !== null) {
            $ordem->update(['snapshot_cliente_nome' => $ordem->cliente->nome]);
        }

        return $ordem->fresh($this->relacoes());
    }

    private function conjuntoElegivel(Transportadora $transportadora, ConjuntoTransporte $conjunto): bool
    {
        $motoristaElegivel = $this->pessoaElegivel($transportadora, $conjunto->motorista_pessoa_id, FuncaoPessoa::Motorista);
        $transportadorElegivel = $this->pessoaElegivel($transportadora, $conjunto->transportador_pessoa_id, FuncaoPessoa::Transportador);
        $veiculoAtivo = TransportadoraVeiculo::query()->where('transportadora_id', $transportadora->id)->where('veiculo_id', $conjunto->veiculo_id)->where('ativo', true)->exists();
        $contaElegivel = $conjunto->contaBancaria->vinculos()->where('transportadora_id', $transportadora->id)->where('ativo', true)->exists() && in_array($conjunto->contaBancaria->pessoa_id, [$conjunto->motorista_pessoa_id, $conjunto->transportador_pessoa_id], true);

        return $motoristaElegivel && $transportadorElegivel && $veiculoAtivo && $contaElegivel;
    }

    private function pessoaElegivel(Transportadora $transportadora, int $pessoaId, FuncaoPessoa $funcao): bool
    {
        return TransportadoraPessoa::query()->where('transportadora_id', $transportadora->id)->where('pessoa_id', $pessoaId)->where('ativo', true)->whereHas('funcoes', fn (Builder $query) => $query->where('funcao', $funcao->value))->exists();
    }

    /** @return array<string, mixed> */
    private function resumo(OrdemCarregamento $ordem): array
    {
        return ['id' => $ordem->id, 'numero' => $ordem->numero, 'data' => $ordem->created_at?->format('d/m/Y'), 'cliente' => $ordem->snapshot_cliente_nome ?? $ordem->cliente?->nome, 'motorista' => $ordem->snapshot_motorista_nome ?? $ordem->conjuntoTransporte?->motorista?->nome, 'veiculo' => $ordem->snapshot_veiculo_placa ?? $ordem->conjuntoTransporte?->veiculo?->placa, 'origem' => trim($ordem->origem_cidade.' / '.$ordem->origem_uf), 'destino' => trim($ordem->destino_cidade.' / '.$ordem->destino_uf), 'situacao' => $ordem->situacao->value, 'situacao_nome' => $ordem->situacao->nome()];
    }

    /** @return array<string, mixed> */
    private function resumoConjunto(ConjuntoTransporte $conjunto): array
    {
        return ['id' => $conjunto->id, 'motorista' => $conjunto->motorista->nome, 'transportador' => $conjunto->transportador->nome, 'veiculo' => $conjunto->veiculo->placa, 'implementos' => $conjunto->implementos->pluck('implemento.placa')->values()->all(), 'carroceria' => $conjunto->tipo_carroceria->nome(), 'conta' => 'Conta ••••'.substr((string) $conjunto->contaBancaria->numero_conta, -4)];
    }
}

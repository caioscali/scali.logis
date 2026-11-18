<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Enums\SituacaoLancamentoFinanceiro;
use App\Domain\Finance\Models\LancamentoFinanceiro;
use App\Domain\FreightContracts\Enums\SituacaoConferencia;
use App\Domain\FreightContracts\Enums\TipoContratoFrete;
use App\Domain\FreightContracts\Models\ContratoFreteCarreto;
use App\Domain\Organizations\Services\ContextoEmpresarial;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CadastroLancamentosFinanceiros
{
    private const DISPLAY_TIMEZONE = 'America/Sao_Paulo';

    public function __construct(private ContextoEmpresarial $contextoEmpresarial) {}

    public function podeConsultar(Request $request, User $usuario): bool
    {
        return $this->contextoEmpresarial->vinculoAtual($request, $usuario) !== null;
    }

    public function podeAprovar(Request $request, User $usuario): bool
    {
        return $this->contextoEmpresarial->vinculoAtual($request, $usuario)?->perfil->podeAprovarLancamento() ?? false;
    }

    /** @return array<string, mixed> */
    public function listar(Request $request, User $usuario): array
    {
        $contexto = $this->contextoAtual($request, $usuario);
        $competencia = $this->competenciaFiltro($request->input('competencia'));
        $situacao = $request->string('situacao')->toString();
        $tipo = $request->string('tipo')->toString();
        $ordem = trim($request->string('ordem')->toString());
        $numeroCfc = trim($request->string('numero_cfc')->toString());
        $busca = trim($request->string('busca')->toString());

        $base = LancamentoFinanceiro::query()
            ->where('transportadora_id', $contexto['transportadora']['id'])
            ->where('filial_id', $contexto['filial']['id'])
            ->when($competencia !== null, fn (Builder $query) => $query->whereDate('competencia', $competencia))
            ->when($situacao !== '', fn (Builder $query) => $query->where('situacao', $situacao))
            ->when($tipo !== '', fn (Builder $query) => $query->where('tipo', $tipo))
            ->when($ordem !== '', fn (Builder $query) => $query->whereHas('ordem', fn (Builder $subquery) => $subquery->where('numero', 'like', "%{$ordem}%")))
            ->when($numeroCfc !== '', fn (Builder $query) => $query->where('numero_cfc', 'like', "%{$numeroCfc}%"))
            ->when($busca !== '', fn (Builder $query) => $query->whereHas('ordem', fn (Builder $subquery) => $subquery->where('snapshot_motorista_nome', 'like', "%{$busca}%")->orWhere('snapshot_transportador_nome', 'like', "%{$busca}%")));

        $resumo = [];
        foreach (SituacaoLancamentoFinanceiro::cases() as $estado) {
            $resumo[$estado->value] = (string) (clone $base)->where('situacao', $estado->value)->sum('valor');
        }
        $lancamentos = (clone $base)->with(['ordem', 'contrato'])->orderByDesc('competencia')->orderByDesc('id')->paginate(15)->withQueryString();

        return [
            'lancamentos' => $lancamentos->through(fn (LancamentoFinanceiro $lancamento): array => $this->resumo($lancamento)),
            'paginator' => ['current_page' => $lancamentos->currentPage(), 'last_page' => $lancamentos->lastPage(), 'links' => $lancamentos->linkCollection()->toArray()],
            'resumo' => $resumo,
            'competencia' => $competencia,
            'situacao' => $situacao,
            'tipo' => $tipo,
            'ordem' => $ordem,
            'numero_cfc' => $numeroCfc,
            'busca' => $busca,
            'situacoes' => array_map(fn (SituacaoLancamentoFinanceiro $item): array => ['valor' => $item->value, 'nome' => $item->nome()], SituacaoLancamentoFinanceiro::cases()),
            'tipos' => array_map(fn (TipoContratoFrete $item): array => ['valor' => $item->value, 'nome' => $item->nome()], TipoContratoFrete::cases()),
        ];
    }

    public function obter(Request $request, User $usuario, LancamentoFinanceiro $lancamento): LancamentoFinanceiro
    {
        $this->autorizarContexto($request, $usuario, $lancamento);

        return $lancamento->load(['transportadora', 'filial', 'ordem.conjuntoTransporte.motorista', 'ordem.conjuntoTransporte.transportador', 'contrato.conferidoPor', 'criador', 'aprovadoPor', 'rejeitadoPor', 'historico.usuario']);
    }

    public function gerarParaContrato(ContratoFreteCarreto $contrato, User $usuario): LancamentoFinanceiro
    {
        if ($contrato->situacao_conferencia !== SituacaoConferencia::Conferido) {
            throw ValidationException::withMessages(['contrato' => 'Somente CFCs conferidos geram lançamentos financeiros.']);
        }
        $contrato = $contrato->fresh(['ordem']);
        $lancamento = LancamentoFinanceiro::query()->where('contrato_frete_carreto_id', $contrato->id)->lockForUpdate()->first();
        $competencia = $this->competenciaDaConferencia($contrato);

        if ($lancamento !== null && $lancamento->situacao === SituacaoLancamentoFinanceiro::Aprovado) {
            return $lancamento;
        }
        if ($lancamento !== null
            && $lancamento->situacao === SituacaoLancamentoFinanceiro::Pendente
            && $lancamento->numero_cfc === $contrato->numero_contrato
            && $this->centavos((string) $lancamento->valor) === $this->centavos((string) $contrato->valor_contrato)
            && $lancamento->competencia?->toDateString() === $competencia) {
            return $lancamento;
        }
        if ($lancamento === null) {
            $lancamento = LancamentoFinanceiro::query()->create($this->dadosDoContrato($contrato, $competencia, $usuario));
            $this->registrarHistorico($lancamento, $usuario, 'criado_automaticamente', null, SituacaoLancamentoFinanceiro::Pendente, null);

            return $lancamento;
        }

        $situacaoAnterior = $lancamento->situacao;
        $lancamento->update([...$this->dadosDoContrato($contrato, $competencia, $usuario), 'situacao' => SituacaoLancamentoFinanceiro::Pendente, 'aprovado_por' => null, 'aprovado_em' => null, 'rejeitado_por' => null, 'rejeitado_em' => null, 'motivo_rejeicao' => null]);
        $this->registrarHistorico($lancamento, $usuario, 'reativado_apos_nova_conferencia', $situacaoAnterior, SituacaoLancamentoFinanceiro::Pendente, 'Novo ciclo de conferência do CFC.');

        return $lancamento->fresh();
    }

    public function garantirContratoPodeSerAlterado(ContratoFreteCarreto $contrato): void
    {
        $lancamento = LancamentoFinanceiro::query()->where('contrato_frete_carreto_id', $contrato->id)->lockForUpdate()->first();
        if ($lancamento?->situacao === SituacaoLancamentoFinanceiro::Aprovado) {
            throw ValidationException::withMessages(['contrato' => 'O CFC não pode ser alterado porque seu lançamento já foi aprovado.']);
        }
    }

    public function cancelarPorAlteracaoContrato(ContratoFreteCarreto $contrato, User $usuario): void
    {
        $lancamento = LancamentoFinanceiro::query()->where('contrato_frete_carreto_id', $contrato->id)->lockForUpdate()->first();
        if ($lancamento === null || $lancamento->situacao === SituacaoLancamentoFinanceiro::Cancelado) {
            return;
        }
        if ($lancamento->situacao === SituacaoLancamentoFinanceiro::Aprovado) {
            throw ValidationException::withMessages(['contrato' => 'O CFC não pode invalidar um lançamento aprovado.']);
        }
        $anterior = $lancamento->situacao;
        $lancamento->update(['situacao' => SituacaoLancamentoFinanceiro::Cancelado, 'updated_by' => $usuario->id]);
        $this->registrarHistorico($lancamento, $usuario, 'cancelado_por_alteracao_cfc', $anterior, SituacaoLancamentoFinanceiro::Cancelado, 'O CFC foi alterado antes da aprovação.');
    }

    public function ajustarCompetencia(Request $request, User $usuario, LancamentoFinanceiro $lancamento, string $competencia, string $motivo): LancamentoFinanceiro
    {
        $this->autorizarAlteracao($request, $usuario, $lancamento);
        if ($lancamento->situacao !== SituacaoLancamentoFinanceiro::Pendente) {
            throw ValidationException::withMessages(['competencia' => 'Somente lançamentos pendentes podem ter a competência ajustada.']);
        }
        if (blank(trim($motivo))) {
            throw ValidationException::withMessages(['motivo' => 'Informe o motivo do ajuste de competência.']);
        }
        $nova = CarbonImmutable::parse($competencia)->startOfMonth()->toDateString();

        return DB::transaction(function () use ($lancamento, $usuario, $nova, $motivo): LancamentoFinanceiro {
            $anterior = $lancamento->situacao;
            $lancamento->update(['competencia' => $nova, 'updated_by' => $usuario->id]);
            $this->registrarHistorico($lancamento, $usuario, 'competencia_alterada', $anterior, $anterior, trim($motivo));

            return $lancamento->fresh();
        });
    }

    public function aprovar(Request $request, User $usuario, LancamentoFinanceiro $lancamento): LancamentoFinanceiro
    {
        $this->autorizarAprovacao($request, $usuario, $lancamento);

        return DB::transaction(function () use ($lancamento, $usuario): LancamentoFinanceiro {
            $lancamento = LancamentoFinanceiro::query()->with('contrato')->lockForUpdate()->findOrFail($lancamento->id);
            $this->validarPodeAprovar($lancamento);
            $lancamento->update(['situacao' => SituacaoLancamentoFinanceiro::Aprovado, 'aprovado_por' => $usuario->id, 'aprovado_em' => now(), 'updated_by' => $usuario->id]);
            $this->registrarHistorico($lancamento, $usuario, 'aprovado', SituacaoLancamentoFinanceiro::Pendente, SituacaoLancamentoFinanceiro::Aprovado, null);

            return $lancamento->fresh();
        });
    }

    public function rejeitar(Request $request, User $usuario, LancamentoFinanceiro $lancamento, string $motivo): LancamentoFinanceiro
    {
        $this->autorizarAprovacao($request, $usuario, $lancamento);
        if (blank(trim($motivo))) {
            throw ValidationException::withMessages(['motivo_rejeicao' => 'Informe o motivo da rejeição.']);
        }

        return DB::transaction(function () use ($lancamento, $usuario, $motivo): LancamentoFinanceiro {
            $lancamento = LancamentoFinanceiro::query()->lockForUpdate()->findOrFail($lancamento->id);
            if ($lancamento->situacao !== SituacaoLancamentoFinanceiro::Pendente) {
                throw ValidationException::withMessages(['lancamento' => 'Somente lançamentos pendentes podem ser rejeitados.']);
            }
            $lancamento->update(['situacao' => SituacaoLancamentoFinanceiro::Rejeitado, 'rejeitado_por' => $usuario->id, 'rejeitado_em' => now(), 'motivo_rejeicao' => trim($motivo), 'updated_by' => $usuario->id]);
            $this->registrarHistorico($lancamento, $usuario, 'rejeitado', SituacaoLancamentoFinanceiro::Pendente, SituacaoLancamentoFinanceiro::Rejeitado, trim($motivo));

            return $lancamento->fresh();
        });
    }

    /** @return array<string, mixed> */
    private function dadosDoContrato(ContratoFreteCarreto $contrato, string $competencia, User $usuario): array
    {
        return ['transportadora_id' => $contrato->transportadora_id, 'filial_id' => $contrato->filial_id, 'ordem_carregamento_id' => $contrato->ordem_carregamento_id, 'contrato_frete_carreto_id' => $contrato->id, 'tipo' => $contrato->tipo->value, 'competencia' => $competencia, 'numero_cfc' => $contrato->numero_contrato, 'valor' => $contrato->valor_contrato, 'observacoes' => $contrato->observacoes, 'created_by' => $usuario->id, 'updated_by' => $usuario->id];
    }

    private function validarPodeAprovar(LancamentoFinanceiro $lancamento): void
    {
        if ($lancamento->situacao !== SituacaoLancamentoFinanceiro::Pendente) {
            throw ValidationException::withMessages(['lancamento' => 'Somente lançamentos pendentes podem ser aprovados.']);
        }
        $contrato = $lancamento->contrato;
        if ($contrato === null || $contrato->situacao_conferencia !== SituacaoConferencia::Conferido || $contrato->numero_contrato !== $lancamento->numero_cfc || $this->centavos((string) $contrato->valor_contrato) !== $this->centavos((string) $lancamento->valor)) {
            throw ValidationException::withMessages(['lancamento' => 'O CFC de origem não está mais conferido com os mesmos dados do lançamento.']);
        }
    }

    private function autorizarAprovacao(Request $request, User $usuario, LancamentoFinanceiro $lancamento): void
    {
        if (! $this->podeAprovar($request, $usuario) || ! $this->noContexto($request, $usuario, $lancamento)) {
            abort(403);
        }
    }

    private function autorizarAlteracao(Request $request, User $usuario, LancamentoFinanceiro $lancamento): void
    {
        if (! $this->podeAprovar($request, $usuario) || ! $this->noContexto($request, $usuario, $lancamento)) {
            abort(403);
        }
    }

    private function autorizarContexto(Request $request, User $usuario, LancamentoFinanceiro $lancamento): void
    {
        if (! $this->noContexto($request, $usuario, $lancamento)) {
            abort(403);
        }
    }

    private function noContexto(Request $request, User $usuario, LancamentoFinanceiro $lancamento): bool
    {
        $contexto = $this->contextoEmpresarial->atual($request, $usuario);

        return $contexto !== null
            && $lancamento->transportadora_id === $contexto['transportadora']['id']
            && $lancamento->filial_id === $contexto['filial']['id'];
    }

    /** @return array<string, mixed> */
    private function contextoAtual(Request $request, User $usuario): array
    {
        $contexto = $this->contextoEmpresarial->atual($request, $usuario);
        if ($contexto === null) {
            abort(403);
        }

        return $contexto;
    }

    private function competenciaDaConferencia(ContratoFreteCarreto $contrato): string
    {
        return CarbonImmutable::parse($contrato->conferido_em ?? now(), self::DISPLAY_TIMEZONE)->startOfMonth()->toDateString();
    }

    private function competenciaFiltro(mixed $competencia): ?string
    {
        if ($competencia === null || $competencia === '') {
            return CarbonImmutable::now(self::DISPLAY_TIMEZONE)->startOfMonth()->toDateString();
        }

        try {
            return CarbonImmutable::parse((string) $competencia, self::DISPLAY_TIMEZONE)->startOfMonth()->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    private function resumo(LancamentoFinanceiro $lancamento): array
    {
        return ['id' => $lancamento->id, 'data' => $lancamento->created_at?->setTimezone(self::DISPLAY_TIMEZONE)->format('d/m/Y'), 'ordem' => $lancamento->ordem?->numero, 'numero_cfc' => $lancamento->numero_cfc, 'tipo' => $lancamento->tipo->value, 'tipo_nome' => $lancamento->tipo->nome(), 'motorista' => $lancamento->ordem?->snapshot_motorista_nome, 'transportador' => $lancamento->ordem?->snapshot_transportador_nome, 'competencia' => $lancamento->competencia?->format('m/Y'), 'valor' => (string) $lancamento->valor, 'situacao' => $lancamento->situacao->value, 'situacao_nome' => $lancamento->situacao->nome()];
    }

    private function registrarHistorico(LancamentoFinanceiro $lancamento, User $usuario, string $acao, ?SituacaoLancamentoFinanceiro $anterior, ?SituacaoLancamentoFinanceiro $nova, ?string $motivo): void
    {
        $ultimo = $lancamento->historico()->latest('id')->first();
        $lancamento->historico()->create(['acao' => $acao, 'situacao_anterior' => $anterior?->value, 'situacao_nova' => $nova?->value, 'valor_anterior' => $ultimo?->valor_novo, 'valor_novo' => $lancamento->valor, 'competencia_anterior' => $ultimo?->competencia_nova, 'competencia_nova' => $lancamento->competencia, 'motivo' => $motivo, 'usuario_id' => $usuario->id, 'ocorrido_em' => now()]);
    }

    private function centavos(string $valor): int
    {
        $normalizado = str_replace(',', '.', trim($valor));
        [$inteiro, $decimal] = array_pad(explode('.', $normalizado, 2), 2, '0');

        return ((int) $inteiro * 100) + (int) str_pad(substr($decimal, 0, 2), 2, '0');
    }
}

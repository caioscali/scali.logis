<?php

namespace App\Domain\FreightContracts\Services;

use App\Domain\Finance\Services\CadastroContasBancarias;
use App\Domain\Finance\Services\CadastroLancamentosFinanceiros;
use App\Domain\FreightContracts\Enums\SituacaoConferencia;
use App\Domain\FreightContracts\Enums\TipoContratoFrete;
use App\Domain\FreightContracts\Models\ContratoFreteCarreto;
use App\Domain\FreightContracts\Models\HistoricoContratoFreteCarreto;
use App\Domain\LoadingOrders\Enums\SituacaoOrdem;
use App\Domain\LoadingOrders\Models\OrdemCarregamento;
use App\Domain\LoadingOrders\Services\CadastroOrdensCarregamento;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CadastroContratosFreteCarreto
{
    public function __construct(
        private CadastroOrdensCarregamento $cadastroOrdens,
        private CadastroContasBancarias $cadastroContas,
        private CadastroLancamentosFinanceiros $cadastroLancamentos,
    ) {}

    public function podeConsultar(Request $request, User $usuario, OrdemCarregamento $ordem): bool
    {
        return in_array($ordem->situacao, [SituacaoOrdem::Emitida, SituacaoOrdem::Cancelada], true)
            && $this->cadastroOrdens->pertenceAoContexto($request, $usuario, $ordem);
    }

    public function podeRegistrar(Request $request, User $usuario, OrdemCarregamento $ordem): bool
    {
        return $ordem->situacao === SituacaoOrdem::Emitida
            && $this->podeConsultar($request, $usuario, $ordem)
            && $this->cadastroOrdens->podeCriar($request, $usuario);
    }

    public function podeConferir(Request $request, User $usuario, OrdemCarregamento $ordem): bool
    {
        return $ordem->situacao === SituacaoOrdem::Emitida
            && $this->podeConsultar($request, $usuario, $ordem)
            && $this->cadastroOrdens->podeCancelar($request, $usuario);
    }

    /** @return array<string, mixed> */
    public function dadosParaConsulta(Request $request, User $usuario, OrdemCarregamento $ordem): array
    {
        $this->autorizarContexto($request, $usuario, $ordem);
        $ordem->loadMissing([
            'transportadora', 'filial', 'cliente', 'conjuntoTransporte.motorista', 'conjuntoTransporte.transportador',
            'conjuntoTransporte.veiculo', 'conjuntoTransporte.implementos.implemento', 'conjuntoTransporte.contaBancaria',
        ]);
        $dados = $this->dadosContrato($request, $usuario, $ordem);
        $contratos = $ordem->contratosFrete()->with(['conferidoPor', 'historico.alteradoPor', 'lancamentoFinanceiro'])->orderBy('tipo')->get();

        return [
            'dados' => $dados,
            'contratos' => $contratos->map(fn (ContratoFreteCarreto $contrato): array => $this->resumo($contrato))->values()->all(),
            'tipos' => array_map(fn (TipoContratoFrete $tipo): array => ['valor' => $tipo->value, 'nome' => $tipo->nome()], TipoContratoFrete::cases()),
            'pode_registrar' => $this->podeRegistrar($request, $usuario, $ordem),
            'pode_conferir' => $this->podeConferir($request, $usuario, $ordem),
        ];
    }

    /** @param array{tipo: string, numero_contrato: string, valor_contrato: string|int|float, observacoes?: ?string} $dados */
    public function registrar(Request $request, User $usuario, OrdemCarregamento $ordem, array $dados): ContratoFreteCarreto
    {
        $this->autorizarRegistro($request, $usuario, $ordem);
        $tipo = $this->tipo($dados['tipo']);
        $referencia = $this->valorReferencia($ordem, $tipo);
        $valor = $this->normalizarValor($dados['valor_contrato']);
        if (ContratoFreteCarreto::query()->where('ordem_carregamento_id', $ordem->id)->where('tipo', $tipo->value)->exists()) {
            throw ValidationException::withMessages(['tipo' => 'Já existe um CFC deste tipo para esta ordem. Edite o contrato existente.']);
        }
        if (ContratoFreteCarreto::query()->where('transportadora_id', $ordem->transportadora_id)->where('numero_contrato', trim($dados['numero_contrato']))->exists()) {
            throw ValidationException::withMessages(['numero_contrato' => 'Este número de CFC já está registrado nesta transportadora.']);
        }

        try {
            return ContratoFreteCarreto::query()->create([
                'transportadora_id' => $ordem->transportadora_id,
                'filial_id' => $ordem->filial_id,
                'ordem_carregamento_id' => $ordem->id,
                'tipo' => $tipo,
                'numero_contrato' => trim($dados['numero_contrato']),
                'valor_referencia' => $referencia,
                'valor_contrato' => $valor,
                'situacao_conferencia' => $this->situacaoInicial($referencia, $valor),
                'observacoes' => blank($dados['observacoes'] ?? null) ? null : trim((string) $dados['observacoes']),
                'created_by' => $usuario->id,
                'updated_by' => $usuario->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['numero_contrato' => 'Este número de CFC já está registrado nesta transportadora.']);
        }
    }

    /** @param array{numero_contrato: string, valor_contrato: string|int|float, observacoes?: ?string} $dados */
    public function atualizar(Request $request, User $usuario, OrdemCarregamento $ordem, ContratoFreteCarreto $contrato, array $dados): ContratoFreteCarreto
    {
        $this->autorizarContrato($request, $usuario, $ordem, $contrato);
        $referencia = (string) $contrato->valor_referencia;
        $valor = $this->normalizarValor($dados['valor_contrato']);

        return DB::transaction(function () use ($usuario, $contrato, $dados, $referencia, $valor): ContratoFreteCarreto {
            $this->cadastroLancamentos->garantirContratoPodeSerAlterado($contrato);
            $this->registrarHistorico($contrato, $usuario);
            $contrato->update([
                'numero_contrato' => trim($dados['numero_contrato']),
                'valor_contrato' => $valor,
                'situacao_conferencia' => $this->situacaoInicial($referencia, $valor),
                'conferido_por' => null,
                'conferido_em' => null,
                'observacoes' => blank($dados['observacoes'] ?? null) ? null : trim((string) $dados['observacoes']),
                'updated_by' => $usuario->id,
            ]);
            $this->cadastroLancamentos->cancelarPorAlteracaoContrato($contrato, $usuario);

            return $contrato->fresh(['historico']);
        });
    }

    public function conferir(Request $request, User $usuario, OrdemCarregamento $ordem, ContratoFreteCarreto $contrato): ContratoFreteCarreto
    {
        $this->autorizarConferencia($request, $usuario, $ordem, $contrato);
        if ($this->centavos((string) $contrato->valor_referencia) !== $this->centavos((string) $contrato->valor_contrato)) {
            throw ValidationException::withMessages(['contrato' => 'O CFC está divergente e não pode ser confirmado. Corrija o valor informado.']);
        }

        return DB::transaction(function () use ($contrato, $usuario): ContratoFreteCarreto {
            $contrato = ContratoFreteCarreto::query()->lockForUpdate()->findOrFail($contrato->id);
            $this->registrarHistorico($contrato, $usuario);
            $contrato->update(['situacao_conferencia' => SituacaoConferencia::Conferido, 'conferido_por' => $usuario->id, 'conferido_em' => now(), 'updated_by' => $usuario->id]);
            $this->cadastroLancamentos->gerarParaContrato($contrato->fresh(), $usuario);

            return $contrato->fresh(['conferidoPor', 'historico']);
        });
    }

    /** @return array<string, mixed> */
    private function dadosContrato(Request $request, User $usuario, OrdemCarregamento $ordem): array
    {
        $conjunto = $ordem->conjuntoTransporte;
        $completo = $this->cadastroContas->dadosCompletosPermitidos($request, $usuario);
        $motorista = $conjunto?->motorista;
        $transportador = $conjunto?->transportador;
        $cliente = $ordem->cliente;
        $conta = $conjunto?->contaBancaria;
        $implementos = $conjunto?->implementos->pluck('implemento.placa')->values()->all() ?? [];

        return [
            'cadastro' => [
                'motorista' => $this->pessoa($ordem->snapshot_motorista_nome, $motorista?->documento, $completo),
                'transportador' => $this->pessoa($ordem->snapshot_transportador_nome, $transportador?->documento, $completo),
                'cliente' => $this->pessoa($ordem->snapshot_cliente_nome, $cliente?->documento, $completo),
            ],
            'conjunto' => [
                'veiculo' => $ordem->snapshot_veiculo_placa ?? $conjunto?->veiculo?->placa,
                'implementos' => $ordem->snapshot_implementos_placas ?? $implementos,
                'tipo_composicao' => $conjunto?->tipo_composicao?->value,
                'tipo_carroceria' => $conjunto?->tipo_carroceria?->value,
            ],
            'carga' => [
                'numero_ordem' => $ordem->numero,
                'produto' => $ordem->produto,
                'quantidade' => $ordem->quantidade,
                'unidade_medida' => $ordem->unidade_medida,
                'origem' => trim($ordem->origem_cidade.' / '.$ordem->origem_uf),
                'destino' => trim($ordem->destino_cidade.' / '.$ordem->destino_uf),
                'data_carregamento' => $ordem->data_carregamento?->format('d/m/Y'),
                'data_previsao_entrega' => $ordem->data_previsao_entrega?->format('d/m/Y'),
            ],
            'financeiro' => [
                'frete' => (string) $ordem->valor_frete,
                'adiantamento' => (string) ($ordem->valor_adiantamento ?? '0.00'),
                'saldo' => (string) ($ordem->valor_saldo ?? '0.00'),
            ],
            'bancario' => $completo && $conta !== null ? [
                'autorizado' => true,
                'banco_codigo' => $conta->banco_codigo,
                'banco_nome' => $conta->banco_nome,
                'agencia' => $conta->agencia,
                'numero_conta' => $conta->numero_conta,
                'digito_conta' => $conta->digito_conta,
                'tipo_conta' => $conta->tipo_conta?->value,
                'tipo_chave_pix' => $conta->tipo_chave_pix?->value,
                'chave_pix' => $conta->chave_pix,
            ] : ['autorizado' => false, 'identificacao' => $ordem->snapshot_conta_pagamento ?? 'Conta vinculada'],
            'texto_copiavel' => $this->textoCopiavel($ordem, $motorista, $transportador, $cliente, $completo, $conta, $implementos),
        ];
    }

    /** @return array{nome: ?string, documento: ?string} */
    private function pessoa(?string $nome, ?string $documento, bool $completo): array
    {
        return ['nome' => $nome, 'documento' => $completo && $documento !== null ? $documento : ($documento === null ? null : $this->mascararDocumento($documento))];
    }

    private function textoCopiavel(OrdemCarregamento $ordem, mixed $motorista, mixed $transportador, mixed $cliente, bool $completo, mixed $conta, array $implementos): string
    {
        $linhas = [
            "Ordem de Carregamento: {$ordem->numero}",
            'Motorista: '.($ordem->snapshot_motorista_nome ?? $motorista?->nome),
            'Transportador: '.($ordem->snapshot_transportador_nome ?? $transportador?->nome),
            'Cliente: '.($ordem->snapshot_cliente_nome ?? $cliente?->nome),
            'Veículo: '.($ordem->snapshot_veiculo_placa ?? $ordem->conjuntoTransporte?->veiculo?->placa),
            'Implementos: '.implode(' · ', $ordem->snapshot_implementos_placas ?? $implementos),
            "Produto: {$ordem->produto}",
            "Quantidade: {$ordem->quantidade} {$ordem->unidade_medida}",
            "Origem: {$ordem->origem_cidade} / {$ordem->origem_uf}",
            "Destino: {$ordem->destino_cidade} / {$ordem->destino_uf}",
            'Frete: R$ '.number_format($this->centavos((string) $ordem->valor_frete) / 100, 2, ',', '.'),
            'Adiantamento: R$ '.number_format($this->centavos((string) ($ordem->valor_adiantamento ?? '0')) / 100, 2, ',', '.'),
            'Saldo: R$ '.number_format($this->centavos((string) ($ordem->valor_saldo ?? '0')) / 100, 2, ',', '.'),
        ];
        if ($completo && $conta !== null) {
            $linhas[] = "Banco: {$conta->banco_codigo} — {$conta->banco_nome}";
            $linhas[] = "Agência: {$conta->agencia} | Conta: {$conta->numero_conta}-{$conta->digito_conta}";
            if ($conta->chave_pix !== null) {
                $linhas[] = "PIX: {$conta->chave_pix}";
            }
        }

        return implode("\n", $linhas);
    }

    /** @return array<string, mixed> */
    private function resumo(ContratoFreteCarreto $contrato): array
    {
        return [
            'id' => $contrato->id,
            'tipo' => $contrato->tipo->value,
            'tipo_nome' => $contrato->tipo->nome(),
            'numero_contrato' => $contrato->numero_contrato,
            'valor_referencia' => (string) $contrato->valor_referencia,
            'valor_contrato' => (string) $contrato->valor_contrato,
            'diferenca' => $this->diferenca((string) $contrato->valor_referencia, (string) $contrato->valor_contrato),
            'situacao' => $contrato->situacao_conferencia->value,
            'situacao_nome' => $contrato->situacao_conferencia->nome(),
            'conferido_em' => $contrato->conferido_em?->format('d/m/Y H:i'),
            'conferido_por' => $contrato->conferidoPor?->name,
            'observacoes' => $contrato->observacoes,
            'historico' => $contrato->historico->map(fn (HistoricoContratoFreteCarreto $item): array => ['id' => $item->id, 'numero_contrato' => $item->numero_contrato, 'valor_contrato' => (string) $item->valor_contrato, 'situacao' => $item->situacao_conferencia, 'alterado_em' => $item->alterado_em?->format('d/m/Y H:i'), 'alterado_por' => $item->alteradoPor?->name])->all(),
            'lancamento' => $contrato->lancamentoFinanceiro === null ? null : ['id' => $contrato->lancamentoFinanceiro->id, 'competencia' => $contrato->lancamentoFinanceiro->competencia?->format('m/Y'), 'valor' => (string) $contrato->lancamentoFinanceiro->valor, 'situacao' => $contrato->lancamentoFinanceiro->situacao->value, 'situacao_nome' => $contrato->lancamentoFinanceiro->situacao->nome()],
        ];
    }

    private function registrarHistorico(ContratoFreteCarreto $contrato, User $usuario): void
    {
        $contrato->historico()->create([
            'tipo' => $contrato->tipo->value,
            'numero_contrato' => $contrato->numero_contrato,
            'valor_referencia' => $contrato->valor_referencia,
            'valor_contrato' => $contrato->valor_contrato,
            'situacao_conferencia' => $contrato->situacao_conferencia->value,
            'observacoes' => $contrato->observacoes,
            'alterado_por' => $usuario->id,
            'alterado_em' => now(),
        ]);
    }

    private function autorizarRegistro(Request $request, User $usuario, OrdemCarregamento $ordem): void
    {
        if (! $this->podeRegistrar($request, $usuario, $ordem)) {
            abort(403);
        }
    }

    private function autorizarConferencia(Request $request, User $usuario, OrdemCarregamento $ordem, ContratoFreteCarreto $contrato): void
    {
        if (! $this->podeConferir($request, $usuario, $ordem) || ! $this->contratoPertence($ordem, $contrato)) {
            abort(403);
        }
    }

    private function autorizarContrato(Request $request, User $usuario, OrdemCarregamento $ordem, ContratoFreteCarreto $contrato): void
    {
        if (! $this->podeRegistrar($request, $usuario, $ordem) || ! $this->contratoPertence($ordem, $contrato)) {
            abort(403);
        }
    }

    private function autorizarContexto(Request $request, User $usuario, OrdemCarregamento $ordem): void
    {
        if (! $this->cadastroOrdens->pertenceAoContexto($request, $usuario, $ordem)) {
            abort(403);
        }
    }

    private function contratoPertence(OrdemCarregamento $ordem, ContratoFreteCarreto $contrato): bool
    {
        return $contrato->ordem_carregamento_id === $ordem->id
            && $contrato->transportadora_id === $ordem->transportadora_id
            && $contrato->filial_id === $ordem->filial_id;
    }

    private function tipo(string $tipo): TipoContratoFrete
    {
        $tipo = TipoContratoFrete::tryFrom($tipo);
        if ($tipo === null) {
            throw ValidationException::withMessages(['tipo' => 'Tipo de CFC inválido.']);
        }

        return $tipo;
    }

    private function valorReferencia(OrdemCarregamento $ordem, TipoContratoFrete $tipo): string
    {
        return match ($tipo) {
            TipoContratoFrete::Adiantamento => (string) ($ordem->valor_adiantamento ?? '0.00'),
            TipoContratoFrete::Saldo => (string) ($ordem->valor_saldo ?? $this->subtrair((string) $ordem->valor_frete, (string) ($ordem->valor_adiantamento ?? '0.00'))),
        };
    }

    private function situacaoInicial(string $referencia, string $valor): SituacaoConferencia
    {
        return $this->centavos($referencia) === $this->centavos($valor) ? SituacaoConferencia::Pendente : SituacaoConferencia::Divergente;
    }

    private function normalizarValor(string|int|float $valor): string
    {
        $valor = str_replace(',', '.', trim((string) $valor));
        if (! is_numeric($valor) || (float) $valor < 0) {
            throw ValidationException::withMessages(['valor_contrato' => 'Informe um valor monetário válido.']);
        }

        return number_format($this->centavos($valor) / 100, 2, '.', '');
    }

    private function diferenca(string $referencia, string $valor): string
    {
        return number_format(($this->centavos($valor) - $this->centavos($referencia)) / 100, 2, '.', '');
    }

    private function subtrair(string $frete, string $adiantamento): string
    {
        return number_format(($this->centavos($frete) - $this->centavos($adiantamento)) / 100, 2, '.', '');
    }

    private function centavos(string $valor): int
    {
        $normalizado = str_replace(',', '.', trim($valor));
        [$inteiro, $decimal] = array_pad(explode('.', $normalizado, 2), 2, '0');

        return ((int) $inteiro * 100) + (int) str_pad(substr($decimal, 0, 2), 2, '0');
    }

    private function mascararDocumento(string $documento): string
    {
        $documento = preg_replace('/\D+/', '', $documento) ?? '';

        return strlen($documento) === 11 ? substr($documento, 0, 3).'.***.***-'.substr($documento, -2) : substr($documento, 0, 2).'.***.***/****-'.substr($documento, -2);
    }
}

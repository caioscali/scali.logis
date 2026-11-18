<?php

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Enums\SituacaoLancamentoFinanceiro;
use App\Domain\FreightContracts\Enums\TipoContratoFrete;
use App\Domain\FreightContracts\Models\ContratoFreteCarreto;
use App\Domain\LoadingOrders\Models\OrdemCarregamento;
use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\Transportadora;
use App\Models\User;
use Database\Factories\Domain\Finance\Models\LancamentoFinanceiroFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LancamentoFinanceiro extends Model
{
    /** @use HasFactory<LancamentoFinanceiroFactory> */
    use HasFactory;

    protected $table = 'lancamentos_financeiros';

    protected $fillable = [
        'transportadora_id', 'filial_id', 'ordem_carregamento_id', 'contrato_frete_carreto_id', 'tipo',
        'competencia', 'numero_cfc', 'valor', 'situacao', 'observacoes', 'aprovado_por', 'aprovado_em',
        'rejeitado_por', 'rejeitado_em', 'motivo_rejeicao', 'created_by', 'updated_by',
    ];

    protected static function newFactory(): Factory
    {
        return LancamentoFinanceiroFactory::new();
    }

    protected function casts(): array
    {
        return [
            'tipo' => TipoContratoFrete::class,
            'situacao' => SituacaoLancamentoFinanceiro::class,
            'competencia' => 'date',
            'valor' => 'decimal:2',
            'aprovado_em' => 'datetime',
            'rejeitado_em' => 'datetime',
        ];
    }

    public function transportadora(): BelongsTo
    {
        return $this->belongsTo(Transportadora::class);
    }

    public function filial(): BelongsTo
    {
        return $this->belongsTo(Filial::class);
    }

    public function ordem(): BelongsTo
    {
        return $this->belongsTo(OrdemCarregamento::class, 'ordem_carregamento_id');
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(ContratoFreteCarreto::class, 'contrato_frete_carreto_id');
    }

    public function aprovadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovado_por');
    }

    public function rejeitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejeitado_por');
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function historico(): HasMany
    {
        return $this->hasMany(HistoricoLancamentoFinanceiro::class, 'lancamento_financeiro_id')->orderBy('ocorrido_em');
    }

    public function getValorFormatadoAttribute(): string
    {
        return 'R$ '.number_format((float) $this->valor, 2, ',', '.');
    }
}

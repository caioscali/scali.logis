<?php

namespace App\Domain\FreightContracts\Models;

use App\Domain\Finance\Models\LancamentoFinanceiro;
use App\Domain\FreightContracts\Enums\SituacaoConferencia;
use App\Domain\FreightContracts\Enums\TipoContratoFrete;
use App\Domain\LoadingOrders\Models\OrdemCarregamento;
use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\Transportadora;
use App\Models\User;
use Database\Factories\Domain\FreightContracts\Models\ContratoFreteCarretoFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContratoFreteCarreto extends Model
{
    /** @use HasFactory<ContratoFreteCarretoFactory> */
    use HasFactory;

    protected $table = 'contratos_frete_carreto';

    protected $fillable = [
        'transportadora_id', 'filial_id', 'ordem_carregamento_id', 'tipo', 'numero_contrato',
        'valor_referencia', 'valor_contrato', 'situacao_conferencia', 'conferido_por', 'conferido_em',
        'observacoes', 'created_by', 'updated_by',
    ];

    protected static function newFactory(): Factory
    {
        return ContratoFreteCarretoFactory::new();
    }

    protected function casts(): array
    {
        return [
            'tipo' => TipoContratoFrete::class,
            'situacao_conferencia' => SituacaoConferencia::class,
            'valor_referencia' => 'decimal:2',
            'valor_contrato' => 'decimal:2',
            'conferido_em' => 'datetime',
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

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function atualizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function conferidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conferido_por');
    }

    public function historico(): HasMany
    {
        return $this->hasMany(HistoricoContratoFreteCarreto::class, 'contrato_frete_carreto_id');
    }

    public function lancamentoFinanceiro(): HasOne
    {
        return $this->hasOne(LancamentoFinanceiro::class, 'contrato_frete_carreto_id');
    }
}

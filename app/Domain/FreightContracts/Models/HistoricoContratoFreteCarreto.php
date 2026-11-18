<?php

namespace App\Domain\FreightContracts\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoricoContratoFreteCarreto extends Model
{
    protected $table = 'historicos_contratos_frete_carreto';

    protected $fillable = [
        'contrato_frete_carreto_id', 'tipo', 'numero_contrato', 'valor_referencia', 'valor_contrato',
        'situacao_conferencia', 'observacoes', 'alterado_por', 'alterado_em',
    ];

    protected function casts(): array
    {
        return ['valor_referencia' => 'decimal:2', 'valor_contrato' => 'decimal:2', 'alterado_em' => 'datetime'];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(ContratoFreteCarreto::class, 'contrato_frete_carreto_id');
    }

    public function alteradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'alterado_por');
    }
}

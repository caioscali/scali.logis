<?php

namespace App\Domain\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoricoLancamentoFinanceiro extends Model
{
    protected $table = 'historicos_lancamentos_financeiros';

    protected $fillable = [
        'lancamento_financeiro_id', 'acao', 'situacao_anterior', 'situacao_nova', 'valor_anterior',
        'valor_novo', 'competencia_anterior', 'competencia_nova', 'motivo', 'usuario_id', 'ocorrido_em',
    ];

    protected function casts(): array
    {
        return [
            'valor_anterior' => 'decimal:2', 'valor_novo' => 'decimal:2',
            'competencia_anterior' => 'date', 'competencia_nova' => 'date', 'ocorrido_em' => 'datetime',
        ];
    }

    public function lancamento(): BelongsTo
    {
        return $this->belongsTo(LancamentoFinanceiro::class, 'lancamento_financeiro_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}

<?php

namespace App\Domain\LoadingOrders\Models;

use App\Domain\FreightContracts\Models\ContratoFreteCarreto;
use App\Domain\LoadingOrders\Enums\SituacaoOrdem;
use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Registry\Models\ConjuntoTransporte;
use App\Domain\Registry\Models\Pessoa;
use App\Models\User;
use Database\Factories\Domain\LoadingOrders\Models\OrdemCarregamentoFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class OrdemCarregamento extends Model
{
    /** @use HasFactory<OrdemCarregamentoFactory> */
    use HasFactory;

    protected $table = 'ordens_carregamento';

    protected $fillable = [
        'numero', 'transportadora_id', 'filial_id', 'conjunto_transporte_id', 'cliente_pessoa_id', 'situacao',
        'produto', 'quantidade', 'unidade_medida', 'origem_cidade', 'origem_uf', 'local_carregamento',
        'data_carregamento', 'destino_cidade', 'destino_uf', 'local_descarga', 'data_previsao_entrega',
        'valor_frete', 'valor_adiantamento', 'valor_saldo', 'observacoes', 'created_by',
        'situacao_alterada_por', 'situacao_alterada_em', 'snapshot_motorista_nome', 'snapshot_transportador_nome',
        'snapshot_veiculo_placa', 'snapshot_implementos_placas', 'snapshot_cliente_nome', 'snapshot_conta_pagamento',
        'pdf_caminho', 'pdf_gerado_em', 'pdf_gerado_por',
    ];

    protected $hidden = ['snapshot_conta_pagamento'];

    protected static function newFactory(): Factory
    {
        return OrdemCarregamentoFactory::new();
    }

    protected static function booted(): void
    {
        static::deleting(function (OrdemCarregamento $ordem): void {
            if ($ordem->situacao !== SituacaoOrdem::Rascunho) {
                throw ValidationException::withMessages(['ordem' => 'Ordens emitidas ou canceladas não podem ser excluídas.']);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'situacao' => SituacaoOrdem::class,
            'quantidade' => 'decimal:3',
            'valor_frete' => 'decimal:2',
            'valor_adiantamento' => 'decimal:2',
            'valor_saldo' => 'decimal:2',
            'data_carregamento' => 'date',
            'data_previsao_entrega' => 'date',
            'situacao_alterada_em' => 'datetime',
            'snapshot_implementos_placas' => 'array',
            'snapshot_conta_pagamento' => 'encrypted',
            'pdf_gerado_em' => 'datetime',
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

    public function conjuntoTransporte(): BelongsTo
    {
        return $this->belongsTo(ConjuntoTransporte::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'cliente_pessoa_id');
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function situacaoAlteradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'situacao_alterada_por');
    }

    public function contratosFrete(): HasMany
    {
        return $this->hasMany(ContratoFreteCarreto::class, 'ordem_carregamento_id');
    }
}

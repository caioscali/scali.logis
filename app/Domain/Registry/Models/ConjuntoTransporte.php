<?php

namespace App\Domain\Registry\Models;

use App\Domain\Finance\Models\ContaBancaria;
use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Registry\Enums\TipoCarroceria;
use App\Domain\Registry\Enums\TipoComposicao;
use Database\Factories\Domain\Registry\Models\ConjuntoTransporteFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConjuntoTransporte extends Model
{
    /** @use HasFactory<ConjuntoTransporteFactory> */
    use HasFactory;

    protected $table = 'conjuntos_transporte';

    protected $fillable = ['transportadora_id', 'motorista_pessoa_id', 'transportador_pessoa_id', 'veiculo_id', 'tipo_composicao', 'tipo_carroceria', 'conta_bancaria_id', 'ativo'];

    protected static function newFactory(): Factory
    {
        return ConjuntoTransporteFactory::new();
    }

    protected function casts(): array
    {
        return ['tipo_composicao' => TipoComposicao::class, 'tipo_carroceria' => TipoCarroceria::class, 'ativo' => 'boolean'];
    }

    public function transportadora(): BelongsTo
    {
        return $this->belongsTo(Transportadora::class);
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'motorista_pessoa_id');
    }

    public function transportador(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'transportador_pessoa_id');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function contaBancaria(): BelongsTo
    {
        return $this->belongsTo(ContaBancaria::class);
    }

    public function implementos(): HasMany
    {
        return $this->hasMany(ConjuntoImplemento::class)->orderBy('posicao');
    }
}

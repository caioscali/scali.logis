<?php

namespace App\Domain\Finance\Models;

use App\Domain\Organizations\Models\Transportadora;
use Database\Factories\Domain\Finance\Models\TransportadoraContaBancariaFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportadoraContaBancaria extends Model
{
    /** @use HasFactory<TransportadoraContaBancariaFactory> */
    use HasFactory;

    protected $table = 'transportadoras_contas_bancarias';

    protected $fillable = ['transportadora_id', 'conta_bancaria_id', 'ativo'];

    protected static function newFactory(): Factory
    {
        return TransportadoraContaBancariaFactory::new();
    }

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function transportadora(): BelongsTo
    {
        return $this->belongsTo(Transportadora::class);
    }

    public function contaBancaria(): BelongsTo
    {
        return $this->belongsTo(ContaBancaria::class);
    }
}

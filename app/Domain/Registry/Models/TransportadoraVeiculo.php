<?php

namespace App\Domain\Registry\Models;

use App\Domain\Organizations\Models\Transportadora;
use Database\Factories\Domain\Registry\Models\TransportadoraVeiculoFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportadoraVeiculo extends Model
{
    /** @use HasFactory<TransportadoraVeiculoFactory> */
    use HasFactory;

    protected $table = 'transportadoras_veiculos';

    protected $fillable = ['transportadora_id', 'veiculo_id', 'ativo'];

    protected static function newFactory(): Factory
    {
        return TransportadoraVeiculoFactory::new();
    }

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function transportadora(): BelongsTo
    {
        return $this->belongsTo(Transportadora::class);
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }
}

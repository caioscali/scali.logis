<?php

namespace App\Domain\Registry\Models;

use App\Domain\Organizations\Models\Transportadora;
use Database\Factories\Domain\Registry\Models\VeiculoFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Veiculo extends Model
{
    /** @use HasFactory<VeiculoFactory> */
    use HasFactory;

    protected $table = 'veiculos';

    protected $fillable = ['placa', 'cidade'];

    protected static function booted(): void
    {
        static::deleting(function (Veiculo $veiculo): void {
            if ($veiculo->vinculos()->exists()) {
                throw ValidationException::withMessages(['veiculo' => 'Veículos com vínculos não podem ser excluídos.']);
            }
        });
    }

    protected static function newFactory(): Factory
    {
        return VeiculoFactory::new();
    }

    public function transportadoras(): BelongsToMany
    {
        return $this->belongsToMany(Transportadora::class, 'transportadoras_veiculos')->withPivot('ativo')->withTimestamps();
    }

    public function vinculos(): HasMany
    {
        return $this->hasMany(TransportadoraVeiculo::class);
    }
}

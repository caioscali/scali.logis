<?php

namespace App\Domain\Registry\Models;

use App\Domain\Organizations\Models\Transportadora;
use Database\Factories\Domain\Registry\Models\ImplementoFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Implemento extends Model
{
    /** @use HasFactory<ImplementoFactory> */
    use HasFactory;

    protected $table = 'implementos';

    protected $fillable = ['placa'];

    protected static function booted(): void
    {
        static::deleting(function (Implemento $implemento): void {
            if ($implemento->vinculos()->exists()) {
                throw ValidationException::withMessages(['implemento' => 'Implementos com vínculos não podem ser excluídos.']);
            }
        });
    }

    protected static function newFactory(): Factory
    {
        return ImplementoFactory::new();
    }

    public function transportadoras(): BelongsToMany
    {
        return $this->belongsToMany(Transportadora::class, 'transportadoras_implementos')->withPivot('ativo')->withTimestamps();
    }

    public function vinculos(): HasMany
    {
        return $this->hasMany(TransportadoraImplemento::class);
    }
}

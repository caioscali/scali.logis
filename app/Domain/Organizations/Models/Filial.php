<?php

namespace App\Domain\Organizations\Models;

use Database\Factories\Domain\Organizations\Models\FilialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Filial extends Model
{
    /** @use HasFactory<FilialFactory> */
    use HasFactory;

    protected $table = 'filiais';

    protected static function booted(): void
    {
        static::saving(function (Filial $filial): void {
            if ($filial->exists && $filial->isDirty('transportadora_id')) {
                throw ValidationException::withMessages(['transportadora_id' => 'A filial não pode ser transferida diretamente para outra transportadora.']);
            }

            if ($filial->exists && $filial->getOriginal('matriz') && (! $filial->matriz || ! $filial->ativo)) {
                throw ValidationException::withMessages(['matriz' => 'A filial matriz não pode perder sua condição ou ser desativada sem substituição explícita.']);
            }

            if ($filial->matriz && static::query()
                ->where('transportadora_id', $filial->transportadora_id)
                ->where('matriz', true)
                ->when($filial->exists, fn ($query) => $query->whereKeyNot($filial->getKey()))
                ->exists()) {
                throw ValidationException::withMessages(['matriz' => 'A transportadora já possui uma filial matriz.']);
            }
        });

        static::deleting(function (Filial $filial): void {
            if ($filial->matriz) {
                throw ValidationException::withMessages(['filial' => 'A filial matriz não pode ser excluída sem substituição explícita.']);
            }
        });
    }

    protected $fillable = ['transportadora_id', 'nome', 'cnpj', 'cidade', 'uf', 'matriz', 'ativo'];

    protected function casts(): array
    {
        return ['matriz' => 'boolean', 'ativo' => 'boolean'];
    }

    public function transportadora(): BelongsTo
    {
        return $this->belongsTo(Transportadora::class);
    }
}

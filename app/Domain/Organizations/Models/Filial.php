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
            if ($filial->matriz && static::query()
                ->where('transportadora_id', $filial->transportadora_id)
                ->where('matriz', true)
                ->when($filial->exists, fn ($query) => $query->whereKeyNot($filial->getKey()))
                ->exists()) {
                throw ValidationException::withMessages(['matriz' => 'A transportadora já possui uma filial matriz.']);
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

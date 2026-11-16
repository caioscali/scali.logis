<?php

namespace App\Domain\Registry\Models;

use App\Domain\Registry\Enums\TipoPessoa;
use Database\Factories\Domain\Registry\Models\PessoaFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Pessoa extends Model
{
    /** @use HasFactory<PessoaFactory> */
    use HasFactory;

    protected $table = 'pessoas';

    protected $fillable = ['tipo', 'nome', 'documento', 'telefone', 'email', 'cidade', 'uf'];

    protected static function newFactory(): Factory
    {
        return PessoaFactory::new();
    }

    protected static function booted(): void
    {
        static::deleting(function (Pessoa $pessoa): void {
            if ($pessoa->transportadoras()->exists()) {
                throw ValidationException::withMessages(['pessoa' => 'Pessoas com vínculos não podem ser excluídas.']);
            }
        });
    }

    protected function casts(): array
    {
        return ['tipo' => TipoPessoa::class];
    }

    public function transportadoras(): HasMany
    {
        return $this->hasMany(TransportadoraPessoa::class);
    }
}

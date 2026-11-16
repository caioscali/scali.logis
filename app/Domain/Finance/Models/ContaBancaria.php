<?php

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Enums\TipoChavePix;
use App\Domain\Finance\Enums\TipoConta;
use App\Domain\Registry\Models\Pessoa;
use Database\Factories\Domain\Finance\Models\ContaBancariaFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ContaBancaria extends Model
{
    /** @use HasFactory<ContaBancariaFactory> */
    use HasFactory;

    protected $table = 'contas_bancarias';

    protected $fillable = ['pessoa_id', 'banco_codigo', 'banco_nome', 'agencia', 'numero_conta', 'digito_conta', 'tipo_conta', 'tipo_chave_pix', 'chave_pix'];

    protected static function newFactory(): Factory
    {
        return ContaBancariaFactory::new();
    }

    protected static function booted(): void
    {
        static::deleting(function (ContaBancaria $conta): void {
            if ($conta->vinculos()->exists()) {
                throw ValidationException::withMessages(['conta_bancaria' => 'Contas utilizadas não podem ser excluídas.']);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'agencia' => 'encrypted',
            'numero_conta' => 'encrypted',
            'digito_conta' => 'encrypted',
            'chave_pix' => 'encrypted',
            'tipo_conta' => TipoConta::class,
            'tipo_chave_pix' => TipoChavePix::class,
        ];
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function vinculos(): HasMany
    {
        return $this->hasMany(TransportadoraContaBancaria::class);
    }
}

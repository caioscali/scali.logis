<?php

namespace App\Domain\Registry\Models;

use App\Domain\Organizations\Models\Transportadora;
use Database\Factories\Domain\Registry\Models\TransportadoraPessoaFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportadoraPessoa extends Model
{
    /** @use HasFactory<TransportadoraPessoaFactory> */
    use HasFactory;

    protected $table = 'transportadoras_pessoas';

    protected $fillable = ['transportadora_id', 'pessoa_id', 'ativo'];

    protected static function newFactory(): Factory
    {
        return TransportadoraPessoaFactory::new();
    }

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function transportadora(): BelongsTo
    {
        return $this->belongsTo(Transportadora::class);
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function funcoes(): HasMany
    {
        return $this->hasMany(PessoaFuncao::class);
    }
}

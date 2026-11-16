<?php

namespace App\Domain\Registry\Models;

use App\Domain\Registry\Enums\FuncaoPessoa;
use Database\Factories\Domain\Registry\Models\PessoaFuncaoFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PessoaFuncao extends Model
{
    /** @use HasFactory<PessoaFuncaoFactory> */
    use HasFactory;

    protected $table = 'pessoas_funcoes';

    protected $fillable = ['transportadora_pessoa_id', 'funcao'];

    protected static function newFactory(): Factory
    {
        return PessoaFuncaoFactory::new();
    }

    protected function casts(): array
    {
        return ['funcao' => FuncaoPessoa::class];
    }

    public function transportadoraPessoa(): BelongsTo
    {
        return $this->belongsTo(TransportadoraPessoa::class);
    }
}

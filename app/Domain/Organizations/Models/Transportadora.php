<?php

namespace App\Domain\Organizations\Models;

use Database\Factories\Domain\Organizations\Models\TransportadoraFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transportadora extends Model
{
    /** @use HasFactory<TransportadoraFactory> */
    use HasFactory;

    protected $fillable = ['grupo_empresarial_id', 'razao_social', 'nome_fantasia', 'cnpj', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function grupoEmpresarial(): BelongsTo
    {
        return $this->belongsTo(GrupoEmpresarial::class);
    }

    public function filiais(): HasMany
    {
        return $this->hasMany(Filial::class);
    }

    public function usuariosTransportadoras(): HasMany
    {
        return $this->hasMany(UsuarioTransportadora::class);
    }

    public function matriz(): HasMany
    {
        return $this->filiais()->where('matriz', true);
    }
}

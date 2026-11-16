<?php

namespace App\Domain\Organizations\Models;

use Database\Factories\Domain\Organizations\Models\GrupoEmpresarialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrupoEmpresarial extends Model
{
    /** @use HasFactory<GrupoEmpresarialFactory> */
    use HasFactory;

    protected $table = 'grupos_empresariais';

    protected $fillable = ['nome', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function transportadoras(): HasMany
    {
        return $this->hasMany(Transportadora::class);
    }
}

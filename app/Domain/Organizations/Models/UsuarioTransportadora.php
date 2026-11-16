<?php

namespace App\Domain\Organizations\Models;

use App\Domain\Organizations\Enums\PerfilAcesso;
use App\Models\User;
use Database\Factories\Domain\Organizations\Models\UsuarioTransportadoraFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UsuarioTransportadora extends Model
{
    /** @use HasFactory<UsuarioTransportadoraFactory> */
    use HasFactory;

    protected $table = 'usuarios_transportadoras';

    protected $fillable = ['user_id', 'transportadora_id', 'perfil', 'ativo'];

    protected function casts(): array
    {
        return ['perfil' => PerfilAcesso::class, 'ativo' => 'boolean'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function transportadora(): BelongsTo
    {
        return $this->belongsTo(Transportadora::class);
    }

    public function usuarioFiliais(): HasMany
    {
        return $this->hasMany(UsuarioFilial::class);
    }

    public function filiaisAtivas(): HasMany
    {
        return $this->usuarioFiliais()
            ->whereHas('filial', fn ($query) => $query->where('ativo', true));
    }
}

<?php

namespace App\Domain\Organizations\Models;

use Database\Factories\Domain\Organizations\Models\UsuarioFilialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class UsuarioFilial extends Model
{
    /** @use HasFactory<UsuarioFilialFactory> */
    use HasFactory;

    protected $table = 'usuarios_filiais';

    protected $fillable = ['usuario_transportadora_id', 'filial_id'];

    protected static function booted(): void
    {
        static::saving(function (UsuarioFilial $usuarioFilial): void {
            $vinculo = UsuarioTransportadora::query()->find($usuarioFilial->usuario_transportadora_id);
            $filial = Filial::query()->find($usuarioFilial->filial_id);

            if ($vinculo === null || $filial === null || $filial->transportadora_id !== $vinculo->transportadora_id) {
                throw ValidationException::withMessages(['filial_id' => 'A filial não pertence à transportadora do vínculo.']);
            }

            if (! $filial->ativo) {
                throw ValidationException::withMessages(['filial_id' => 'Uma filial inativa não pode ser autorizada.']);
            }
        });
    }

    public function usuarioTransportadora(): BelongsTo
    {
        return $this->belongsTo(UsuarioTransportadora::class);
    }

    public function filial(): BelongsTo
    {
        return $this->belongsTo(Filial::class);
    }
}

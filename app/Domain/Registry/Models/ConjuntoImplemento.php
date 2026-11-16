<?php

namespace App\Domain\Registry\Models;

use Database\Factories\Domain\Registry\Models\ConjuntoImplementoFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConjuntoImplemento extends Model
{
    /** @use HasFactory<ConjuntoImplementoFactory> */
    use HasFactory;

    protected $table = 'conjuntos_implementos';

    protected $fillable = ['conjunto_transporte_id', 'implemento_id', 'posicao'];

    protected static function newFactory(): Factory
    {
        return ConjuntoImplementoFactory::new();
    }

    protected function casts(): array
    {
        return ['posicao' => 'integer'];
    }

    public function conjunto(): BelongsTo
    {
        return $this->belongsTo(ConjuntoTransporte::class, 'conjunto_transporte_id');
    }

    public function implemento(): BelongsTo
    {
        return $this->belongsTo(Implemento::class);
    }
}

<?php

namespace App\Domain\Registry\Models;

use App\Domain\Organizations\Models\Transportadora;
use Database\Factories\Domain\Registry\Models\TransportadoraImplementoFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportadoraImplemento extends Model
{
    /** @use HasFactory<TransportadoraImplementoFactory> */
    use HasFactory;

    protected $table = 'transportadoras_implementos';

    protected $fillable = ['transportadora_id', 'implemento_id', 'ativo'];

    protected static function newFactory(): Factory
    {
        return TransportadoraImplementoFactory::new();
    }

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function transportadora(): BelongsTo
    {
        return $this->belongsTo(Transportadora::class);
    }

    public function implemento(): BelongsTo
    {
        return $this->belongsTo(Implemento::class);
    }
}

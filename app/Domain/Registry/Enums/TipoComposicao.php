<?php

namespace App\Domain\Registry\Enums;

enum TipoComposicao: string
{
    case Caminhao = 'caminhao';
    case CavaloSemirreboque = 'cavalo_semirreboque';
    case CarretaLs = 'carreta_ls';
    case Bitrem = 'bitrem';
    case Rodotrem = 'rodotrem';

    public function quantidadeImplementos(): int
    {
        return match ($this) {
            self::Caminhao => 0,
            self::CavaloSemirreboque, self::CarretaLs => 1,
            self::Bitrem => 2,
            self::Rodotrem => 3,
        };
    }

    public static function opcoes(): array
    {
        return array_map(fn (self $tipo): array => ['valor' => $tipo->value, 'nome' => $tipo->nome()], self::cases());
    }

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function nome(): string
    {
        return match ($this) {
            self::Caminhao => 'Caminhão',
            self::CavaloSemirreboque => 'Cavalo + semirreboque',
            self::CarretaLs => 'Carreta LS',
            self::Bitrem => 'Bitrem',
            self::Rodotrem => 'Rodotrem',
        };
    }
}

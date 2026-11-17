<?php

namespace App\Domain\Registry\Enums;

enum TipoCarroceria: string
{
    case Graneleiro = 'graneleiro';
    case Basculante = 'basculante';
    case Sider = 'sider';
    case Bau = 'bau';
    case Plataforma = 'plataforma';
    case Tanque = 'tanque';
    case Frigorifico = 'frigorifico';

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
            self::Graneleiro => 'Graneleiro',
            self::Basculante => 'Basculante',
            self::Sider => 'Sider',
            self::Bau => 'Baú',
            self::Plataforma => 'Plataforma',
            self::Tanque => 'Tanque',
            self::Frigorifico => 'Frigorífico',
        };
    }
}

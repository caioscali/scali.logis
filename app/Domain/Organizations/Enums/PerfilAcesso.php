<?php

namespace App\Domain\Organizations\Enums;

enum PerfilAcesso: string
{
    case Administrador = 'administrador';
    case Gerente = 'gerente';
    case Operador = 'operador';
    case Consulta = 'consulta';

    public function podeAdministrarUsuarios(): bool
    {
        return $this === self::Administrador;
    }

    public function podeAlterarDados(): bool
    {
        return in_array($this, [self::Administrador, self::Gerente, self::Operador], true);
    }

    public function podeGerenciarPessoas(): bool
    {
        return $this->podeAlterarDados();
    }

    public function podeCancelarOrdem(): bool
    {
        return in_array($this, [self::Administrador, self::Gerente], true);
    }

    public function podeAprovarLancamento(): bool
    {
        return in_array($this, [self::Administrador, self::Gerente], true);
    }

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}

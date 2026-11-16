<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class Cnpj implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cnpj = self::normalizar((string) $value);

        if ($cnpj === null || strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            $fail('O :attribute deve ser um CNPJ válido.');

            return;
        }

        $base = substr($cnpj, 0, 12);
        $primeiroDigito = self::digito($base, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $segundoDigito = self::digito($base.$primeiroDigito, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        if ($cnpj !== $base.$primeiroDigito.$segundoDigito) {
            $fail('O :attribute deve ser um CNPJ válido.');
        }
    }

    public static function normalizar(string $cnpj): ?string
    {
        $normalizado = preg_replace('/\D+/', '', $cnpj) ?? '';

        return $normalizado === '' ? null : $normalizado;
    }

    /**
     * @param  array<int, int>  $pesos
     */
    private static function digito(string $valor, array $pesos): int
    {
        $soma = 0;

        foreach (str_split($valor) as $indice => $numero) {
            $soma += (int) $numero * $pesos[$indice];
        }

        $resto = $soma % 11;

        return $resto < 2 ? 0 : 11 - $resto;
    }
}

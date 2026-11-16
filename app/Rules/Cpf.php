<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class Cpf implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cpf = self::normalizar((string) $value);

        if ($cpf === null || strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            $fail('O :attribute deve ser um CPF válido.');

            return;
        }

        $base = substr($cpf, 0, 9);
        $primeiroDigito = self::digito($base);
        $segundoDigito = self::digito($base.$primeiroDigito);

        if ($cpf !== $base.$primeiroDigito.$segundoDigito) {
            $fail('O :attribute deve ser um CPF válido.');
        }
    }

    public static function normalizar(string $cpf): ?string
    {
        $normalizado = preg_replace('/\D+/', '', $cpf) ?? '';

        return $normalizado === '' ? null : $normalizado;
    }

    private static function digito(string $valor): int
    {
        $soma = 0;
        $peso = strlen($valor) + 1;

        foreach (str_split($valor) as $numero) {
            $soma += (int) $numero * $peso--;
        }

        $resto = $soma % 11;

        return $resto < 2 ? 0 : 11 - $resto;
    }
}

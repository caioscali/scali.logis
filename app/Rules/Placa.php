<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class Placa implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $placa = self::normalizar((string) $value);

        if ($placa === null || ! preg_match('/^(?:[A-Z]{3}\d{4}|[A-Z]{3}\d[A-Z]\d{2})$/', $placa)) {
            $fail('O :attribute deve ser uma placa brasileira válida.');
        }
    }

    public static function normalizar(string $placa): ?string
    {
        $normalizada = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $placa) ?? '');

        return $normalizada === '' ? null : $normalizada;
    }
}

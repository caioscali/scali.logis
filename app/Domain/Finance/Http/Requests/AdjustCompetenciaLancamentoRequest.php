<?php

namespace App\Domain\Finance\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AdjustCompetenciaLancamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['competencia' => ['required', 'date'], 'motivo' => ['required', 'string', 'max:5000']];
    }
}

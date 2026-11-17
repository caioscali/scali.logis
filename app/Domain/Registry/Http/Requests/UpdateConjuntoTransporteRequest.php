<?php

namespace App\Domain\Registry\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConjuntoTransporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['ativo' => ['required', 'boolean']];
    }
}

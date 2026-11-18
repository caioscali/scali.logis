<?php

namespace App\Domain\FreightContracts\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContratoFreteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $ordem = $this->route('ordem');

        return [
            'tipo' => ['required', Rule::in(['adiantamento', 'saldo'])],
            'numero_contrato' => ['required', 'string', 'max:100', Rule::unique('contratos_frete_carreto', 'numero_contrato')->where(fn ($query) => $query->where('transportadora_id', $ordem?->transportadora_id))],
            'valor_contrato' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

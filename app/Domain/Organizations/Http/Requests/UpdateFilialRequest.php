<?php

namespace App\Domain\Organizations\Http\Requests;

use App\Rules\Cnpj;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFilialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdministrator() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'transportadora_id' => ['required', 'integer', 'exists:transportadoras,id'],
            'nome' => ['required', 'string', 'max:255'],
            'cnpj' => ['nullable', new Cnpj, Rule::unique('filiais', 'cnpj')->ignore($this->route('filial'))],
            'cidade' => ['required', 'string', 'max:120'],
            'uf' => ['required', 'string', 'size:2', 'uppercase'],
            'matriz' => ['sometimes', 'boolean'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['cnpj' => Cnpj::normalizar((string) $this->input('cnpj')), 'uf' => strtoupper((string) $this->input('uf'))]);
    }
}

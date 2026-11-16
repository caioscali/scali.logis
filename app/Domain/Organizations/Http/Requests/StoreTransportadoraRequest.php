<?php

namespace App\Domain\Organizations\Http\Requests;

use App\Rules\Cnpj;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransportadoraRequest extends FormRequest
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
            'grupo_empresarial_id' => ['required', 'integer', 'exists:grupos_empresariais,id'],
            'razao_social' => ['required', 'string', 'max:255'],
            'nome_fantasia' => ['required', 'string', 'max:255'],
            'cnpj' => ['nullable', new Cnpj, 'unique:transportadoras,cnpj'],
            'ativo' => ['sometimes', 'boolean'],
            'filial_matriz.nome' => ['required', 'string', 'max:255'],
            'filial_matriz.cnpj' => ['nullable', new Cnpj, 'unique:filiais,cnpj'],
            'filial_matriz.cidade' => ['required', 'string', 'max:120'],
            'filial_matriz.uf' => ['required', 'string', 'size:2', 'uppercase'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cnpj' => Cnpj::normalizar((string) $this->input('cnpj')),
            'filial_matriz' => [
                ...$this->input('filial_matriz', []),
                'cnpj' => Cnpj::normalizar((string) $this->input('filial_matriz.cnpj')),
                'uf' => strtoupper((string) $this->input('filial_matriz.uf')),
            ],
        ]);
    }
}

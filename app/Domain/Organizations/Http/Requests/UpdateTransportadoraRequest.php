<?php

namespace App\Domain\Organizations\Http\Requests;

use App\Rules\Cnpj;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransportadoraRequest extends FormRequest
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
            'cnpj' => ['nullable', new Cnpj, Rule::unique('transportadoras', 'cnpj')->ignore($this->route('transportadora'))],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['cnpj' => Cnpj::normalizar((string) $this->input('cnpj'))]);
    }
}

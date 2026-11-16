<?php

namespace App\Domain\Organizations\Http\Requests;

use App\Domain\Organizations\Enums\PerfilAcesso;
use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Services\AcessoEmpresarial;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUsuarioTransportadoraRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $transportadora = $this->route('transportadora');

        return $transportadora instanceof Transportadora
            && app(AcessoEmpresarial::class)->podeAdministrarUsuarios($this->user(), $transportadora);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
            'perfil' => ['required', Rule::in(PerfilAcesso::valores())],
            'ativo' => ['sometimes', 'boolean'],
            'filial_ids' => ['sometimes', 'array'],
            'filial_ids.*' => ['integer', 'distinct', 'exists:filiais,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }

    public function messages(): array
    {
        return ['email.exists' => 'A conta informada ainda não existe. Use o cadastro público antes de criar o vínculo.'];
    }
}

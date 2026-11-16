<?php

namespace App\Domain\Organizations\Http\Requests;

use App\Domain\Organizations\Enums\PerfilAcesso;
use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Domain\Organizations\Services\AcessoEmpresarial;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsuarioTransportadoraRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $transportadora = $this->route('transportadora');
        $vinculo = $this->route('usuarioTransportadora');

        return $transportadora instanceof Transportadora
            && $vinculo instanceof UsuarioTransportadora
            && $vinculo->transportadora_id === $transportadora->id
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
            'perfil' => ['required', Rule::in(PerfilAcesso::valores())],
            'ativo' => ['required', 'boolean'],
            'filial_ids' => ['sometimes', 'array'],
            'filial_ids.*' => ['integer', 'distinct', 'exists:filiais,id'],
        ];
    }
}

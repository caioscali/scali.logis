<?php

namespace App\Domain\Registry\Http\Requests;

use App\Domain\Organizations\Services\ContextoEmpresarial;
use App\Domain\Registry\Enums\FuncaoPessoa;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePessoaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();
        $vinculo = $this->route('transportadoraPessoa');

        return $usuario !== null
            && $vinculo !== null
            && app(ContextoEmpresarial::class)->vinculoAtual($this, $usuario)?->id === $vinculo->transportadora_id
            && app(ContextoEmpresarial::class)->vinculoAtual($this, $usuario)?->perfil->podeGerenciarPessoas() === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ativo' => ['required', 'boolean'],
            'funcoes' => ['sometimes', 'array'],
            'funcoes.*' => ['string', 'distinct', Rule::in(FuncaoPessoa::valores())],
        ];
    }
}

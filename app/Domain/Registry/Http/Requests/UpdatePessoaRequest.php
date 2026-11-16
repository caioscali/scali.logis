<?php

namespace App\Domain\Registry\Http\Requests;

use App\Domain\Organizations\Services\ContextoEmpresarial;
use App\Domain\Registry\Enums\FuncaoPessoa;
use App\Domain\Registry\Models\TransportadoraPessoa;
use App\Domain\Registry\Services\CadastroPessoas;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePessoaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();
        $vinculo = $this->route('transportadoraPessoa');
        $contexto = $usuario === null ? null : app(ContextoEmpresarial::class)->atual($this, $usuario);
        $perfil = $usuario === null ? null : app(ContextoEmpresarial::class)->vinculoAtual($this, $usuario)?->perfil;

        return $usuario !== null
            && $vinculo instanceof TransportadoraPessoa
            && $contexto !== null
            && $contexto['transportadora']['id'] === $vinculo->transportadora_id
            && $perfil?->podeGerenciarPessoas() === true
            && app(CadastroPessoas::class)->pessoaNoContexto($this, $usuario, $vinculo->pessoa);
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

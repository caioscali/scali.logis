<?php

namespace App\Domain\Registry\Http\Requests;

use App\Domain\Organizations\Services\ContextoEmpresarial;
use App\Domain\Registry\Enums\FuncaoPessoa;
use App\Domain\Registry\Enums\TipoPessoa;
use App\Rules\Cnpj;
use App\Rules\Cpf;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePessoaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        return $usuario !== null
            && app(ContextoEmpresarial::class)->vinculoAtual($this, $usuario)?->perfil->podeGerenciarPessoas() === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tipo = $this->input('tipo');

        return [
            'tipo' => ['required', Rule::enum(TipoPessoa::class)],
            'nome' => ['required', 'string', 'max:255'],
            'documento' => [
                'required',
                'string',
                $tipo === TipoPessoa::Fisica->value ? new Cpf : new Cnpj,
            ],
            'telefone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:120'],
            'uf' => ['nullable', 'string', 'size:2', 'uppercase'],
            'funcoes' => ['sometimes', 'array'],
            'funcoes.*' => ['string', 'distinct', Rule::in(FuncaoPessoa::valores())],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'documento' => preg_replace('/\D+/', '', (string) $this->input('documento')),
            'uf' => strtoupper((string) $this->input('uf')),
        ]);
    }
}

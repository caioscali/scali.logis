<?php

namespace App\Domain\Registry\Http\Requests;

use App\Domain\Organizations\Services\ContextoEmpresarial;
use App\Domain\Registry\Enums\TipoPessoa;
use App\Rules\Cnpj;
use App\Rules\Cpf;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PesquisarPessoaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && app(ContextoEmpresarial::class)->vinculoAtual($this, $this->user()) !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tipo = $this->input('tipo');

        return [
            'tipo' => ['required', Rule::enum(TipoPessoa::class)],
            'documento' => ['required', $tipo === TipoPessoa::Fisica->value ? new Cpf : new Cnpj],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['documento' => preg_replace('/\D+/', '', (string) $this->input('documento'))]);
    }
}

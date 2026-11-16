<?php

namespace App\Domain\Finance\Http\Requests;

use App\Domain\Finance\Enums\TipoChavePix;
use App\Domain\Finance\Enums\TipoConta;
use App\Domain\Organizations\Services\ContextoEmpresarial;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContaBancariaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && app(ContextoEmpresarial::class)->vinculoAtual($this, $this->user())?->perfil->podeGerenciarPessoas() === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pessoa_id' => ['required', 'integer', 'exists:pessoas,id'],
            'conta_bancaria_id' => ['nullable', 'integer', 'exists:contas_bancarias,id'],
            'banco_codigo' => ['nullable', 'string', 'regex:/^\d{3,10}$/'],
            'banco_nome' => ['nullable', 'string', 'max:255'],
            'agencia' => ['nullable', 'string', 'max:30'],
            'numero_conta' => ['nullable', 'string', 'max:40'],
            'digito_conta' => ['nullable', 'string', 'max:5'],
            'tipo_conta' => ['nullable', Rule::enum(TipoConta::class)],
            'tipo_chave_pix' => ['nullable', Rule::enum(TipoChavePix::class)],
            'chave_pix' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'banco_codigo' => preg_replace('/\D+/', '', (string) $this->input('banco_codigo')) ?: null,
            'agencia' => preg_replace('/\D+/', '', (string) $this->input('agencia')) ?: null,
            'numero_conta' => preg_replace('/\D+/', '', (string) $this->input('numero_conta')) ?: null,
            'digito_conta' => preg_replace('/\D+/', '', (string) $this->input('digito_conta')) ?: null,
            'chave_pix' => trim((string) $this->input('chave_pix')) ?: null,
        ]);
    }
}

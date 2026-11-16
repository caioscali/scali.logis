<?php

namespace App\Domain\Finance\Http\Requests;

use App\Domain\Organizations\Services\ContextoEmpresarial;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PesquisarContasPessoaRequest extends FormRequest
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
        return ['pessoa_id' => ['required', 'integer', 'exists:pessoas,id']];
    }
}

<?php

namespace App\Domain\Registry\Http\Requests;

use App\Domain\Organizations\Services\ContextoEmpresarial;
use App\Rules\Placa;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PesquisarEquipamentoRequest extends FormRequest
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
        return ['placa' => ['required', new Placa]];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['placa' => Placa::normalizar((string) $this->input('placa'))]);
    }
}

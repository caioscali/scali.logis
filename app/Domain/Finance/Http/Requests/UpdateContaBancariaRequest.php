<?php

namespace App\Domain\Finance\Http\Requests;

use App\Domain\Finance\Models\TransportadoraContaBancaria;
use App\Domain\Organizations\Services\ContextoEmpresarial;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateContaBancariaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();
        $vinculo = $this->route('transportadoraContaBancaria');

        if ($usuario === null || ! $vinculo instanceof TransportadoraContaBancaria) {
            return false;
        }

        $contexto = app(ContextoEmpresarial::class)->atual($this, $usuario);
        $perfil = app(ContextoEmpresarial::class)->vinculoAtual($this, $usuario)?->perfil;

        return $contexto !== null
            && $contexto['transportadora']['id'] === $vinculo->transportadora_id
            && $perfil?->podeGerenciarPessoas() === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['ativo' => ['required', 'boolean']];
    }
}

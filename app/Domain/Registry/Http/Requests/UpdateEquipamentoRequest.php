<?php

namespace App\Domain\Registry\Http\Requests;

use App\Domain\Organizations\Services\ContextoEmpresarial;
use App\Domain\Registry\Models\TransportadoraImplemento;
use App\Domain\Registry\Models\TransportadoraVeiculo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEquipamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();
        $vinculo = $this->route('transportadoraVeiculo') ?? $this->route('transportadoraImplemento');

        if ($usuario === null || ! ($vinculo instanceof TransportadoraVeiculo || $vinculo instanceof TransportadoraImplemento)) {
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

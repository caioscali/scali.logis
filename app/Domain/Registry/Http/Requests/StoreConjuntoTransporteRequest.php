<?php

namespace App\Domain\Registry\Http\Requests;

use App\Domain\Organizations\Services\ContextoEmpresarial;
use App\Domain\Registry\Enums\TipoCarroceria;
use App\Domain\Registry\Enums\TipoComposicao;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreConjuntoTransporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && app(ContextoEmpresarial::class)->vinculoAtual($this, $this->user())?->perfil->podeAlterarDados() === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'motorista_pessoa_id' => ['required', 'integer', 'exists:pessoas,id'],
            'transportador_pessoa_id' => ['required', 'integer', 'exists:pessoas,id'],
            'veiculo_id' => ['required', 'integer', 'exists:veiculos,id'],
            'tipo_composicao' => ['required', 'string', 'in:'.implode(',', TipoComposicao::valores())],
            'tipo_carroceria' => ['required', 'string', 'in:'.implode(',', TipoCarroceria::valores())],
            'conta_bancaria_id' => ['required', 'integer', 'exists:contas_bancarias,id'],
            'implementos' => ['array'],
            'implementos.*.implemento_id' => ['required', 'integer', 'exists:implementos,id'],
            'implementos.*.posicao' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }
}

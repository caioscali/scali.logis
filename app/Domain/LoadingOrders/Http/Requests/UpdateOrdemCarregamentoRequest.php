<?php

namespace App\Domain\LoadingOrders\Http\Requests;

use App\Domain\LoadingOrders\Models\OrdemCarregamento;
use App\Domain\LoadingOrders\Services\CadastroOrdensCarregamento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOrdemCarregamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ordem = $this->route('ordem');

        return $ordem instanceof OrdemCarregamento && $this->user() !== null && app(CadastroOrdensCarregamento::class)->podeCriar($this, $this->user());
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return StoreOrdemCarregamentoRequest::campos();
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['valor_frete', 'valor_adiantamento', 'data_carregamento', 'data_previsao_entrega'])) {
                return;
            }
            if ($this->filled('valor_frete') && $this->filled('valor_adiantamento') && $this->centavos((string) $this->input('valor_adiantamento')) > $this->centavos((string) $this->input('valor_frete'))) {
                $validator->errors()->add('valor_adiantamento', 'O adiantamento não pode ser superior ao valor do frete.');
            }
            if ($this->filled('data_carregamento') && $this->filled('data_previsao_entrega') && $this->date('data_previsao_entrega')->lt($this->date('data_carregamento'))) {
                $validator->errors()->add('data_previsao_entrega', 'A previsão de entrega não pode ser anterior ao carregamento.');
            }
        }];
    }

    private function centavos(string $valor): int
    {
        [$inteiro, $decimal] = array_pad(explode('.', str_replace(',', '.', $valor), 2), 2, '0');

        return ((int) $inteiro * 100) + (int) str_pad(substr($decimal, 0, 2), 2, '0');
    }
}

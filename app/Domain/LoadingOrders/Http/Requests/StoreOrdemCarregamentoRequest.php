<?php

namespace App\Domain\LoadingOrders\Http\Requests;

use App\Domain\LoadingOrders\Services\CadastroOrdensCarregamento;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOrdemCarregamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && app(CadastroOrdensCarregamento::class)->podeCriar($this, $this->user());
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return self::campos();
    }

    /** @return array<string, array<int, string>> */
    public static function campos(): array
    {
        return [
            'conjunto_transporte_id' => ['nullable', 'integer', 'exists:conjuntos_transporte,id'],
            'cliente_pessoa_id' => ['nullable', 'integer', 'exists:pessoas,id'],
            'produto' => ['nullable', 'string', 'max:255'],
            'quantidade' => ['nullable', 'numeric', 'min:0'],
            'unidade_medida' => ['nullable', 'string', 'max:30'],
            'origem_cidade' => ['nullable', 'string', 'max:120'],
            'origem_uf' => ['nullable', 'string', 'size:2'],
            'local_carregamento' => ['nullable', 'string', 'max:255'],
            'data_carregamento' => ['nullable', 'date'],
            'destino_cidade' => ['nullable', 'string', 'max:120'],
            'destino_uf' => ['nullable', 'string', 'size:2'],
            'local_descarga' => ['nullable', 'string', 'max:255'],
            'data_previsao_entrega' => ['nullable', 'date'],
            'valor_frete' => ['nullable', 'numeric', 'min:0'],
            'valor_adiantamento' => ['nullable', 'numeric', 'min:0'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
        ];
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

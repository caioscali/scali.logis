<?php

namespace App\Domain\LoadingOrders\Services;

use App\Domain\LoadingOrders\Contracts\GoogleDriveClient;
use App\Domain\LoadingOrders\Contracts\GoogleSheetsClient;
use App\Domain\LoadingOrders\Enums\SituacaoOrdem;
use App\Domain\LoadingOrders\Exceptions\GeracaoPdfOrdemException;
use App\Domain\LoadingOrders\Models\OrdemCarregamento;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GeradorPdfOrdem
{
    public function __construct(
        private GoogleSheetsClient $sheets,
        private GoogleDriveClient $drive,
        private MapeamentoOrdem $mapeamento,
    ) {}

    public function gerar(OrdemCarregamento $ordem, User $usuario): OrdemCarregamento
    {
        if ($ordem->situacao !== SituacaoOrdem::Emitida) {
            throw new GeracaoPdfOrdemException('O PDF só pode ser gerado para ordens emitidas.');
        }
        if ($ordem->pdf_caminho !== null && Storage::disk('local')->exists($ordem->pdf_caminho)) {
            return $ordem;
        }

        return Cache::lock("ordem-pdf:{$ordem->id}", 120)->block(5, function () use ($ordem, $usuario): OrdemCarregamento {
            $ordem->refresh();
            if ($ordem->pdf_caminho !== null && Storage::disk('local')->exists($ordem->pdf_caminho)) {
                return $ordem;
            }
            $templateId = config('google.sheets.template_id');
            if (! is_string($templateId) || $templateId === '') {
                throw new GeracaoPdfOrdemException('A planilha-modelo ainda não foi configurada.');
            }
            $temporaria = null;
            try {
                $temporaria = $this->sheets->copiarModelo($templateId, "OC-{$ordem->numero}-temporaria", config('google.sheets.folder_id'));
                $this->sheets->preencher($temporaria, $this->mapeamento->valores($ordem));
                $pdf = $this->drive->exportarPdf($temporaria);
                if (! str_starts_with($pdf, '%PDF-') || strlen($pdf) < 6) {
                    throw new GeracaoPdfOrdemException('A Google API retornou um PDF inválido.');
                }
                $path = $this->caminho($ordem);
                Storage::disk('local')->put($path, $pdf);
                $ordem->forceFill(['pdf_caminho' => $path, 'pdf_gerado_em' => now(), 'pdf_gerado_por' => $usuario->id])->save();

                return $ordem->fresh();
            } catch (GeracaoPdfOrdemException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                Log::error('Falha na geração do PDF da ordem de carregamento.', ['ordem_id' => $ordem->id, 'exception' => $exception::class]);
                throw new GeracaoPdfOrdemException('Não foi possível gerar o PDF da ordem. Tente novamente.');
            } finally {
                if ($temporaria !== null) {
                    try {
                        $this->sheets->remover($temporaria);
                    } catch (Throwable $exception) {
                        Log::warning('Não foi possível remover a planilha temporária da ordem.', ['ordem_id' => $ordem->id, 'exception' => $exception::class]);
                    }
                }
            }
        });
    }

    private function caminho(OrdemCarregamento $ordem): string
    {
        $numero = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $ordem->numero) ?: (string) $ordem->id;

        return "ordens-carregamento/{$ordem->transportadora_id}/{$ordem->filial_id}/{$numero}/ORDEM-CARREGAMENTO-{$numero}.pdf";
    }
}

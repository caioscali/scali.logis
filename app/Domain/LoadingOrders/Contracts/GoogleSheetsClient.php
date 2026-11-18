<?php

namespace App\Domain\LoadingOrders\Contracts;

interface GoogleSheetsClient
{
    public function copiarModelo(string $templateId, string $nome, ?string $folderId = null): string;

    /** @param array<int, array{range: string, values: array<int, array<int, string|int|float|null>>}> $valores */
    public function preencher(string $spreadsheetId, array $valores): void;

    public function remover(string $spreadsheetId): void;
}

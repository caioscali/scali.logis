<?php

namespace App\Domain\LoadingOrders\Contracts;

interface GoogleDriveClient
{
    public function exportarPdf(string $spreadsheetId): string;
}

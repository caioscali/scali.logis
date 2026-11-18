<?php

namespace App\Domain\LoadingOrders\Services;

use App\Domain\LoadingOrders\Contracts\GoogleDriveClient;
use App\Domain\LoadingOrders\Contracts\GoogleSheetsClient;
use App\Domain\LoadingOrders\Exceptions\GeracaoPdfOrdemException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GoogleApiClient implements GoogleDriveClient, GoogleSheetsClient
{
    private ?string $token = null;

    public function copiarModelo(string $templateId, string $nome, ?string $folderId = null): string
    {
        $body = ['name' => $nome];
        if ($folderId !== null && $folderId !== '') {
            $body['parents'] = [$folderId];
        }
        $response = $this->drive()->post("files/{$templateId}/copy", $body)->throw();

        return (string) $response->json('id');
    }

    public function preencher(string $spreadsheetId, array $valores): void
    {
        $this->sheets()->post("spreadsheets/{$spreadsheetId}/values:batchUpdate", [
            'valueInputOption' => 'RAW',
            'data' => $valores,
        ])->throw();
    }

    public function remover(string $spreadsheetId): void
    {
        $this->drive()->delete("files/{$spreadsheetId}")->throw();
    }

    public function exportarPdf(string $spreadsheetId): string
    {
        return $this->drive()->withHeaders(['Accept' => 'application/pdf'])
            ->get("files/{$spreadsheetId}/export", ['mimeType' => 'application/pdf'])->throw()->body();
    }

    private function sheets(): PendingRequest
    {
        return $this->request('https://sheets.googleapis.com/v4/');
    }

    private function drive(): PendingRequest
    {
        return $this->request('https://www.googleapis.com/drive/v3/');
    }

    private function request(string $baseUrl): PendingRequest
    {
        return Http::baseUrl($baseUrl)->withToken($this->accessToken())->timeout(config('google.timeout'));
    }

    private function accessToken(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }
        $path = config('google.credentials');
        if (! is_string($path) || ! is_readable($path)) {
            throw new GeracaoPdfOrdemException('A integração Google não está configurada.');
        }
        $credentials = json_decode((string) file_get_contents($path), true);
        if (! is_array($credentials) || blank($credentials['client_email'] ?? null) || blank($credentials['private_key'] ?? null)) {
            throw new GeracaoPdfOrdemException('As credenciais Google são inválidas.');
        }
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $claims = rtrim(strtr(base64_encode(json_encode(['iss' => $credentials['client_email'], 'scope' => 'https://www.googleapis.com/auth/drive https://www.googleapis.com/auth/spreadsheets', 'aud' => 'https://oauth2.googleapis.com/token', 'exp' => time() + 3600, 'iat' => time()])), '+/', '-_'), '=');
        if (! openssl_sign("{$header}.{$claims}", $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new GeracaoPdfOrdemException('Não foi possível autenticar na integração Google.');
        }
        $jwt = "{$header}.{$claims}.".rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        $response = Http::asForm()->timeout(config('google.timeout'))->post('https://oauth2.googleapis.com/token', ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt])->throw();

        return $this->token = (string) $response->json('access_token');
    }
}

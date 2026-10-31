<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal Google Sheets v4 client authenticated as a service account
 * (JWT bearer grant signed with openssl) — no Google SDK needed. Only the
 * calls the Live Excel sync uses.
 */
class GoogleSheetsClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/spreadsheets';

    private const API = 'https://sheets.googleapis.com/v4/spreadsheets/';

    private ?array $key = null;

    public function configured(): bool
    {
        return is_file((string) config('google-sheets.credentials'));
    }

    /** The service account's email — the address the sheet must be shared with. */
    public function serviceAccountEmail(): ?string
    {
        return $this->configured() ? ($this->key()['client_email'] ?? null) : null;
    }

    /**
     * @return array{title: string, tabs: array<string, array{id: int, rowGroups: array, protected: bool, protections: array<int, int>, charts: array<int, int>, conditionalFormats: int}>} keyed by tab title
     */
    public function spreadsheet(string $id): array
    {
        $data = $this->request()->get(self::API.rawurlencode($id), [
            'fields' => 'properties.title,sheets(properties(sheetId,title),rowGroups,protectedRanges(protectedRangeId),charts(chartId),conditionalFormats(ranges))',
        ])->throw()->json();

        return [
            'title' => (string) ($data['properties']['title'] ?? ''),
            'tabs' => collect($data['sheets'] ?? [])->mapWithKeys(fn ($s) => [$s['properties']['title'] => [
                'id' => (int) $s['properties']['sheetId'],
                'rowGroups' => $s['rowGroups'] ?? [],
                'protected' => ! empty($s['protectedRanges']),
                'protections' => array_map(fn ($p) => (int) $p['protectedRangeId'], $s['protectedRanges'] ?? []),
                'charts' => array_map(fn ($c) => (int) $c['chartId'], $s['charts'] ?? []),
                'conditionalFormats' => count($s['conditionalFormats'] ?? []),
            ]])->all(),
        ];
    }

    /** @return array<int, array<int, mixed>> the cells of $range as shown (rows of columns) */
    public function values(string $id, string $range): array
    {
        return $this->request()->get(self::API.rawurlencode($id).'/values/'.rawurlencode($range))->throw()->json('values') ?? [];
    }

    /** Runs spreadsheets.batchUpdate (add tab, formatting, …). */
    public function batchUpdate(string $id, array $requests): array
    {
        return $this->request()->post(self::API.rawurlencode($id).':batchUpdate', ['requests' => $requests])->throw()->json();
    }

    /**
     * Clears $clearRange, then writes $values starting at $range. RAW stores
     * values as-is; USER_ENTERED makes "=…" cells live formulas.
     */
    public function replaceValues(string $id, string $clearRange, string $range, array $values, string $input = 'RAW'): void
    {
        // Google rejects the "[]" Laravel sends for an empty body; it wants "{}".
        $this->request()->withBody('{}', 'application/json')->post(self::API.rawurlencode($id).'/values/'.rawurlencode($clearRange).':clear')->throw();
        $this->request()->put(self::API.rawurlencode($id).'/values/'.rawurlencode($range).'?valueInputOption='.$input, [
            'range' => $range,
            'majorDimension' => 'ROWS',
            'values' => $values,
        ])->throw();
    }

    /** Turns an API failure into a short, admin-readable message. */
    public static function describe(\Throwable $e): string
    {
        if ($e instanceof RequestException) {
            $status = $e->response->status();
            $message = (string) ($e->response->json('error.message') ?? $e->getMessage());

            return match ($status) {
                403 => 'Google refused access — share the sheet with the service account email as Editor. ('.$message.')',
                404 => 'Google Sheet not found — check the link / ID. ('.$message.')',
                default => "Google API error {$status}: {$message}",
            };
        }

        return $e->getMessage();
    }

    private function request(): PendingRequest
    {
        // Network blips, rate limits (429) and Google 5xx are retried; other errors fail at once.
        return Http::timeout((int) config('google-sheets.timeout', 20))
            ->retry(3, 2000, fn ($e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError())))
            ->acceptJson()
            ->withToken($this->accessToken());
    }

    private function accessToken(): string
    {
        $key = $this->key();

        return Cache::remember('google-sheets:token:'.md5($key['client_email']), 3300, function () use ($key) {
            $now = time();
            $tokenUri = $key['token_uri'] ?? 'https://oauth2.googleapis.com/token';
            $segments = [
                $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                $this->base64Url(json_encode([
                    'iss' => $key['client_email'],
                    'scope' => self::SCOPE,
                    'aud' => $tokenUri,
                    'iat' => $now,
                    'exp' => $now + 3600,
                ])),
            ];
            if (! openssl_sign(implode('.', $segments), $signature, $key['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Could not sign the Google service-account token (invalid private key).');
            }
            $segments[] = $this->base64Url($signature);

            $token = Http::asForm()->timeout((int) config('google-sheets.timeout', 20))->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ])->throw()->json('access_token');

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('Google did not return an access token.');
            }

            return $token;
        });
    }

    private function key(): array
    {
        if ($this->key !== null) {
            return $this->key;
        }

        $path = (string) config('google-sheets.credentials');
        $key = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (! is_array($key) || empty($key['client_email']) || empty($key['private_key'])) {
            throw new RuntimeException("Google service-account key missing or invalid at {$path}.");
        }

        return $this->key = $key;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

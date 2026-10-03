<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Secrets\Drivers;

use Illuminate\Support\Facades\Http;
use SashaLenz\SettingsUi\Contracts\SecretStore;
use SashaLenz\SettingsUi\Exceptions\SecretUnavailableException;
use SashaLenz\SettingsUi\Secrets\SecretLocation;
use Throwable;

/**
 * Driver that resolves secrets from HashiCorp Vault via HTTP API (v1 / v2 kv engines).
 */
final class VaultSecretDriver implements SecretStore
{
    public function __construct(
        private readonly string $addr,
        private readonly string $token,
        private readonly string $mount = 'secret',
        private readonly int $version = 2,
    ) {}

    public function get(SecretLocation $location): ?string
    {
        try {
            $path = ltrim($location->ref, '/');
            $url = $this->version === 2
                ? rtrim($this->addr, '/')."/v1/{$this->mount}/data/{$path}"
                : rtrim($this->addr, '/')."/v1/{$this->mount}/{$path}";

            $response = Http::withToken($this->token)
                ->timeout(5)
                ->get($url);

            if ($response->status() === 404) {
                return null;
            }

            if (! $response->successful()) {
                throw new SecretUnavailableException("Vault returned HTTP {$response->status()}: {$response->body()}");
            }

            $data = $response->json();
            $fields = $this->version === 2 ? ($data['data']['data'] ?? []) : ($data['data'] ?? []);

            if ($location->field !== null) {
                return isset($fields[$location->field]) ? (string) $fields[$location->field] : null;
            }

            // If no field specified and single key, return first value or json
            return count($fields) === 1 ? (string) reset($fields) : json_encode($fields, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            if ($e instanceof SecretUnavailableException) {
                throw $e;
            }

            throw new SecretUnavailableException("Vault connection error: {$e->getMessage()}", previous: $e);
        }
    }

    public function put(SecretLocation $location, string $value): void
    {
        try {
            $path = ltrim($location->ref, '/');
            $url = $this->version === 2
                ? rtrim($this->addr, '/')."/v1/{$this->mount}/data/{$path}"
                : rtrim($this->addr, '/')."/v1/{$this->mount}/{$path}";

            $payload = $location->field !== null ? [$location->field => $value] : ['value' => $value];
            $body = $this->version === 2 ? ['data' => $payload] : $payload;

            $response = Http::withToken($this->token)
                ->timeout(5)
                ->post($url, $body);

            if (! $response->successful()) {
                throw new SecretUnavailableException("Vault put failed with HTTP {$response->status()}: {$response->body()}");
            }
        } catch (Throwable $e) {
            throw new SecretUnavailableException("Vault write error: {$e->getMessage()}", previous: $e);
        }
    }

    public function forget(SecretLocation $location): void
    {
        try {
            $path = ltrim($location->ref, '/');
            $url = $this->version === 2
                ? rtrim($this->addr, '/')."/v1/{$this->mount}/metadata/{$path}"
                : rtrim($this->addr, '/')."/v1/{$this->mount}/{$path}";

            Http::withToken($this->token)
                ->timeout(5)
                ->delete($url);
        } catch (Throwable $e) {
            throw new SecretUnavailableException("Vault delete error: {$e->getMessage()}", previous: $e);
        }
    }

    public function healthy(): bool
    {
        try {
            $res = Http::withToken($this->token)->timeout(2)->get(rtrim($this->addr, '/').'/v1/sys/health');

            return in_array($res->status(), [200, 429, 473], true);
        } catch (Throwable) {
            return false;
        }
    }
}

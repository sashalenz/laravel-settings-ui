<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Secrets\Drivers;

use SashaLenz\SettingsUi\Contracts\SecretStore;
use SashaLenz\SettingsUi\Exceptions\SecretUnavailableException;
use SashaLenz\SettingsUi\Secrets\SecretLocation;
use Throwable;

/**
 * Driver that resolves secrets from AWS Secrets Manager using AWS SDK PHP.
 */
final class AwsSecretsManagerSecretDriver implements SecretStore
{
    /** @var mixed AWS client instance */
    private mixed $client = null;

    /**
     * @param  array<string, mixed>  $config  AWS client options (region, credentials, etc.)
     */
    public function __construct(
        private readonly array $config = [],
    ) {}

    public function get(SecretLocation $location): ?string
    {
        $this->ensureClient();

        try {
            $result = $this->client->getSecretValue([
                'SecretId' => $location->ref,
            ]);

            $secretString = $result['SecretString'] ?? null;

            if ($secretString === null) {
                return null;
            }

            if ($location->field !== null) {
                $decoded = json_decode((string) $secretString, true);
                if (is_array($decoded) && isset($decoded[$location->field])) {
                    return (string) $decoded[$location->field];
                }

                return null;
            }

            return (string) $secretString;
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'ResourceNotFoundException')) {
                return null;
            }

            throw new SecretUnavailableException("AWS Secrets Manager error: {$msg}", previous: $e);
        }
    }

    public function put(SecretLocation $location, string $value): void
    {
        $this->ensureClient();

        try {
            $secretString = $value;
            if ($location->field !== null) {
                $existing = $this->get(new SecretLocation($location->ref));
                $data = $existing ? (json_decode($existing, true) ?: []) : [];
                $data[$location->field] = $value;
                $secretString = (string) json_encode($data);
            }

            try {
                $this->client->putSecretValue([
                    'SecretId' => $location->ref,
                    'SecretString' => $secretString,
                ]);
            } catch (Throwable) {
                $this->client->createSecret([
                    'Name' => $location->ref,
                    'SecretString' => $secretString,
                ]);
            }
        } catch (Throwable $e) {
            throw new SecretUnavailableException("AWS Secrets Manager write error: {$e->getMessage()}", previous: $e);
        }
    }

    public function forget(SecretLocation $location): void
    {
        $this->ensureClient();

        try {
            $this->client->deleteSecret([
                'SecretId' => $location->ref,
                'RecoveryWindowInDays' => 7,
            ]);
        } catch (Throwable $e) {
            throw new SecretUnavailableException("AWS Secrets Manager delete error: {$e->getMessage()}", previous: $e);
        }
    }

    public function healthy(): bool
    {
        try {
            $this->ensureClient();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function ensureClient(): void
    {
        if ($this->client !== null) {
            return;
        }

        $class = 'Aws\\SecretsManager\\SecretsManagerClient';
        if (! class_exists($class)) {
            throw new SecretUnavailableException('Package aws/aws-sdk-php is required to use AwsSecretsManagerSecretDriver.');
        }

        $this->client = new $class(array_merge([
            'version' => 'latest',
            'region' => config('services.aws.region', 'us-east-1'),
        ], $this->config));
    }
}

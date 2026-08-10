<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Application\Services;

use App\Modules\AdvertiserWallet\Infrastructure\Models\GeniusPayConfiguration;
use App\Shared\Payments\PaymentProviderContract;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class GeniusPayConfigurationService
{
    /** @return array<string, mixed> */
    public function status(): array
    {
        $configuration = GeniusPayConfiguration::query()->latest('updated_at')->first();
        if ($configuration === null && (! $data['api_key'] || ! $data['api_secret'] || ! $data['webhook_secret'])) {
            throw ValidationException::withMessages([
                'api_key' => 'Les trois clés sandbox sont obligatoires lors de la première configuration.',
            ]);
        }

        return [
            'environment' => 'sandbox',
            'configured' => $configuration !== null,
            'api_key_configured' => $configuration?->api_key !== null,
            'api_secret_configured' => $configuration?->api_secret !== null,
            'webhook_secret_configured' => $configuration?->webhook_secret !== null,
            'api_key_hint' => $configuration !== null ? $this->mask($configuration->api_key) : null,
            'updated_at' => $configuration?->updated_at,
            'webhook_url' => rtrim((string) config('app.url'), '/').'/api/webhooks/geniuspay',
        ];
    }

    /** @param array<string, string|null> $data */
    public function save(array $data, string $actorAccountId): array
    {
        $configuration = GeniusPayConfiguration::query()->latest('updated_at')->first();
        $values = [
            'environment' => 'sandbox',
            'api_key' => $data['api_key'] ?: $configuration?->api_key,
            'api_secret' => $data['api_secret'] ?: $configuration?->api_secret,
            'webhook_secret' => $data['webhook_secret'] ?: $configuration?->webhook_secret,
            'updated_by' => $actorAccountId,
        ];

        $configuration === null
            ? GeniusPayConfiguration::query()->create($values)
            : $configuration->update($values);

        $this->applyToRuntime(GeniusPayConfiguration::query()->latest('updated_at')->firstOrFail());
        Log::info('geniuspay.sandbox_configuration_updated', ['actor_account_id' => $actorAccountId]);

        return $this->status();
    }

    /** @return array<string, mixed> */
    public function testConnection(): array
    {
        return app(PaymentProviderContract::class)->testConnection();
    }

    public function applyToRuntime(GeniusPayConfiguration $configuration): void
    {
        config([
            'services.geniuspay.environment' => 'sandbox',
            'services.geniuspay.api_key' => $configuration->api_key,
            'services.geniuspay.api_secret' => $configuration->api_secret,
            'services.geniuspay.webhook_secret' => $configuration->webhook_secret,
        ]);
    }

    private function mask(string $value): string
    {
        return mb_substr($value, 0, min(10, mb_strlen($value))).'••••••••';
    }
}

<?php

namespace App\Modules\Wallet\Application\Services;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Wallet\Infrastructure\Models\PaymentProviderConfiguration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

final class GeniusPayConfigurationService
{
    private const PROVIDER = 'geniuspay';

    /** @var list<string> */
    private const ENVIRONMENTS = ['sandbox', 'production'];

    /** @return array<string, mixed> */
    public function runtime(): array
    {
        $active = PaymentProviderConfiguration::query()
            ->where('provider', self::PROVIDER)
            ->where('is_active', true)
            ->first();

        if ($active instanceof PaymentProviderConfiguration) {
            return $this->effectiveConfiguration($active->environment, $active);
        }

        return $this->effectiveConfiguration($this->fallbackEnvironment(), null);
    }

    /** @return array<string, mixed> */
    public function summary(string $environment): array
    {
        $environment = $this->environment($environment);
        $configuration = PaymentProviderConfiguration::query()
            ->where('provider', self::PROVIDER)
            ->where('environment', $environment)
            ->first();
        $activeStoredEnvironment = PaymentProviderConfiguration::query()
            ->where('provider', self::PROVIDER)
            ->where('is_active', true)
            ->value('environment');
        $isActive = is_string($activeStoredEnvironment)
            ? $activeStoredEnvironment === $environment
            : $this->fallbackEnvironment() === $environment;
        $effective = $this->effectiveConfiguration($environment, $configuration);

        return [
            'environment' => $environment,
            'label' => $environment === 'sandbox' ? 'Sandbox' : 'Production',
            'configured' => $effective['api_key'] !== ''
                && $effective['api_secret'] !== ''
                && $effective['webhook_secret'] !== '',
            'apiKeyConfigured' => $effective['api_key'] !== '',
            'apiSecretConfigured' => $effective['api_secret'] !== '',
            'webhookSecretConfigured' => $effective['webhook_secret'] !== '',
            'apiKeyMask' => $this->mask((string) $effective['api_key']),
            'apiSecretMask' => $this->mask((string) $effective['api_secret']),
            'webhookSecretMask' => $this->mask((string) $effective['webhook_secret']),
            'baseUrl' => $effective['base_url'],
            'isActive' => $isActive,
            'storedInDatabase' => $configuration !== null,
            'lastTestStatus' => $configuration?->last_test_status,
            'lastTestMessage' => $configuration?->last_test_message,
            'lastTestedAt' => $configuration?->last_tested_at?->toIso8601String(),
            'activatedAt' => $configuration?->activated_at?->toIso8601String(),
        ];
    }

    /**
     * @param array{api_key?:string|null,api_secret?:string|null,webhook_secret?:string|null} $data
     */
    public function save(string $environment, array $data, Account $actor): PaymentProviderConfiguration
    {
        $environment = $this->environment($environment);

        return DB::transaction(function () use ($environment, $data, $actor): PaymentProviderConfiguration {
            $configuration = PaymentProviderConfiguration::query()->firstOrNew([
                'provider' => self::PROVIDER,
                'environment' => $environment,
            ]);
            $effective = $this->effectiveConfiguration($environment, $configuration->exists ? $configuration : null);

            foreach (['api_key', 'api_secret', 'webhook_secret'] as $field) {
                $value = trim((string) ($data[$field] ?? ''));

                if ($value !== '') {
                    $configuration->{$field} = $value;
                } elseif (! $configuration->exists && (string) $effective[$field] !== '') {
                    $configuration->{$field} = (string) $effective[$field];
                }
            }

            $configuration->base_url = $this->defaultBaseUrl();
            $configuration->checkout_hosts = $this->defaultCheckoutHosts();
            $configuration->updated_by_account_id = $actor->id;
            $configuration->save();

            return $configuration->refresh();
        });
    }

    /** @return array{ok:bool,message:string,httpStatus:int|null,accountStatus:string|null,environment:string|null} */
    public function test(string $environment, Account $actor): array
    {
        $environment = $this->environment($environment);
        $configuration = PaymentProviderConfiguration::query()->firstOrNew([
            'provider' => self::PROVIDER,
            'environment' => $environment,
        ]);
        $effective = $this->effectiveConfiguration($environment, $configuration->exists ? $configuration : null);

        if ($effective['api_key'] === '' || $effective['api_secret'] === '') {
            throw ValidationException::withMessages([
                'credentials' => 'Renseigne la clé API et le secret API avant de tester la connexion.',
            ]);
        }

        $result = [
            'ok' => false,
            'message' => 'GeniusPay est injoignable.',
            'httpStatus' => null,
            'accountStatus' => null,
            'environment' => null,
        ];

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'X-API-Key' => (string) $effective['api_key'],
                    'X-API-Secret' => (string) $effective['api_secret'],
                ])
                ->connectTimeout(5)
                ->timeout(15)
                ->get(rtrim((string) $effective['base_url'], '/').'/account');

            $providerEnvironment = strtolower(trim((string) $response->json('data.environment', '')));
            $accountStatus = strtolower(trim((string) $response->json('data.status', '')));
            $expectedEnvironments = $environment === 'sandbox'
                ? ['sandbox']
                : ['production', 'live'];
            $ok = $response->successful()
                && $response->json('success') === true
                && in_array($providerEnvironment, $expectedEnvironments, true)
                && in_array($accountStatus, ['active', 'enabled'], true);

            $result = [
                'ok' => $ok,
                'message' => $ok
                    ? "Connexion {$environment} réussie. Compte GeniusPay actif."
                    : 'Connexion refusée ou environnement GeniusPay incohérent.',
                'httpStatus' => $response->status(),
                'accountStatus' => $accountStatus !== '' ? $accountStatus : null,
                'environment' => $providerEnvironment !== '' ? $providerEnvironment : null,
            ];
        } catch (ConnectionException) {
            $result['message'] = 'GeniusPay est temporairement injoignable.';
        }

        $configuration->provider = self::PROVIDER;
        $configuration->environment = $environment;
        $configuration->base_url = $this->defaultBaseUrl();
        $configuration->checkout_hosts = $this->defaultCheckoutHosts();
        $configuration->updated_by_account_id = $actor->id;

        if (! $configuration->exists) {
            $configuration->api_key = (string) $effective['api_key'];
            $configuration->api_secret = (string) $effective['api_secret'];
            if ((string) $effective['webhook_secret'] !== '') {
                $configuration->webhook_secret = (string) $effective['webhook_secret'];
            }
        }

        $configuration->last_test_status = $result['ok'] ? 'success' : 'failed';
        $configuration->last_test_message = $result['message'];
        $configuration->last_tested_at = now();
        $configuration->save();

        return $result;
    }

    public function activate(string $environment, Account $actor): PaymentProviderConfiguration
    {
        $environment = $this->environment($environment);

        if ($environment === 'production' && ! $this->productionActivationAllowed()) {
            throw ValidationException::withMessages([
                'environment' => 'Les clés Production peuvent être enregistrées, mais leur activation reste verrouillée jusqu’à l’autorisation de mise en production des paiements.',
            ]);
        }

        return DB::transaction(function () use ($environment, $actor): PaymentProviderConfiguration {
            $configuration = PaymentProviderConfiguration::query()
                ->where('provider', self::PROVIDER)
                ->where('environment', $environment)
                ->lockForUpdate()
                ->first();

            if (! $configuration instanceof PaymentProviderConfiguration) {
                throw ValidationException::withMessages([
                    'environment' => 'Enregistre et teste d’abord les identifiants de cet environnement.',
                ]);
            }

            $effective = $this->effectiveConfiguration($environment, $configuration);
            if ($effective['api_key'] === '' || $effective['api_secret'] === '' || $effective['webhook_secret'] === '') {
                throw ValidationException::withMessages([
                    'credentials' => 'La clé API, le secret API et le secret webhook sont tous obligatoires avant activation.',
                ]);
            }

            if ($configuration->last_test_status !== 'success') {
                throw ValidationException::withMessages([
                    'environment' => 'Teste avec succès la connexion GeniusPay avant activation.',
                ]);
            }

            PaymentProviderConfiguration::query()
                ->where('provider', self::PROVIDER)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);

            $configuration->forceFill([
                'is_active' => true,
                'activated_at' => now(),
                'updated_by_account_id' => $actor->id,
            ])->save();

            return $configuration->refresh();
        });
    }

    public function productionActivationAllowed(): bool
    {
        return (bool) config('services.geniuspay.allow_production_activation', false);
    }

    public function webhookUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/api/webhooks/geniuspay';
    }

    /** @return array<string, mixed> */
    private function effectiveConfiguration(
        string $environment,
        ?PaymentProviderConfiguration $configuration,
    ): array {
        $useFallback = $this->fallbackEnvironment() === $environment;
        $fallbackBaseUrl = rtrim(
            (string) config('services.geniuspay.base_url', $this->defaultBaseUrl()),
            '/',
        );
        $fallbackCheckoutHosts = array_values((array) config(
            'services.geniuspay.checkout_hosts',
            $this->defaultCheckoutHosts(),
        ));

        return [
            'environment' => $environment,
            'api_key' => $configuration?->api_key
                ?? ($useFallback ? (string) config('services.geniuspay.api_key', '') : ''),
            'api_secret' => $configuration?->api_secret
                ?? ($useFallback ? (string) config('services.geniuspay.api_secret', '') : ''),
            'webhook_secret' => $configuration?->webhook_secret
                ?? ($useFallback ? (string) config('services.geniuspay.webhook_secret', '') : ''),
            'base_url' => $configuration?->base_url
                ?: ($useFallback ? $fallbackBaseUrl : $this->defaultBaseUrl()),
            'checkout_hosts' => $configuration?->checkout_hosts
                ?: ($useFallback ? $fallbackCheckoutHosts : $this->defaultCheckoutHosts()),
            'webhook_tolerance_seconds' => max(
                30,
                (int) config('services.geniuspay.webhook_tolerance_seconds', 300),
            ),
        ];
    }

    private function fallbackEnvironment(): string
    {
        $environment = strtolower(trim((string) config('services.geniuspay.mode', 'sandbox')));

        if ($environment === 'live') {
            return 'production';
        }

        return in_array($environment, self::ENVIRONMENTS, true) ? $environment : 'sandbox';
    }

    private function environment(string $environment): string
    {
        $environment = strtolower(trim($environment));

        if (! in_array($environment, self::ENVIRONMENTS, true)) {
            throw ValidationException::withMessages([
                'environment' => 'Environnement GeniusPay invalide.',
            ]);
        }

        return $environment;
    }

    private function defaultBaseUrl(): string
    {
        return 'https://geniuspay.ci/api/v1/merchant';
    }

    /** @return list<string> */
    private function defaultCheckoutHosts(): array
    {
        return ['geniuspay.ci', 'pay.genius.ci'];
    }

    private function mask(string $secret): ?string
    {
        if ($secret === '') {
            return null;
        }

        return '••••••••'.mb_substr($secret, -4);
    }
}

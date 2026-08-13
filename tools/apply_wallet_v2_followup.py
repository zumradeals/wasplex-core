from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8")


def write(rel: str, content: str) -> None:
    path = ROOT / rel
    path.write_text(content.rstrip() + "\n", encoding="utf-8")


def replace(rel: str, old: str, new: str) -> None:
    content = read(rel)
    if old not in content:
        raise RuntimeError(f"Motif introuvable dans {rel}: {old[:160]!r}")
    write(rel, content.replace(old, new, 1))


replace(
    "apps/platform/app/Modules/AdvertiserWallet/Http/Controllers/Webhook/GeniusPayWebhookController.php",
    """        if (! $this->provider->verifyWebhookSignature($rawPayload, $headers)) {
            throw new InvalidWebhookSignatureException;
        }
""",
    """        if (! $this->provider->verifyWebhookSignature($rawPayload, $headers)) {
            // Preserve P003's immutable invalid-signature audit event. The
            // historical advertiser handler records signature_valid=false
            // before throwing the same security exception.
            $this->advertiserHandler->handle($rawPayload, $headers);

            throw new InvalidWebhookSignatureException;
        }
""",
)

replace(
    "apps/platform/app/Modules/AdvertiserWallet/Application/Services/GeniusPayConfigurationService.php",
    """        $configuration = GeniusPayConfiguration::query()->latest('updated_at')->first();

        if ($configuration === null && (! $data['api_key'] || ! $data['api_secret'] || ! $data['webhook_secret'])) {
            throw ValidationException::withMessages([
                'api_key' => 'Les trois clés sandbox sont obligatoires lors de la première configuration.',
            ]);
        }

        $values = [
            'environment' => 'sandbox',
            'api_key' => $data['api_key'] ?: $configuration?->api_key,
            'api_secret' => $data['api_secret'] ?: $configuration?->api_secret,
            'webhook_secret' => $data['webhook_secret'] ?: $configuration?->webhook_secret,
            'updated_by' => $actorAccountId,
        ];
""",
    """        $configuration = GeniusPayConfiguration::query()->latest('updated_at')->first();
        $apiKey = $data['api_key'] ?? null;
        $apiSecret = $data['api_secret'] ?? null;
        $webhookSecret = $data['webhook_secret'] ?? null;

        if ($configuration === null && (! $apiKey || ! $apiSecret || ! $webhookSecret)) {
            throw ValidationException::withMessages([
                'api_key' => 'Les trois clés sandbox sont obligatoires lors de la première configuration.',
            ]);
        }

        $values = [
            'environment' => 'sandbox',
            'api_key' => $apiKey ?: $configuration?->api_key,
            'api_secret' => $apiSecret ?: $configuration?->api_secret,
            'webhook_secret' => $webhookSecret ?: $configuration?->webhook_secret,
            'updated_by' => $actorAccountId,
        ];
""",
)

print("Correctif de compatibilité Wallet V2 appliqué.")

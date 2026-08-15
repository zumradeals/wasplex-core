<?php

declare(strict_types=1);

namespace App\Modules\Live\Application\Services;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Shared\Http\AppException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class LiveKitService
{
    private const JOIN_TOKEN_TTL_SECONDS = 300;

    private const ADMIN_TOKEN_TTL_SECONDS = 60;

    /**
     * @return array{url: string, token: string, room: string, identity: string, role: string, can_publish: bool}
     */
    public function joinCredentials(LiveEvent $live, Account $account, bool $canPublish, string $role): array
    {
        $this->assertConfigured();

        if (! in_array($live->status, [LiveEvent::STATUS_LIVE, LiveEvent::STATUS_PAUSED], true)) {
            throw new AppException('LIVE_MEDIA_NOT_OPEN', 'La diffusion média de ce Live n’est pas ouverte.', [], 409);
        }

        $account->loadMissing('profile');
        $identity = $this->participantIdentity($account->id);
        $displayName = $account->profile?->resolvedDisplayName() ?? 'Membre Wasplex';
        $room = $this->roomName($live);
        $now = now()->timestamp;

        $token = $this->sign([
            'iss' => $this->apiKey(),
            'sub' => $identity,
            'nbf' => $now,
            'exp' => $now + self::JOIN_TOKEN_TTL_SECONDS,
            'name' => $displayName,
            'metadata' => json_encode([
                'wasplex_account_id' => (string) $account->id,
                'wasplex_role' => $role,
            ], JSON_THROW_ON_ERROR),
            'video' => [
                'roomJoin' => true,
                'room' => $room,
                'canPublish' => $canPublish,
                'canSubscribe' => true,
                'canPublishData' => false,
            ],
        ]);

        return [
            'url' => $this->browserUrl(),
            'token' => $token,
            'room' => $room,
            'identity' => $identity,
            'role' => $role,
            'can_publish' => $canPublish,
        ];
    }

    public function updateParticipantPublishing(LiveEvent $live, string $accountId, bool $canPublish): void
    {
        $this->assertConfigured();
        $room = $this->roomName($live);
        $now = now()->timestamp;
        $adminToken = $this->sign([
            'iss' => $this->apiKey(),
            'sub' => 'wasplex-live-admin',
            'nbf' => $now,
            'exp' => $now + self::ADMIN_TOKEN_TTL_SECONDS,
            'video' => [
                'roomAdmin' => true,
                'room' => $room,
            ],
        ]);

        try {
            $response = Http::asJson()
                ->withToken($adminToken)
                ->timeout(5)
                ->post($this->apiBaseUrl().'/twirp/livekit.RoomService/UpdateParticipant', [
                    'room' => $room,
                    'identity' => $this->participantIdentity($accountId),
                    'permission' => [
                        'canSubscribe' => true,
                        'canPublish' => $canPublish,
                        'canPublishData' => false,
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new AppException(
                'LIVE_MEDIA_PROVIDER_UNAVAILABLE',
                'Le service vidéo temps réel est momentanément indisponible.',
                [],
                503,
                $exception,
            );
        }

        if (! $response->successful()) {
            throw new AppException(
                'LIVE_MEDIA_PERMISSION_UPDATE_FAILED',
                'Impossible de modifier les droits vidéo de cet intervenant.',
                ['provider_status' => $response->status()],
                503,
            );
        }
    }

    public function ensureConfigured(): void
    {
        $this->assertConfigured();
    }

    public function isConfigured(): bool
    {
        return $this->browserUrl() !== '' && $this->apiKey() !== '' && $this->apiSecret() !== '';
    }

    public function roomName(LiveEvent $live): string
    {
        return 'wasplex-live-'.$live->id;
    }

    public function participantIdentity(string $accountId): string
    {
        return 'account-'.$accountId;
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new AppException(
                'LIVE_MEDIA_NOT_CONFIGURED',
                'Le transport vidéo temps réel n’est pas encore configuré sur cet environnement.',
                [],
                503,
            );
        }
    }

    private function browserUrl(): string
    {
        return trim((string) config('services.livekit.url'));
    }

    private function apiBaseUrl(): string
    {
        $url = $this->browserUrl();

        if (str_starts_with($url, 'wss://')) {
            return rtrim('https://'.substr($url, 6), '/');
        }

        if (str_starts_with($url, 'ws://')) {
            return rtrim('http://'.substr($url, 5), '/');
        }

        return rtrim($url, '/');
    }

    private function apiKey(): string
    {
        return trim((string) config('services.livekit.api_key'));
    }

    private function apiSecret(): string
    {
        return (string) config('services.livekit.api_secret');
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function sign(array $claims): string
    {
        $header = $this->base64Url(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64Url(json_encode($claims, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac('sha256', $header.'.'.$payload, $this->apiSecret(), true);

        return $header.'.'.$payload.'.'.$this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

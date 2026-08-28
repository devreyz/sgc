<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FcmHttpV1Client
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function configured(): bool
    {
        $path = $this->credentialsPath();

        return (bool) config('notifications.fcm.enabled')
            && filled(config('notifications.fcm.project_id'))
            && $path !== null
            && is_readable($path);
    }

    public function send(string $token, array $message): Response
    {
        if (! $this->configured()) {
            throw new RuntimeException('FCM HTTP v1 is not configured.');
        }

        $projectId = rawurlencode((string) config('notifications.fcm.project_id'));

        return Http::asJson()
            ->acceptJson()
            ->withToken($this->accessToken())
            ->timeout((int) config('notifications.fcm.timeout', 15))
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => $message + ['token' => $token],
            ]);
    }

    private function accessToken(): string
    {
        return Cache::remember('fcm:http-v1:access-token', now()->addMinutes(50), function (): string {
            $credentials = new ServiceAccountCredentials(self::SCOPE, $this->credentialsPath());
            $token = $credentials->fetchAuthToken();

            if (! is_array($token) || blank($token['access_token'] ?? null)) {
                throw new RuntimeException('Unable to obtain an FCM OAuth access token.');
            }

            return (string) $token['access_token'];
        });
    }

    private function credentialsPath(): ?string
    {
        $configured = trim((string) config('notifications.fcm.credentials'));
        if ($configured === '') {
            return null;
        }

        if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $configured) === 1) {
            return $configured;
        }

        return base_path($configured);
    }
}

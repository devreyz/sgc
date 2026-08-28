<?php

namespace App\Services;

use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PushDeviceService
{
    public function __construct(private readonly PushSubscriptionSessionService $sessions)
    {
    }

    public function register(User $user, Session $session, array $attributes): PushDevice
    {
        $installationHash = $this->fingerprint((string) $attributes['installation_id']);
        $tokenHash = $this->fingerprint((string) $attributes['token']);
        $sessionHash = $this->sessions->hash($session);

        return DB::transaction(function () use ($user, $attributes, $installationHash, $tokenHash, $sessionHash) {
            $matches = PushDevice::query()
                ->where(fn ($query) => $query
                    ->where('installation_hash', $installationHash)
                    ->orWhere('token_hash', $tokenHash))
                ->lockForUpdate()
                ->get();

            $device = $matches->firstWhere('installation_hash', $installationHash)
                ?? $matches->firstWhere('token_hash', $tokenHash);

            foreach ($matches as $match) {
                if (! $device || $match->isNot($device)) {
                    $match->forceFill([
                        'token_hash' => $this->fingerprint("revoked:{$match->id}:{$match->token_hash}"),
                        'revoked_at' => now(),
                        'notifications_enabled' => false,
                    ])->save();
                }
            }

            $previousUserId = $device?->user_id;
            $device ??= new PushDevice;
            $device->forceFill([
                'user_id' => $user->id,
                'platform' => 'android',
                'installation_hash' => $installationHash,
                'token_hash' => $tokenHash,
                'token' => (string) $attributes['token'],
                'session_hash' => $sessionHash,
                'device_name' => $attributes['device_name'] ?? null,
                'app_version' => $attributes['app_version'] ?? null,
                'os_version' => $attributes['os_version'] ?? null,
                'notifications_enabled' => true,
                'failure_count' => 0,
                'bound_at' => now(),
                'last_seen_at' => now(),
                'last_failure_at' => null,
                'revoked_at' => null,
            ])->save();

            Log::channel(config('logging.default'))->info('Android push device bound.', [
                'push_device_id' => $device->id,
                'user_id' => $user->id,
                'account_changed' => $previousUserId !== null && (int) $previousUserId !== (int) $user->id,
            ]);

            return $device;
        }, 3);
    }

    public function revokeInstallation(User $user, string $installationId): int
    {
        if (! Schema::hasTable('push_devices')) {
            return 0;
        }

        return PushDevice::query()
            ->where('user_id', $user->id)
            ->where('installation_hash', $this->fingerprint($installationId))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'notifications_enabled' => false]);
    }

    public function revokeCurrentSession(int $userId, Session $session): int
    {
        if (! Schema::hasTable('push_devices')) {
            return 0;
        }

        return PushDevice::query()
            ->where('user_id', $userId)
            ->where('session_hash', $this->sessions->hash($session))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'notifications_enabled' => false]);
    }

    private function fingerprint(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}

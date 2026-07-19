<?php

namespace App\Services;

use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SecurityAuditService
{
    private const SENSITIVE_KEYS = [
        'token', 'code', 'challenge', 'credential', 'cookie', 'session',
        'access_token', 'refresh_token', 'id_token', 'password', 'secret',
    ];

    public function record(
        string $eventType,
        string $result,
        array $attributes = [],
        ?Request $request = null,
    ): SecurityEvent {
        $request ??= request();
        $correlationId = $request->attributes->get('security_correlation_id');

        if (! is_string($correlationId) || ! Str::isUuid($correlationId)) {
            $correlationId = (string) Str::uuid();
            $request->attributes->set('security_correlation_id', $correlationId);
        }

        return SecurityEvent::query()->create([
            'event_type' => $eventType,
            'tenant_id' => $attributes['tenant_id'] ?? null,
            'actor_user_id' => $attributes['actor_user_id'] ?? $request->user()?->id,
            'target_user_id' => $attributes['target_user_id'] ?? null,
            'associate_id' => $attributes['associate_id'] ?? null,
            'invitation_id' => $attributes['invitation_id'] ?? null,
            'result' => $result,
            'ip_hash' => $this->hashIp($request->ip()),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'correlation_id' => $correlationId,
            'context' => $this->sanitize($attributes['context'] ?? []),
            'created_at' => now(),
        ]);
    }

    public function hashIp(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('security.audit_pepper'));
    }

    private function sanitize(array $context): array
    {
        $clean = [];

        foreach ($context as $key => $value) {
            $normalizedKey = mb_strtolower((string) $key);

            if (collect(self::SENSITIVE_KEYS)->contains(
                fn (string $sensitive) => str_contains($normalizedKey, $sensitive)
            )) {
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->sanitize($value);
            } elseif (is_scalar($value) || $value === null) {
                $clean[$key] = is_string($value) ? Str::limit($value, 500, '') : $value;
            }
        }

        return $clean;
    }
}

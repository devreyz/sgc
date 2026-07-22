<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PushSubscriptionController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $configured = filled(config('notifications.vapid.public_key'))
            && filled(config('notifications.vapid.private_key'))
            && filled(config('notifications.vapid.subject'));

        return response()->json([
            'configured' => $configured,
            'public_key' => $configured ? config('notifications.vapid.public_key') : null,
            'subscriptions' => PushSubscription::query()->active()->where('user_id', $request->user()->id)->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:4096'],
            'expirationTime' => ['nullable', 'numeric'],
            'keys.p256dh' => ['required', 'string', 'max:512'],
            'keys.auth' => ['required', 'string', 'max:512'],
            'contentEncoding' => ['nullable', 'in:aes128gcm,aesgcm'],
        ]);

        abort_unless(Str::startsWith($data['endpoint'], 'https://'), 422, 'Assinatura push invalida.');

        $hash = hash('sha256', $data['endpoint']);
        $existing = PushSubscription::query()->where('endpoint_hash', $hash)->first();
        abort_if($existing && (int) $existing->user_id !== (int) $request->user()->id, 409, 'Nao foi possivel registrar este dispositivo.');

        $subscription = PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => $hash],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $data['endpoint'],
                'public_key' => data_get($data, 'keys.p256dh'),
                'auth_token' => data_get($data, 'keys.auth'),
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'user_agent_summary' => Str::limit((string) $request->userAgent(), 160, ''),
                'expires_at' => filled($data['expirationTime'] ?? null)
                    ? now()->setTimestampMs((int) $data['expirationTime'])
                    : null,
                'revoked_at' => null,
                'failure_count' => 0,
            ]
        );

        activity('security')->causedBy($request->user())->withProperties([
            'tenant_id' => session('tenant_id'),
            'push_subscription_id' => $subscription->id,
        ])->log('Notificacoes push ativadas no dispositivo');

        return response()->json(['ok' => true, 'id' => $subscription->id], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'url', 'max:4096']]);

        $updated = PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->update(['revoked_at' => now()]);

        if ($updated) {
            activity('security')->causedBy($request->user())->withProperties([
                'tenant_id' => session('tenant_id'),
            ])->log('Notificacoes push desativadas no dispositivo');
        }

        return response()->json(['ok' => true]);
    }
}

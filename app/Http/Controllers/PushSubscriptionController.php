<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\PushSubscriptionSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PushSubscriptionController extends Controller
{
    public function status(Request $request, PushSubscriptionSessionService $sessions): JsonResponse
    {
        $configured = filled(config('notifications.vapid.public_key'))
            && filled(config('notifications.vapid.private_key'))
            && filled(config('notifications.vapid.subject'));
        $schemaReady = $this->schemaReady();

        return response()->json([
            'configured' => $configured,
            'schema_ready' => $schemaReady,
            'public_key' => $configured ? config('notifications.vapid.public_key') : null,
            'subscriptions' => $schemaReady
                ? PushSubscription::query()->active()->where('user_id', $request->user()->id)->count()
                : 0,
            'session_endpoint_hashes' => $schemaReady
                ? PushSubscription::query()
                    ->active()
                    ->where('user_id', $request->user()->id)
                    ->where('session_hash', $sessions->hash($request->session()))
                    ->pluck('endpoint_hash')
                    ->all()
                : [],
        ]);
    }

    public function store(Request $request, PushSubscriptionSessionService $sessions): JsonResponse
    {
        abort_unless($this->schemaReady(), 503, 'Notificacoes temporariamente indisponiveis.');

        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:4096'],
            'expirationTime' => ['nullable', 'numeric'],
            'keys.p256dh' => ['required', 'string', 'max:512'],
            'keys.auth' => ['required', 'string', 'max:512'],
            'contentEncoding' => ['nullable', 'in:aes128gcm,aesgcm'],
        ]);

        abort_unless(Str::startsWith($data['endpoint'], 'https://'), 422, 'Assinatura push invalida.');

        $hash = hash('sha256', $data['endpoint']);
        $sessionHash = $sessions->hash($request->session());
        $previousUserId = null;
        $bindingChanged = false;

        $subscription = DB::transaction(function () use ($request, $data, $hash, $sessionHash, &$previousUserId, &$bindingChanged) {
            $subscription = PushSubscription::query()
                ->where('endpoint_hash', $hash)
                ->lockForUpdate()
                ->first() ?? new PushSubscription(['endpoint_hash' => $hash]);

            $previousUserId = $subscription->exists ? (int) $subscription->user_id : null;
            if ($subscription->exists && $previousUserId !== (int) $request->user()->id) {
                $ownsBrowserSubscription = hash_equals((string) $subscription->public_key, (string) data_get($data, 'keys.p256dh'))
                    && hash_equals((string) $subscription->auth_token, (string) data_get($data, 'keys.auth'));
                abort_unless($ownsBrowserSubscription, 409, 'Nao foi possivel registrar este dispositivo.');
            }

            $bindingChanged = ! $subscription->exists
                || $previousUserId !== (int) $request->user()->id
                || ! hash_equals((string) $subscription->session_hash, $sessionHash)
                || $subscription->revoked_at !== null;

            $subscription->forceFill([
                'user_id' => $request->user()->id,
                'session_hash' => $sessionHash,
                'endpoint' => $data['endpoint'],
                'public_key' => data_get($data, 'keys.p256dh'),
                'auth_token' => data_get($data, 'keys.auth'),
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'user_agent_summary' => Str::limit((string) $request->userAgent(), 160, ''),
                'bound_at' => $bindingChanged ? now() : $subscription->bound_at,
                'last_seen_at' => now(),
                'expires_at' => filled($data['expirationTime'] ?? null)
                    ? now()->setTimestampMs((int) $data['expirationTime'])
                    : null,
                'revoked_at' => null,
                'failure_count' => 0,
            ])->save();

            return $subscription;
        });

        if ($bindingChanged) {
            activity('security')->causedBy($request->user())->withProperties([
                'tenant_id' => session('tenant_id'),
                'push_subscription_id' => $subscription->id,
                'previous_user_id' => $previousUserId,
            ])->log('Notificacoes push vinculadas a sessao do dispositivo');
        }

        return response()->json(['ok' => true, 'id' => $subscription->id], 201);
    }

    public function destroy(Request $request, PushSubscriptionSessionService $sessions): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'url', 'max:4096']]);

        $updated = PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('session_hash', $sessions->hash($request->session()))
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->update(['revoked_at' => now()]);

        if ($updated) {
            activity('security')->causedBy($request->user())->withProperties([
                'tenant_id' => session('tenant_id'),
            ])->log('Notificacoes push desativadas no dispositivo');
        }

        return response()->json(['ok' => true]);
    }

    private function schemaReady(): bool
    {
        return Schema::hasTable('push_subscriptions')
            && Schema::hasColumn('push_subscriptions', 'session_hash')
            && Schema::hasColumn('push_subscriptions', 'bound_at')
            && Schema::hasColumn('push_subscriptions', 'last_seen_at');
    }
}

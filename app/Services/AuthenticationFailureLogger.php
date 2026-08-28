<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AuthenticationFailureLogger
{
    public function __construct(private readonly SecurityAuditService $audit) {}

    public function record(
        Request $request,
        string $flow,
        string $stage,
        string $reason,
        int $status,
        ?Throwable $exception = null,
    ): void {
        $activeRequest = app('request');
        $auditRequest = $activeRequest instanceof Request ? $activeRequest : $request;

        if ($request->attributes->get('authentication_failure_recorded') === true
            || $auditRequest->attributes->get('authentication_failure_recorded') === true) {
            return;
        }

        $flow = $this->label($flow, 'authentication');
        $stage = $this->label($stage, 'unknown');
        $reason = $this->label($reason, 'rejected');
        $platform = $request->header('X-SGC-Platform') === 'android' ? 'android' : 'web';
        $exceptionClass = $exception?->getPrevious()
            ? $exception->getPrevious()::class
            : ($exception ? $exception::class : null);

        $event = $this->audit->record(Str::limit($flow.'_login_failed', 80, ''), 'denied', [
            'context' => [
                'platform' => $platform,
                'stage' => $stage,
                'reason' => $reason,
                'http_status' => $status,
                'exception_class' => $exceptionClass,
            ],
        ], $auditRequest);

        $request->attributes->set('authentication_failure_recorded', true);
        $auditRequest->attributes->set('authentication_failure_recorded', true);

        Log::channel($platform === 'android' ? 'app' : 'web')
            ->warning('Authentication attempt denied.', [
                'flow' => $flow,
                'platform' => $platform,
                'stage' => $stage,
                'reason' => $reason,
                'http_status' => $status,
                'ip_hash' => $event->ip_hash,
                'correlation_id' => $event->correlation_id,
                'user_id' => $auditRequest->user()?->getAuthIdentifier(),
                'exception_class' => $exceptionClass,
            ]);
    }

    private function label(string $value, string $fallback): string
    {
        $value = preg_replace('/[^A-Za-z0-9_.:-]/', '_', $value) ?? '';

        return Str::limit($value !== '' ? $value : $fallback, 80, '');
    }
}

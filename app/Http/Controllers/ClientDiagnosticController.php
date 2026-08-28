<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientDiagnosticRequest;
use App\Services\SecurityAuditService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClientDiagnosticController extends Controller
{
    public function __invoke(ClientDiagnosticRequest $request, SecurityAuditService $audit): Response
    {
        $data = $request->validated();
        $channel = $data['platform'] === 'app' ? 'app' : 'web';
        $message = $this->sanitizeMessage($data['message']);

        try {
            $audit->record(
                $data['category'] === 'authentication'
                    ? 'client_authentication_error'
                    : 'client_error_reported',
                'reported',
                ['context' => [
                    'platform' => $data['platform'],
                    'category' => $data['category'],
                    'error_type' => $data['code'] ?? null,
                    'stage' => $data['stage'] ?? null,
                    'message' => $message,
                    'path' => $data['path'] ?? null,
                    'app_version' => $data['app_version'] ?? null,
                    'android_version' => $data['android_version'] ?? null,
                    'device' => $data['device'] ?? null,
                ]],
                $request,
            );
        } catch (Throwable $exception) {
            Log::channel($channel)->error('Client diagnostic could not be persisted.', [
                'exception_class' => $exception::class,
                'ip_hash' => $audit->hashIp($request->ip()),
            ]);
        }

        Log::channel($channel)->warning('Client diagnostic reported.', [
            'platform' => $data['platform'],
            'category' => $data['category'],
            'code' => $data['code'] ?? null,
            'stage' => $data['stage'] ?? null,
            'message' => $message,
            'path' => $data['path'] ?? null,
            'app_version' => $data['app_version'] ?? null,
            'android_version' => $data['android_version'] ?? null,
            'device' => $data['device'] ?? null,
            'ip_hash' => $audit->hashIp($request->ip()),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 300),
        ]);

        return response()->noContent();
    }

    private function sanitizeMessage(string $message): string
    {
        $message = preg_replace('/Bearer\s+[A-Za-z0-9._~+\/-]+/i', 'Bearer [redacted]', $message) ?? $message;
        $message = preg_replace('/\beyJ[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\b/', '[redacted-jwt]', $message) ?? $message;
        $message = preg_replace('/\b[A-Fa-f0-9]{64,}\b/', '[redacted-hex]', $message) ?? $message;

        return mb_substr($message, 0, 500);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientDiagnosticRequest;
use App\Services\SecurityAuditService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ClientDiagnosticController extends Controller
{
    public function __invoke(ClientDiagnosticRequest $request, SecurityAuditService $audit): Response
    {
        $data = $request->validated();
        $channel = $data['platform'] === 'app' ? 'app' : 'web';

        Log::channel($channel)->warning('Client diagnostic reported.', [
            'platform' => $data['platform'],
            'category' => $data['category'],
            'code' => $data['code'] ?? null,
            'stage' => $data['stage'] ?? null,
            'message' => $data['message'],
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
}

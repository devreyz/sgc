<?php

namespace App\Http\Controllers;

use App\Services\PushDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushDeviceController extends Controller
{
    public function store(Request $request, PushDeviceService $devices): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:4096'],
            'installation_id' => ['required', 'uuid'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'os_version' => ['nullable', 'string', 'max:40'],
        ]);

        $device = $devices->register($request->user(), $request->session(), $validated);

        return response()->json(['registered' => true, 'device_id' => $device->id]);
    }

    public function destroy(Request $request, PushDeviceService $devices): JsonResponse
    {
        $validated = $request->validate(['installation_id' => ['required', 'uuid']]);
        $devices->revokeInstallation($request->user(), $validated['installation_id']);

        return response()->json(['revoked' => true]);
    }
}

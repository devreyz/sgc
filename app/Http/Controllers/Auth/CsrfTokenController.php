<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CsrfTokenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'csrf_token' => $request->session()->token(),
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
        ]);
    }
}

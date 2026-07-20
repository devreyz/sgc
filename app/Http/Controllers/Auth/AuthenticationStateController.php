<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthenticationRedirector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticationStateController extends Controller
{
    public function __invoke(Request $request, AuthenticationRedirector $redirector): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'authenticated' => $user !== null,
            'redirect' => $user ? $redirector->pathFor($user) : null,
        ])->header('Cache-Control', 'no-store, private, max-age=0');
    }
}

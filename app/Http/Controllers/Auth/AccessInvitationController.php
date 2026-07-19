<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyInvitationCodeRequest;
use App\Services\AccessInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AccessInvitationController extends Controller
{
    public function consume(string $token, Request $request, AccessInvitationService $service): RedirectResponse
    {
        $invitation = $service->findPendingByToken($token);

        if ($invitation) {
            $request->session()->put('access_invitation_id', $invitation->id);
        } else {
            $request->session()->forget('access_invitation_id');
        }

        return redirect()->route('access.invitation.verify', status: 303);
    }

    public function show(): mixed
    {
        return view('auth.invitation-verify');
    }

    public function verifyCode(
        VerifyInvitationCodeRequest $request,
        AccessInvitationService $service,
    ): JsonResponse {
        try {
            $invitationId = (string) $request->session()->get('access_invitation_id', '');
            $claim = $service->claim($invitationId, $request->string('code')->toString(), $request);

            $request->session()->regenerate();
            $service->bindClaimToSession($claim['invitation'], $claim['grant'], $request);
            $request->session()->forget('access_invitation_id');
            $request->session()->put('access_enrollment', [
                'invitation_id' => $claim['invitation']->id,
                'grant' => $claim['grant'],
                'expires_at' => $claim['invitation']->enrollment_expires_at->timestamp,
            ]);

            return response()->json(['redirect' => route('access.invitation.passkey')]);
        } catch (RuntimeException) {
            return response()->json(['message' => 'Nao foi possivel validar este acesso.'], 422);
        }
    }
}

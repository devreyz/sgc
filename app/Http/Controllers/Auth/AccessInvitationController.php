<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyInvitationCodeRequest;
use App\Services\AccessInvitationService;
use App\Services\AuthenticationRedirector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AccessInvitationController extends Controller
{
    public function consume(
        string $token,
        Request $request,
        AccessInvitationService $service,
        AuthenticationRedirector $redirector,
    ): RedirectResponse
    {
        if ($request->user()) {
            return redirect()->to($redirector->pathFor($request->user()), 303);
        }

        $invitation = $service->findPendingByToken($token);

        if ($invitation) {
            $invitation = $service->prepareForCodeEntry($invitation, $request);
        }

        if ($invitation) {
            $request->session()->put('access_invitation_id', $invitation->id);
        } else {
            $request->session()->forget('access_invitation_id');
        }

        return $invitation
            ? redirect()->route('access.invitation.verify', status: 303)
            : redirect()->route('login', status: 303)
                ->with('error', 'O convite nao esta disponivel.');
    }

    public function show(
        Request $request,
        AccessInvitationService $service,
        AuthenticationRedirector $redirector,
    ): mixed
    {
        if ($request->user()) {
            return redirect()->to($redirector->pathFor($request->user()), 303);
        }

        if (! $service->pendingInvitationFromSession($request)) {
            $request->session()->forget('access_invitation_id');

            return redirect()->route('login', status: 303)
                ->with('error', 'O convite nao esta disponivel.');
        }

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

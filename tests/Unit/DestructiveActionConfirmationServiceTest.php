<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\DestructiveActionConfirmationService;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Tests\TestCase;

class DestructiveActionConfirmationServiceTest extends TestCase
{
    public function test_code_cannot_bypass_a_required_passkey(): void
    {
        [$request, $user] = $this->requestWithUser();
        $this->putChallenge($request, passkeyRequired: true);

        $verified = app(DestructiveActionConfirmationService::class)->verifyAndConsume(
            $request,
            'delete_distribution',
            99,
            'confirmation-id',
            'ABC-123',
        );

        self::assertFalse($verified);
        self::assertSame(1, $request->session()->get('sgc.destructive_confirmation.attempts'));
        self::assertNull($user->last_authenticated_at);
    }

    public function test_fresh_passkey_confirmation_is_single_use(): void
    {
        [$request, $user] = $this->requestWithUser();
        $this->putChallenge($request, passkeyRequired: true);
        $user->last_authenticated_at = now();

        $service = app(DestructiveActionConfirmationService::class);

        self::assertTrue($service->verifyAndConsume(
            $request,
            'delete_distribution',
            99,
            'confirmation-id',
            null,
        ));
        self::assertFalse($service->verifyAndConsume(
            $request,
            'delete_distribution',
            99,
            'confirmation-id',
            null,
        ));
    }

    public function test_server_code_is_bound_to_user_tenant_action_and_subject(): void
    {
        [$request] = $this->requestWithUser();
        $this->putChallenge($request, passkeyRequired: false);

        $service = app(DestructiveActionConfirmationService::class);

        self::assertFalse($service->verifyAndConsume(
            $request,
            'delete_delivery',
            99,
            'confirmation-id',
            'ABC-123',
        ));

        $this->putChallenge($request, passkeyRequired: false);
        self::assertTrue($service->verifyAndConsume(
            $request,
            'delete_distribution',
            99,
            'confirmation-id',
            'abc-123',
        ));
        self::assertFalse($request->session()->has('sgc.destructive_confirmation'));
    }

    /** @return array{Request, User} */
    private function requestWithUser(): array
    {
        $session = new Store('test', new ArraySessionHandler(5));
        $session->start();
        $session->put('tenant_id', 7);

        $user = new User;
        $user->forceFill(['id' => 11, 'last_authenticated_at' => null]);

        $request = Request::create('/');
        $request->setLaravelSession($session);
        $request->setUserResolver(fn (): User => $user);

        return [$request, $user];
    }

    private function putChallenge(Request $request, bool $passkeyRequired): void
    {
        $issuedAt = now()->timestamp;
        $request->session()->put('sgc.destructive_confirmation', [
            'id' => 'confirmation-id',
            'action' => 'delete_distribution',
            'subject_id' => 99,
            'user_id' => 11,
            'tenant_id' => 7,
            'code_hash' => hash('sha256', 'ABC-123'),
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt + 300,
            'attempts' => 0,
            'passkey_required' => $passkeyRequired,
        ]);
    }
}

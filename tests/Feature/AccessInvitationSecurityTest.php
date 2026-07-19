<?php

namespace Tests\Feature;

use App\Actions\Passkeys\GenerateSecureRegistrationOptions;
use App\Http\Requests\SecurePasskeyVerificationRequest;
use App\Models\Associate;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\AccessInvitationService;
use App\Services\GoogleAccountService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Support\WebAuthn;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\TestCase;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialRequestOptions;

class AccessInvitationSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['security_events', 'access_invitations', 'oauth_accounts', 'passkeys', 'activity_log', 'model_has_roles', 'model_has_permissions', 'role_has_permissions', 'permissions', 'roles', 'associates', 'tenant_user', 'tenants', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->nullable()->unique();
            $t->string('password')->nullable();
            $t->boolean('status')->default(true);
            $t->string('google_id')->nullable();
            $t->string('avatar')->nullable();
            $t->string('webauthn_user_handle', 43)->nullable()->unique();
            $t->timestamp('last_authenticated_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->boolean('active')->default(true);
            $t->string('locale')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('tenant_user', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('user_id');
            $t->boolean('is_admin')->default(false);
            $t->json('roles')->nullable();
            $t->boolean('status')->default(true);
            $t->string('tenant_name')->nullable();
            $t->string('tenant_password')->nullable();
            $t->timestamp('deactivated_at')->nullable();
            $t->unsignedBigInteger('deactivated_by')->nullable();
            $t->text('notes')->nullable();
            $t->json('email_history')->nullable();
            $t->timestamps();
        });
        Schema::create('associates', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('nickname')->nullable();
            $t->string('cpf_cnpj')->nullable();
            $t->string('city')->default('Cidade');
            $t->string('state', 2)->default('SP');
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
        });
        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
        });
        Schema::create('role_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->unsignedBigInteger('role_id');
        });
        Schema::create('model_has_roles', function (Blueprint $t) {
            $t->unsignedBigInteger('role_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
        });
        Schema::create('model_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
        });
        Schema::create('passkeys', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->unsignedBigInteger('user_id');
            $t->string('name')->nullable();
            $t->string('credential_id')->unique();
            $t->text('credential');
            $t->text('public_key')->nullable();
            $t->unsignedBigInteger('sign_count')->default(0);
            $t->json('transports')->nullable();
            $t->string('aaguid')->nullable();
            $t->boolean('backup_eligible')->default(false);
            $t->boolean('backup_state')->default(false);
            $t->boolean('user_verified')->default(false);
            $t->string('rp_id');
            $t->timestamp('last_used_at')->nullable();
            $t->string('created_ip_hash')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->unsignedBigInteger('revoked_by_user_id')->nullable();
            $t->string('revocation_reason')->nullable();
            $t->timestamps();
        });
        Schema::create('oauth_accounts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('provider');
            $t->string('provider_subject');
            $t->string('provider_email')->nullable();
            $t->boolean('provider_email_verified')->default(false);
            $t->timestamp('linked_at');
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
            $t->unique(['provider', 'provider_subject']);
            $t->unique(['user_id', 'provider']);
        });
        Schema::create('access_invitations', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('associate_id')->nullable();
            $t->unsignedBigInteger('tenant_user_id')->nullable();
            $t->unsignedBigInteger('issued_by_user_id');
            $t->string('token_hash')->unique();
            $t->string('code_hash');
            $t->string('status')->default('pending');
            $t->timestamp('expires_at');
            $t->timestamp('claimed_at')->nullable();
            $t->string('claimed_session_hash')->nullable();
            $t->timestamp('enrollment_expires_at')->nullable();
            $t->timestamp('consumed_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->unsignedTinyInteger('failed_attempts')->default(0);
            $t->timestamp('last_attempt_at')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
        });
        Schema::create('security_events', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('event_type');
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->unsignedBigInteger('actor_user_id')->nullable();
            $t->unsignedBigInteger('target_user_id')->nullable();
            $t->unsignedBigInteger('associate_id')->nullable();
            $t->string('invitation_id')->nullable();
            $t->string('result');
            $t->string('ip_hash')->nullable();
            $t->string('user_agent')->nullable();
            $t->string('correlation_id');
            $t->json('context')->nullable();
            $t->timestamp('created_at');
        });
        Schema::create('activity_log', function (Blueprint $t) {
            $t->id();
            $t->string('log_name')->nullable();
            $t->text('description');
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('causer_type')->nullable();
            $t->unsignedBigInteger('causer_id')->nullable();
            $t->string('event')->nullable();
            $t->text('properties')->nullable();
            $t->uuid('batch_uuid')->nullable();
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->timestamps();
        });
    }

    public function test_invitation_secrets_are_independent_hashed_and_revealed_once(): void
    {
        [$admin, $tenantId, $associate] = $this->fixture();
        $result = app(AccessInvitationService::class)->issue($admin, $associate, $tenantId, 36);
        $invitation = $result['invitation']->fresh();
        $token = basename(parse_url($result['link'], PHP_URL_PATH));

        $this->assertNotSame($result['code'], $token);
        $this->assertNotSame($result['code'], $invitation->code_hash);
        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertTrue(Hash::driver('argon2id')->check(
            $result['code'].config('security.invitation_code_pepper'),
            $invitation->code_hash
        ));
        $this->assertCount(2, array_values(array_filter(explode('/', (string) parse_url($result['link'], PHP_URL_PATH)))));
        $this->assertDatabaseMissing('security_events', ['context' => $result['code']]);
        $serializedEvents = DB::table('security_events')->pluck('context')->implode('|');
        $this->assertStringNotContainsString($result['code'], $serializedEvents);
        $this->assertStringNotContainsString($token, $serializedEvents);
    }

    public function test_scanner_get_does_not_claim_and_token_disappears_from_redirect(): void
    {
        [$admin, $tenantId, $associate] = $this->fixture();
        $result = app(AccessInvitationService::class)->issue($admin, $associate, $tenantId);
        $token = basename(parse_url($result['link'], PHP_URL_PATH));

        $response = $this->get('/acesso/'.$token);
        $response->assertStatus(303)->assertRedirect(route('access.invitation.verify'));
        $this->assertStringNotContainsString($token, (string) $response->headers->get('Location'));
        $this->assertSame('pending', $result['invitation']->fresh()->status);

        $page = $this->get(route('access.invitation.verify'));
        $page->assertOk()->assertDontSee($associate->nickname)->assertDontSee('Tenant A');
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $page->headers->get('Referrer-Policy'));
    }

    public function test_fifth_invalid_code_locks_invitation(): void
    {
        [$admin, $tenantId, $associate] = $this->fixture();
        $result = app(AccessInvitationService::class)->issue($admin, $associate, $tenantId);
        $token = basename(parse_url($result['link'], PHP_URL_PATH));
        $this->get('/acesso/'.$token);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson(route('access.invitation.code'), ['code' => 'AAAAAAAAAA'])
                ->assertStatus(422)
                ->assertJson(['message' => 'Nao foi possivel validar este acesso.']);
        }

        $invitation = $result['invitation']->fresh();
        $this->assertSame(5, $invitation->failed_attempts);
        $this->assertSame('locked', $invitation->status);
    }

    public function test_correct_code_claims_once_and_grant_does_not_authenticate_user(): void
    {
        [$admin, $tenantId, $associate] = $this->fixture();
        $result = app(AccessInvitationService::class)->issue($admin, $associate, $tenantId);
        $token = basename(parse_url($result['link'], PHP_URL_PATH));
        $this->get('/acesso/'.$token);

        $this->postJson(route('access.invitation.code'), ['code' => $result['code']])
            ->assertOk()
            ->assertJson(['redirect' => route('access.invitation.passkey')]);
        $this->assertSame('claimed', $result['invitation']->fresh()->status);
        $this->assertGuest();
        $this->get('/tenant/select')->assertRedirect('/login');

        $this->postJson(route('access.invitation.code'), ['code' => $result['code']])->assertStatus(422);
    }

    public function test_admin_cannot_open_associate_from_another_tenant(): void
    {
        [$admin] = $this->fixture();
        $tenantB = DB::table('tenants')->insertGetId(['name' => 'Tenant B', 'slug' => 'tenant-b', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $associateB = DB::table('associates')->insertGetId(['tenant_id' => $tenantB, 'nickname' => 'Outro membro', 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($admin)
            ->get(route('security.associates.access.index', ['tenant' => 'tenant-b', 'associate' => $associateB]));
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_admin_can_manage_access_for_member_without_associate(): void
    {
        [$admin, $tenantId] = $this->fixture();
        $member = User::query()->create([
            'name' => 'Global Member',
            'email' => 'member@example.test',
            'password' => bcrypt('secret'),
            'status' => true,
        ]);
        $membership = TenantUser::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $member->id,
            'is_admin' => false,
            'roles' => ['associado'],
            'status' => true,
            'tenant_name' => 'Membro Local',
        ]);

        $this->actingAs($admin)
            ->get(route('security.members.access.index', [
                'tenant' => 'tenant-a',
                'membership' => $membership->id,
            ]))
            ->assertOk()
            ->assertSee('Membro Local')
            ->assertDontSee('Global Member');

        $this->actingAs($admin)
            ->postJson(route('security.members.access.store', [
                'tenant' => 'tenant-a',
                'membership' => $membership->id,
            ]), ['expires_in_hours' => 24])
            ->assertCreated()
            ->assertJsonStructure(['id', 'link', 'code', 'expires_at']);

        $this->assertDatabaseHas('access_invitations', [
            'tenant_id' => $tenantId,
            'tenant_user_id' => $membership->id,
            'associate_id' => null,
            'status' => 'pending',
        ]);
    }

    public function test_admin_cannot_manage_member_from_another_tenant(): void
    {
        [$admin] = $this->fixture();
        $otherUser = User::query()->create([
            'name' => 'Other User',
            'email' => 'other@example.test',
            'password' => bcrypt('secret'),
            'status' => true,
        ]);
        $tenantB = DB::table('tenants')->insertGetId([
            'name' => 'Tenant B', 'slug' => 'tenant-b', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $membership = TenantUser::query()->create([
            'tenant_id' => $tenantB,
            'user_id' => $otherUser->id,
            'status' => true,
            'tenant_name' => 'Outro Membro',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('security.members.access.index', [
                'tenant' => 'tenant-b',
                'membership' => $membership->id,
            ]));

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_passkey_management_requires_recent_authentication(): void
    {
        [$member] = $this->fixture();

        $this->actingAs($member)
            ->get(route('security.passkeys.options'))
            ->assertRedirect(route('security.index'));
    }

    public function test_recent_google_member_can_request_own_passkey_registration(): void
    {
        config()->set('passkeys.relying_party_id', 'localhost');
        config()->set('passkeys.allowed_origins', ['http://localhost']);
        [, $tenantId] = $this->fixture();
        $member = User::query()->create([
            'name' => 'Google Account',
            'email' => 'google-member@example.test',
            'password' => null,
            'status' => true,
            'last_authenticated_at' => now(),
        ]);
        TenantUser::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $member->id,
            'is_admin' => false,
            'roles' => [],
            'status' => true,
            'tenant_name' => 'Membro Google',
        ]);
        DB::table('oauth_accounts')->insert([
            'user_id' => $member->id,
            'provider' => 'google',
            'provider_subject' => 'google-member-subject',
            'provider_email' => 'google-member@example.test',
            'provider_email_verified' => true,
            'linked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($member)
            ->getJson(route('security.passkeys.options'))
            ->assertOk()
            ->assertJsonStructure(['options']);
    }

    public function test_registration_options_require_resident_key_and_user_verification(): void
    {
        [$admin] = $this->fixture();
        $options = app(GenerateSecureRegistrationOptions::class)($admin);

        $this->assertSame(32, strlen($options->challenge));
        $this->assertSame(
            AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            $options->authenticatorSelection?->residentKey
        );
        $this->assertSame(
            AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            $options->authenticatorSelection?->userVerification
        );
        $this->assertSame('none', $options->attestation);
    }

    public function test_authentication_options_are_userless_and_challenge_is_single_use(): void
    {
        $options = app(GenerateVerificationOptions::class)();
        $this->assertSame([], $options->allowCredentials);
        $this->assertSame(32, strlen($options->challenge));

        $this->withSession(['sgc.passkeys.authentication' => [
            'purpose' => 'authentication',
            'options' => WebAuthn::toJson($options),
            'expires_at' => now()->addMinute()->timestamp,
        ]]);
        $request = SecurePasskeyVerificationRequest::create('/auth/passkey/verify', 'POST');
        $request->setLaravelSession(app('session.store'));
        $this->assertInstanceOf(PublicKeyCredentialRequestOptions::class, $request->verificationOptions());

        $this->expectException(ValidationException::class);
        $request->verificationOptions();
    }

    public function test_expired_or_revoked_invitation_token_never_returns_to_pending(): void
    {
        [$admin, $tenantId, $associate] = $this->fixture();
        $service = app(AccessInvitationService::class);
        $issued = $service->issue($admin, $associate, $tenantId);
        $expired = $issued['invitation'];
        $token = rawurldecode((string) str($issued['link'])->afterLast('/'));
        $expired->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->assertNull($service->findPendingByToken($token));
        $this->assertSame('expired', $expired->fresh()->status);

        $revoked = $service->issue($admin, $associate, $tenantId)['invitation'];
        $service->revoke($revoked, $admin, Request::create('/security', 'DELETE'));

        $this->assertSame('revoked', $revoked->fresh()->status);
    }

    public function test_user_handle_is_random_stable_and_not_based_on_sequential_id(): void
    {
        $user = User::query()->create(['name' => null, 'email' => null, 'password' => null, 'status' => true]);
        $handle = $user->webauthn_user_handle;

        $this->assertSame(32, strlen(Base64UrlSafe::decodeNoPadding($handle)));
        $this->assertNotSame((string) $user->id, $handle);
        $this->assertSame($handle, $user->fresh()->webauthn_user_handle);
    }

    public function test_google_subject_is_primary_and_same_email_never_auto_merges(): void
    {
        [$user] = $this->fixture();

        try {
            app(GoogleAccountService::class)->resolve(
                'login', 'different-google-subject', mb_strtolower($user->email), null, null
            );
            $this->fail('An email collision must not merge accounts.');
        } catch (\RuntimeException) {
            $this->assertDatabaseCount('oauth_accounts', 0);
            $this->assertDatabaseCount('users', 1);
        }
    }

    public function test_linking_google_requires_recent_authentication_and_preserves_user(): void
    {
        [$user] = $this->fixture();
        $service = app(GoogleAccountService::class);

        $this->expectException(\RuntimeException::class);
        $service->resolve('link', 'google-subject', $user->email, $user, $user->id);
    }

    public function test_recent_user_can_link_google_by_subject(): void
    {
        [$user] = $this->fixture();
        $user->forceFill(['last_authenticated_at' => now()])->saveQuietly();

        [$resolved, $account] = app(GoogleAccountService::class)->resolve(
            'link', 'google-subject', $user->email, $user, $user->id
        );

        $this->assertSame($user->id, $resolved->id);
        $this->assertSame('google-subject', $account->provider_subject);
        $this->assertDatabaseCount('users', 1);
    }

    private function fixture(): array
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Global Admin', 'email' => 'admin@example.test', 'password' => bcrypt('secret'), 'status' => true,
            'webauthn_user_handle' => Base64UrlSafe::encodeUnpadded(random_bytes(32)), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $tenantId = DB::table('tenants')->insertGetId(['name' => 'Tenant A', 'slug' => 'tenant-a', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tenant_user')->insert(['tenant_id' => $tenantId, 'user_id' => $userId, 'is_admin' => true, 'roles' => json_encode(['admin']), 'status' => true, 'tenant_name' => 'Admin Local', 'created_at' => now(), 'updated_at' => now()]);
        $associateId = DB::table('associates')->insertGetId(['tenant_id' => $tenantId, 'nickname' => 'Pessoa Protegida', 'cpf_cnpj' => '00000000000', 'created_at' => now(), 'updated_at' => now()]);

        return [User::query()->findOrFail($userId), $tenantId, Associate::withoutGlobalScopes()->findOrFail($associateId)];
    }
}

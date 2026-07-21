<?php

namespace Tests\Feature;

use App\Actions\Passkeys\GenerateSecureRegistrationOptions;
use App\Http\Requests\SecurePasskeyVerificationRequest;
use App\Models\Associate;
use App\Models\TenantUser;
use App\Models\TenantCloudStorageConnection;
use App\Models\User;
use App\Services\AccessInvitationService;
use App\Services\GoogleAccountService;
use App\Services\GoogleDriveClientFactory;
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
        foreach (['tenant_cloud_storage_connections', 'security_events', 'access_invitations', 'oauth_accounts', 'passkeys', 'activity_log', 'model_has_roles', 'model_has_permissions', 'role_has_permissions', 'permissions', 'roles', 'associates', 'tenant_user', 'tenants', 'users'] as $table) {
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
            $t->timestamp('expires_at')->nullable();
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
        Schema::create('tenant_cloud_storage_connections', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->unique();
            $t->string('provider')->default('google_drive');
            $t->text('oauth_client_id')->nullable();
            $t->text('oauth_client_secret')->nullable();
            $t->text('refresh_token')->nullable();
            $t->text('granted_scopes')->nullable();
            $t->string('root_folder_id')->nullable();
            $t->string('status')->default('active');
            $t->unsignedBigInteger('connected_by_user_id')->nullable();
            $t->timestamp('connected_at')->nullable();
            $t->timestamp('last_sync_at')->nullable();
            $t->text('last_error')->nullable();
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
        $this->assertMatchesRegularExpression('/^\d{6}$/', $result['code']);
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
        $wrongCode = $result['code'] === '000000' ? '000001' : '000000';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson(route('access.invitation.code'), ['code' => $wrongCode])
                ->assertStatus(422)
                ->assertJson(['message' => 'Nao foi possivel validar este acesso.']);
        }

        $invitation = $result['invitation']->fresh();
        $this->assertSame(5, $invitation->failed_attempts);
        $this->assertSame('locked', $invitation->status);
    }

    public function test_correct_code_creates_temporary_grant_without_consuming_invitation(): void
    {
        [$admin, $tenantId, $associate] = $this->fixture();
        $result = app(AccessInvitationService::class)->issue($admin, $associate, $tenantId);
        $token = basename(parse_url($result['link'], PHP_URL_PATH));
        $this->get('/acesso/'.$token);

        $this->postJson(route('access.invitation.code'), ['code' => $result['code']])
            ->assertOk()
            ->assertJson(['redirect' => route('access.invitation.passkey')]);
        $invitation = $result['invitation']->fresh();
        $this->assertSame('pending', $invitation->status);
        $this->assertNotNull($invitation->claimed_at);
        $this->assertNull($invitation->consumed_at);
        $this->assertGuest();
        $this->get('/tenant/select')->assertRedirect('/login');

        $this->postJson(route('access.invitation.code'), ['code' => $result['code']])->assertStatus(422);
    }

    public function test_invitation_can_request_code_again_when_passkey_was_not_created(): void
    {
        [$admin, $tenantId, $associate] = $this->fixture();
        $result = app(AccessInvitationService::class)->issue($admin, $associate, $tenantId);
        $token = basename(parse_url($result['link'], PHP_URL_PATH));

        $this->get('/acesso/'.$token);
        $this->postJson(route('access.invitation.code'), ['code' => $result['code']])->assertOk();
        $service = app(AccessInvitationService::class);
        $this->assertTrue($service->invitationForGrant(request())->isClaimed());

        // Simula fechar a pagina antes da cerimonia e abrir o link novamente.
        $this->get('/acesso/'.$token)
            ->assertStatus(303)
            ->assertRedirect(route('access.invitation.verify'));
        try {
            $service->invitationForGrant(request());
            $this->fail('Reabrir o link deve invalidar a autorizacao temporaria anterior.');
        } catch (\RuntimeException) {
            $this->assertTrue(true);
        }
        $this->get(route('access.invitation.verify'))->assertOk();
        $this->postJson(route('access.invitation.code'), ['code' => $result['code']])->assertOk();

        $this->assertSame('pending', $result['invitation']->fresh()->status);
        $this->assertNull($result['invitation']->fresh()->consumed_at);
    }

    public function test_legacy_claimed_invitation_returns_to_code_step_when_link_is_reopened(): void
    {
        [$admin, $tenantId, $associate] = $this->fixture();
        $result = app(AccessInvitationService::class)->issue($admin, $associate, $tenantId);
        $token = basename(parse_url($result['link'], PHP_URL_PATH));
        $result['invitation']->forceFill([
            'status' => 'claimed',
            'claimed_at' => now(),
            'claimed_session_hash' => hash('sha256', 'old-session'),
            'enrollment_expires_at' => now()->addMinutes(5),
        ])->save();

        $this->get('/acesso/'.$token)
            ->assertStatus(303)
            ->assertRedirect(route('access.invitation.verify'));

        $invitation = $result['invitation']->fresh();
        $this->assertSame('pending', $invitation->status);
        $this->assertNull($invitation->claimed_session_hash);
    }

    public function test_consumed_invitation_never_opens_code_page_again(): void
    {
        [$admin, $tenantId, $associate] = $this->fixture();
        $result = app(AccessInvitationService::class)->issue($admin, $associate, $tenantId);
        $token = basename(parse_url($result['link'], PHP_URL_PATH));
        $result['invitation']->forceFill(['status' => 'consumed', 'consumed_at' => now()])->save();

        $this->get('/acesso/'.$token)
            ->assertStatus(303)
            ->assertRedirect(route('login'));
        $this->get(route('access.invitation.verify'))
            ->assertStatus(303)
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_is_redirected_away_from_intermediate_auth_pages(): void
    {
        [$user, $tenantId, $associate] = $this->fixture();
        $result = app(AccessInvitationService::class)->issue($user, $associate, $tenantId);
        $token = basename(parse_url($result['link'], PHP_URL_PATH));

        $this->actingAs($user)->get('/login')->assertRedirect();
        $this->actingAs($user)->get('/acesso/'.$token)->assertStatus(303);
        $this->actingAs($user)->getJson(route('auth.state'))
            ->assertOk()
            ->assertJson(['authenticated' => true]);
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

    public function test_non_canonical_legacy_user_handle_is_repaired_without_changing_its_bytes(): void
    {
        $legacyHandle = str_repeat('A', 42).'B';
        $expectedBytes = base64_decode($legacyHandle.'=', true);
        $user = User::query()->create([
            'name' => null,
            'email' => null,
            'password' => null,
            'status' => true,
            'webauthn_user_handle' => $legacyHandle,
        ]);

        $this->assertSame($expectedBytes, $user->getPasskeyUserHandle());
        $this->assertSame(
            Base64UrlSafe::encodeUnpadded($expectedBytes),
            $user->fresh()->webauthn_user_handle
        );
    }

    public function test_invalid_user_handle_with_existing_passkey_is_never_rotated_silently(): void
    {
        $user = User::query()->create([
            'name' => null,
            'email' => null,
            'password' => null,
            'status' => true,
            'webauthn_user_handle' => str_repeat('%', 43),
        ]);
        DB::table('passkeys')->insert([
            'id' => '01KPASSKEYINVALIDHANDLE00001',
            'user_id' => $user->id,
            'name' => 'Chave existente',
            'credential_id' => 'credential-existing',
            'credential' => '{}',
            'sign_count' => 0,
            'rp_id' => 'localhost',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $user->getPasskeyUserHandle();
            $this->fail('An invalid handle with credentials must require administrative recovery.');
        } catch (\RuntimeException) {
            $this->assertSame(str_repeat('%', 43), $user->fresh()->webauthn_user_handle);
        }
    }

    public function test_expired_passkey_is_not_available_for_authentication(): void
    {
        [$user] = $this->fixture();
        DB::table('passkeys')->insert([
            'id' => '01KPASSKEYEXPIRED0000000001',
            'user_id' => $user->id,
            'name' => 'Chave expirada',
            'credential_id' => 'credential-expired',
            'credential' => '{}',
            'sign_count' => 0,
            'rp_id' => 'localhost',
            'expires_at' => now()->subSecond(),
            'created_at' => now()->subYear(),
            'updated_at' => now(),
        ]);

        $this->assertFalse(\App\Models\Passkey::query()
            ->where('credential_id', 'credential-expired')
            ->exists());
        $this->assertTrue(\App\Models\Passkey::withoutGlobalScope('usable')
            ->where('credential_id', 'credential-expired')
            ->exists());
    }

    public function test_passkey_name_is_limited_to_three_words_in_backend(): void
    {
        config()->set('passkeys.relying_party_id', 'localhost');
        config()->set('passkeys.allowed_origins', ['http://localhost']);
        [$user] = $this->fixture();
        $user->forceFill(['last_authenticated_at' => now()])->saveQuietly();

        $this->actingAs($user)
            ->postJson(route('security.passkeys.store'), [
                'name' => 'uma chave com quatro palavras',
                'credential' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
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

    public function test_tenant_drive_refresh_token_is_encrypted_and_hidden(): void
    {
        [$user, $tenantId] = $this->fixture();
        $connection = new TenantCloudStorageConnection();
        $connection->forceFill([
            'tenant_id' => $tenantId,
            'oauth_client_id' => 'tenant-a.apps.googleusercontent.com',
            'oauth_client_secret' => 'tenant-a-client-secret',
            'refresh_token' => 'sensitive-refresh-token',
            'granted_scopes' => ['https://www.googleapis.com/auth/drive.file'],
            'status' => 'active',
            'connected_by_user_id' => $user->id,
            'connected_at' => now(),
        ])->save();

        $raw = DB::table('tenant_cloud_storage_connections')->where('tenant_id', $tenantId)->first();

        $this->assertNotSame('sensitive-refresh-token', $raw->refresh_token);
        $this->assertNotSame('tenant-a.apps.googleusercontent.com', $raw->oauth_client_id);
        $this->assertNotSame('tenant-a-client-secret', $raw->oauth_client_secret);
        $this->assertSame('sensitive-refresh-token', $connection->fresh()->refresh_token);
        $this->assertArrayNotHasKey('refresh_token', $connection->fresh()->toArray());
        $this->assertArrayNotHasKey('oauth_client_id', $connection->fresh()->toArray());
        $this->assertArrayNotHasKey('oauth_client_secret', $connection->fresh()->toArray());
        $this->assertStringNotContainsString('drive.file', (string) $raw->granted_scopes);
    }

    public function test_google_drive_oauth_clients_are_resolved_per_tenant(): void
    {
        [, $tenantId] = $this->fixture();
        $otherTenantId = DB::table('tenants')->insertGetId([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $first = new TenantCloudStorageConnection();
        $first->forceFill([
            'tenant_id' => $tenantId,
            'oauth_client_id' => 'first.apps.googleusercontent.com',
            'oauth_client_secret' => 'first-client-secret',
            'status' => 'configured',
        ])->save();

        $second = new TenantCloudStorageConnection();
        $second->forceFill([
            'tenant_id' => $otherTenantId,
            'oauth_client_id' => 'second.apps.googleusercontent.com',
            'oauth_client_secret' => 'second-client-secret',
            'status' => 'configured',
        ])->save();

        $factory = app(GoogleDriveClientFactory::class);
        $firstClient = $factory->baseClient($first->fresh());
        $secondClient = $factory->baseClient($second->fresh());

        $this->assertSame('first.apps.googleusercontent.com', $firstClient->getClientId());
        $this->assertSame('first-client-secret', $firstClient->getClientSecret());
        $this->assertSame('second.apps.googleusercontent.com', $secondClient->getClientId());
        $this->assertSame('second-client-secret', $secondClient->getClientSecret());
        $this->assertNotSame($firstClient->getClientId(), $secondClient->getClientId());
    }

    public function test_google_drive_connect_without_tenant_credentials_redirects_instead_of_failing(): void
    {
        [$user, $tenantId] = $this->fixture();
        $user->forceFill(['last_authenticated_at' => now()])->saveQuietly();

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenantId, 'tenant_slug' => 'tenant-a'])
            ->get('/tenant-a/settings/google-drive/connect')
            ->assertRedirect(route('filament.admin.pages.organization-settings-page', [
                'tab' => 'google-drive-tab',
            ]));

        $this->assertDatabaseMissing('tenant_cloud_storage_connections', [
            'tenant_id' => $tenantId,
        ]);
    }

    public function test_google_drive_connect_cannot_use_another_tenant_configuration(): void
    {
        [$user, $tenantId] = $this->fixture();
        $user->forceFill(['last_authenticated_at' => now()])->saveQuietly();
        $otherTenantId = DB::table('tenants')->insertGetId([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $connection = new TenantCloudStorageConnection();
        $connection->forceFill([
            'tenant_id' => $otherTenantId,
            'oauth_client_id' => 'other.apps.googleusercontent.com',
            'oauth_client_secret' => 'other-client-secret',
            'status' => 'configured',
        ])->save();

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenantId, 'tenant_slug' => 'tenant-a'])
            ->get('/tenant-a/settings/google-drive/connect')
            ->assertRedirect(route('filament.admin.pages.organization-settings-page', [
                'tab' => 'google-drive-tab',
            ]));
    }

    public function test_profile_cannot_change_password(): void
    {
        [$user] = $this->fixture();
        $originalPassword = $user->password;

        $this->actingAs($user)
            ->withSession(['tenant_id' => 1, 'tenant_slug' => 'tenant-a'])
            ->post('/tenant-a/profile', [
                'name' => 'Nome atualizado',
                'current_password' => 'secret',
                'password' => 'Changed-password-123',
                'password_confirmation' => 'Changed-password-123',
            ])
            ->assertRedirect();

        $this->assertSame($originalPassword, $user->fresh()->password);
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

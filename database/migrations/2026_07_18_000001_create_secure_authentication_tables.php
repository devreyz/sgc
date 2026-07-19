<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $authenticationColumnsAlreadyExist = Schema::hasColumn('users', 'webauthn_user_handle');

        if (! $authenticationColumnsAlreadyExist) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('webauthn_user_handle', 43)->nullable()->unique()->after('remember_token');
                $table->timestamp('last_authenticated_at')->nullable()->after('webauthn_user_handle');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->string('name')->nullable()->change();
                $table->string('email', 191)->nullable()->change();
                $table->string('password')->nullable()->change();
            });
        }

        DB::table('users')
            ->whereNull('webauthn_user_handle')
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')->where('id', $user->id)->update([
                        'webauthn_user_handle' => Str::random(43),
                    ]);
                }
            });

        if (! $authenticationColumnsAlreadyExist) {
            Schema::table('associates', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->foreignId('user_id')->nullable()->change();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('passkeys')) {
            Schema::create('passkeys', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('name')->nullable();
                $table->string('credential_id', 191)->unique();
                $table->longText('credential');
                $table->longText('public_key')->nullable();
                $table->unsignedBigInteger('sign_count')->default(0);
                $table->json('transports')->nullable();
                $table->uuid('aaguid')->nullable();
                $table->boolean('backup_eligible')->default(false);
                $table->boolean('backup_state')->default(false);
                $table->boolean('user_verified')->default(false);
                $table->string('rp_id', 255);
                $table->timestamp('last_used_at')->nullable();
                $table->char('created_ip_hash', 64)->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('revocation_reason')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'revoked_at']);
            });
        }

        if (! Schema::hasTable('oauth_accounts')) {
            Schema::create('oauth_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('provider', 32);
                $table->string('provider_subject', 191);
                $table->string('provider_email')->nullable();
                $table->boolean('provider_email_verified')->default(false);
                $table->timestamp('linked_at');
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
                $table->unique(['provider', 'provider_subject']);
                $table->unique(['user_id', 'provider']);
            });
        }

        DB::table('users')
            ->whereNotNull('google_id')
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    DB::table('oauth_accounts')->updateOrInsert(
                        ['provider' => 'google', 'provider_subject' => (string) $user->google_id],
                        [
                            'user_id' => $user->id,
                            'provider_email' => $user->email,
                            'provider_email_verified' => $user->email_verified_at !== null,
                            'linked_at' => now(),
                            'last_used_at' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            });

        if (! Schema::hasTable('access_invitations')) {
            Schema::create('access_invitations', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('associate_id')->constrained('associates')->cascadeOnDelete();
                $table->foreignId('issued_by_user_id')->constrained('users')->restrictOnDelete();
                $table->char('token_hash', 64)->unique();
                $table->string('code_hash');
                $table->string('status', 24)->default('pending');
                $table->timestamp('expires_at');
                $table->timestamp('claimed_at')->nullable();
                $table->char('claimed_session_hash', 64)->nullable();
                $table->timestamp('enrollment_expires_at')->nullable();
                $table->timestamp('consumed_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->unsignedTinyInteger('failed_attempts')->default(0);
                $table->timestamp('last_attempt_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'associate_id', 'status'], 'access_invitation_target_status_idx');
                $table->index(['status', 'expires_at']);
            });
        }

        if (! Schema::hasTable('security_events')) {
            Schema::create('security_events', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('event_type', 80);
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('associate_id')->nullable()->constrained('associates')->nullOnDelete();
                $table->ulid('invitation_id')->nullable();
                $table->string('result', 32);
                $table->char('ip_hash', 64)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->uuid('correlation_id');
                $table->json('context')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['tenant_id', 'event_type', 'created_at']);
                $table->index(['actor_user_id', 'created_at']);
                $table->index('invitation_id');
                $table->foreign('invitation_id')->references('id')->on('access_invitations')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('access_invitations');
        Schema::dropIfExists('oauth_accounts');
        Schema::dropIfExists('passkeys');

        Schema::table('associates', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['webauthn_user_handle']);
            $table->dropColumn(['webauthn_user_handle', 'last_authenticated_at']);
            $table->string('name')->nullable(false)->change();
            $table->string('email', 191)->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};

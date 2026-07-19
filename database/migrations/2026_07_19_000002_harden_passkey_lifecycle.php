<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ParagonIE\ConstantTime\Base64UrlSafe;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('passkeys', 'expires_at')) {
            Schema::table('passkeys', function (Blueprint $table) {
                $table->timestamp('expires_at')->nullable()->after('last_used_at');
                $table->index(['user_id', 'expires_at'], 'passkeys_user_expiry_idx');
            });
        }

        DB::table('passkeys')
            ->whereNull('expires_at')
            ->orderBy('id')
            ->chunkById(200, function ($passkeys): void {
                foreach ($passkeys as $passkey) {
                    DB::table('passkeys')->where('id', $passkey->id)->update([
                        'expires_at' => Carbon::parse($passkey->created_at)
                            ->addDays((int) config('passkeys.lifetime_days', 365)),
                    ]);
                }
            }, 'id');

        DB::table('users')
            ->whereNotNull('webauthn_user_handle')
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $canonical = $this->canonicalHandle((string) $user->webauthn_user_handle);

                    if ($canonical === null) {
                        $hasPasskeys = DB::table('passkeys')->where('user_id', $user->id)->exists();
                        if ($hasPasskeys) {
                            continue;
                        }
                        $canonical = Base64UrlSafe::encodeUnpadded(random_bytes(32));
                    }

                    if (! hash_equals((string) $user->webauthn_user_handle, $canonical)) {
                        DB::table('users')->where('id', $user->id)->update([
                            'webauthn_user_handle' => $canonical,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('passkeys', 'expires_at')) {
            Schema::table('passkeys', function (Blueprint $table) {
                $table->dropIndex('passkeys_user_expiry_idx');
                $table->dropColumn('expires_at');
            });
        }
    }

    private function canonicalHandle(string $handle): ?string
    {
        try {
            $decoded = Base64UrlSafe::decodeNoPadding($handle);
        } catch (Throwable) {
            $standard = strtr($handle, '-_', '+/');
            $standard .= str_repeat('=', (4 - strlen($standard) % 4) % 4);
            $decoded = base64_decode($standard, true);
        }

        return is_string($decoded) && strlen($decoded) === 32
            ? Base64UrlSafe::encodeUnpadded($decoded)
            : null;
    }
};

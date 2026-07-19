<?php

namespace App\Services;

use App\Models\OAuthAccount;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GoogleAccountService
{
    public function resolve(
        string $intent,
        string $subject,
        ?string $email,
        ?User $currentUser,
        int|string|null $expectedUserId,
    ): array {
        return DB::transaction(function () use ($intent, $subject, $email, $currentUser, $expectedUserId) {
            $account = OAuthAccount::query()
                ->where('provider', 'google')
                ->where('provider_subject', $subject)
                ->lockForUpdate()
                ->first();

            if ($account) {
                $user = User::query()->whereKey($account->user_id)->lockForUpdate()->firstOrFail();

                if (in_array($intent, ['link', 'reauth'], true)
                    && ((int) $expectedUserId !== (int) $currentUser?->id || (int) $user->id !== (int) $currentUser?->id)) {
                    throw new RuntimeException('Google account belongs to another user.');
                }
            } elseif ($intent === 'link') {
                if (! $currentUser
                    || (int) $expectedUserId !== (int) $currentUser->id
                    || ! $currentUser->recentlyAuthenticated()) {
                    throw new RuntimeException('Recent authentication required.');
                }

                if ($currentUser->oauthAccounts()->where('provider', 'google')->exists()) {
                    throw new RuntimeException('Google already linked.');
                }

                $user = $currentUser;
                $account = new OAuthAccount;
            } elseif ($intent === 'login') {
                $user = User::query()->where('google_id', $subject)->lockForUpdate()->first();

                if (! $user) {
                    if ($email && User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                        throw new RuntimeException('Email collision requires account proof.');
                    }
                    throw new RuntimeException('Google account is not linked.');
                }

                $account = new OAuthAccount;
            } else {
                throw new RuntimeException('Google account is not linked.');
            }

            if (! $user->status) {
                throw new RuntimeException('Inactive user.');
            }

            $hasActiveMembership = $user->isSuperAdmin() || TenantUser::query()
                ->where('user_id', $user->id)
                ->where('status', true)
                ->exists();
            if (! $hasActiveMembership) {
                throw new RuntimeException('No active membership.');
            }

            $account->forceFill([
                'user_id' => $user->id,
                'provider' => 'google',
                'provider_subject' => $subject,
                'provider_email' => $email,
                'provider_email_verified' => true,
                'linked_at' => $account->linked_at ?? now(),
                'last_used_at' => now(),
            ])->save();

            return [$user, $account];
        });
    }
}

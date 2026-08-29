<?php

namespace App\Http\Controllers;

use App\Models\Associate;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MemberCardValidationController extends Controller
{
    /**
     * Validate a member card by token.
     */
    public function verifyCard(Request $request, string $token)
    {
        // Tokens are generated with 64 URL-safe characters. Reject malformed
        // requests before querying, which reduces accidental probing of this
        // public endpoint.
        if (! preg_match('/^[A-Za-z0-9_-]{40,100}$/', $token)) {
            return $this->invalid();
        }

        $associate = Associate::query()
            ->where('validation_token', $token)
            ->with('user:id,status')
            ->first();

        if (! $associate) {
            return $this->invalid();
        }

        $tenant = Tenant::find($associate->tenant_id);
        
        if (! $tenant || ! $tenant->active) {
            return $this->invalid();
        }

        $user = $associate->user;

        if (! $user?->status) {
            return $this->invalid();
        }

        return response()->view('member-card.valid', [
            'associate' => $associate,
            'tenant' => $tenant,
            // A route pública confirma autenticidade; não é uma ficha cadastral.
            'memberDisplayName' => $this->maskName($associate->display_name),
            'memberCode' => $this->maskCode((string) ($associate->member_code ?: $associate->registration_number ?: $associate->id)),
        ], 200, $this->privateHeaders());
    }

    private function invalid()
    {
        return response()->view('member-card.invalid', [
            'message' => 'Não foi possível confirmar a autenticidade desta carteirinha.',
        ], 404, $this->privateHeaders());
    }

    private function maskName(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) <= 1) {
            return Str::of($parts[0] ?? 'Associado')->substr(0, 1)->append('.')->toString();
        }

        return $parts[0].' '.Str::of((string) end($parts))->substr(0, 1).'.';
    }

    private function maskCode(string $code): string
    {
        $suffix = mb_substr($code, -4);

        return '••••'.$suffix;
    }

    private function privateHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ];
    }
}

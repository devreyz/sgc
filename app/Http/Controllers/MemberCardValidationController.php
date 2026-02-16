<?php

namespace App\Http\Controllers;

use App\Models\Associate;
use App\Models\Tenant;
use Illuminate\Http\Request;

class MemberCardValidationController extends Controller
{
    /**
     * Validate a member card by token.
     */
    public function verifyCard(Request $request, $token)
    {
        $associate = Associate::where('validation_token', $token)->first();

        if (!$associate) {
            return view('member-card.invalid', [
                'message' => 'Carteirinha inválida ou não encontrada.',
            ]);
        }

        $tenant = Tenant::find($associate->tenant_id);
        
        if (!$tenant || !$tenant->active) {
            return view('member-card.invalid', [
                'message' => 'Associação inativa ou não encontrada.',
            ]);
        }

        // Get user info
        $user = $associate->user;

        if (!$user) {
            return view('member-card.invalid', [
                'message' => 'Usuário não encontrado.',
            ]);
        }

        return view('member-card.valid', [
            'associate' => $associate,
            'tenant' => $tenant,
            'user' => $user,
        ]);
    }
}

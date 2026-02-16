<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * Show the user profile page.
     */
    public function show(Request $request)
    {
        $user = Auth::user();
        
        // Get current tenant from route or session
        $tenant = null;
        if ($request->route('tenant')) {
            $routeTenant = $request->route('tenant');
            $tenant = is_string($routeTenant) 
                ? Tenant::where('slug', $routeTenant)->first() 
                : $routeTenant;
        } elseif (session('tenant_id')) {
            $tenant = Tenant::find(session('tenant_id'));
        }

        // Get user's tenants
        $userTenants = $user->tenants;
        
        // Check if user is admin
        $isAdmin = $user->hasRole('super_admin') || $user->hasRole('admin');

        return view('profile.show', [
            'user' => $user,
            'tenant' => $tenant,
            'tenants' => $userTenants,
            'isAdmin' => $isAdmin,
        ]);
    }

    /**
     * Update user profile.
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('super_admin') || $user->hasRole('admin');

        // Build validation rules
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'string'], // Base64 string
            'current_password' => ['nullable', 'required_with:password'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];

        // Only validate email if user is admin
        if ($isAdmin) {
            $rules['email'] = ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id];
        }

        $validated = $request->validate($rules);

        // Update basic info
        $user->name = $validated['name'];
        
        // Only update email if user is admin
        if ($isAdmin && $request->filled('email')) {
            $user->email = $request->email;
        }

        // Handle avatar upload (base64 WebP image)
        if ($request->filled('avatar')) {
            $base64Image = $request->avatar;
            
            // Extract base64 data
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
                $base64Image = substr($base64Image, strpos($base64Image, ',') + 1);
                $type = strtolower($type[1]); // jpg, png, gif, webp, etc.
                
                // Decode base64
                $imageData = base64_decode($base64Image);
                
                if ($imageData !== false) {
                    // Delete old avatar if exists
                    if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                        Storage::disk('public')->delete($user->avatar);
                    }
                    
                    // Generate unique filename
                    $filename = 'avatars/' . uniqid() . '_' . time() . '.webp';
                    
                    // Save to storage
                    Storage::disk('public')->put($filename, $imageData);
                    $user->avatar = $filename;
                }
            }
        }

        // Handle password change
        if ($request->filled('password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return back()->withErrors(['current_password' => 'A senha atual está incorreta.']);
            }
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return back()->with('success', 'Perfil atualizado com sucesso!');
    }

    /**
     * Remove user avatar.
     */
    public function removeAvatar(Request $request)
    {
        $user = Auth::user();

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->avatar = null;
        $user->save();

        return back()->with('success', 'Foto de perfil removida com sucesso!');
    }
}

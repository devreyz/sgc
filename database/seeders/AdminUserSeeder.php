<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'josereisleite2016@gmail.com',
            'password' => Hash::make('hdhdjsdlhsehreiygkgf7vcbfed'),
            'status' => true,
        ])->assignRole('super_admin');
    }
}

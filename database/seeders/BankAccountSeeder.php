<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use Illuminate\Database\Seeder;

class BankAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Criar conta de caixa padrão
        BankAccount::firstOrCreate(
            ['type' => 'caixa'],
            [
                'name' => 'Caixa da Cooperativa',
                'type' => 'caixa',
                'initial_balance' => 0,
                'current_balance' => 0,
                'balance_date' => now(),
                'is_default' => true,
                'status' => true,
                'notes' => 'Conta de caixa principal da cooperativa',
            ]
        );

        // Exemplo de conta bancária
        BankAccount::firstOrCreate(
            [
                'bank_code' => '001',
                'agency' => '1234',
                'account_number' => '56789',
            ],
            [
                'name' => 'Banco do Brasil - Conta Corrente',
                'type' => 'corrente',
                'bank_code' => '001',
                'bank_name' => 'Banco do Brasil',
                'agency' => '1234',
                'agency_digit' => '5',
                'account_number' => '56789',
                'account_digit' => '0',
                'initial_balance' => 0,
                'current_balance' => 0,
                'balance_date' => now(),
                'is_default' => false,
                'status' => true,
            ]
        );
    }
}

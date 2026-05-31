<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Para cada cliente com preços legados (customer_product_prices):
     * 1. Cria uma PriceTable "Migrado – {cliente}"
     * 2. Cria PriceTableItems para cada linha
     * 3. Vincula o cliente à nova tabela (se ainda não tiver uma)
     */
    public function up(): void
    {
        if (! Schema::hasTable('customer_product_prices')) {
            return;
        }

        $rows = DB::table('customer_product_prices')
            ->whereNull('deleted_at')
            ->get();

        $byCustomer = [];
        foreach ($rows as $row) {
            $byCustomer[$row->customer_id][] = $row;
        }

        foreach ($byCustomer as $customerId => $prices) {
            $customer = DB::table('customers')->where('id', $customerId)->first();
            if (! $customer || $customer->price_table_id) {
                continue;
            }

            $tableName = 'Migrado – ' . ($customer->trade_name ?: $customer->name);

            $priceTableId = DB::table('price_tables')->insertGetId([
                'tenant_id'  => $customer->tenant_id,
                'name'       => $tableName,
                'code'       => 'MIGRADO-' . strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', $customer->name ?? 'X'), 0, 20)),
                'year'       => (int) date('Y'),
                'active'     => true,
                'notes'      => 'Criada automaticamente a partir de preços legados do cliente.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $inserted = [];
            foreach ($prices as $price) {
                if (in_array($price->product_id, $inserted)) {
                    continue; // evitar duplicatas
                }
                $inserted[] = $price->product_id;

                DB::table('price_table_items')->insert([
                    'price_table_id' => $priceTableId,
                    'product_id'     => $price->product_id,
                    'sale_price'     => $price->sale_price,
                    'cost_price'     => $price->cost_price,
                    'notes'          => $price->project_id ? 'Migrado de preço por projeto #' . $price->project_id : null,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }

            DB::table('customers')
                ->where('id', $customerId)
                ->update(['price_table_id' => $priceTableId]);
        }
    }

    public function down(): void
    {
        $tables = DB::table('price_tables')
            ->where('notes', 'like', 'Criada automaticamente%')
            ->pluck('id');

        if ($tables->isNotEmpty()) {
            DB::table('price_table_items')->whereIn('price_table_id', $tables)->delete();
            DB::table('customers')->whereIn('price_table_id', $tables)->update(['price_table_id' => null]);
            DB::table('price_tables')->whereIn('id', $tables)->delete();
        }
    }
};

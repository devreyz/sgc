<?php

namespace Database\Seeders;

use App\Models\ChartAccount;
use Illuminate\Database\Seeder;

class ChartAccountSeeder extends Seeder
{
    public function run(): void
    {
        // Receitas
        $receitas = ChartAccount::create([
            'code' => '1',
            'name' => 'Receitas',
            'type' => 'receita',
            'status' => true,
        ]);

        ChartAccount::create([
            'code' => '1.1',
            'name' => 'Taxa Administrativa',
            'type' => 'receita',
            'parent_id' => $receitas->id,
            'status' => true,
        ]);

        ChartAccount::create([
            'code' => '1.2',
            'name' => 'Serviços Prestados',
            'type' => 'receita',
            'parent_id' => $receitas->id,
            'status' => true,
        ]);

        ChartAccount::create([
            'code' => '1.3',
            'name' => 'Venda de Produtos',
            'type' => 'receita',
            'parent_id' => $receitas->id,
            'status' => true,
        ]);

        // Despesas
        $despesas = ChartAccount::create([
            'code' => '2',
            'name' => 'Despesas',
            'type' => 'despesa',
            'status' => true,
        ]);

        ChartAccount::create([
            'code' => '2.1',
            'name' => 'Despesas Administrativas',
            'type' => 'despesa',
            'parent_id' => $despesas->id,
            'status' => true,
        ]);

        ChartAccount::create([
            'code' => '2.2',
            'name' => 'Combustível',
            'type' => 'despesa',
            'parent_id' => $despesas->id,
            'status' => true,
        ]);

        ChartAccount::create([
            'code' => '2.3',
            'name' => 'Manutenção de Equipamentos',
            'type' => 'despesa',
            'parent_id' => $despesas->id,
            'status' => true,
        ]);

        ChartAccount::create([
            'code' => '2.4',
            'name' => 'Pessoal',
            'type' => 'despesa',
            'parent_id' => $despesas->id,
            'status' => true,
        ]);

        ChartAccount::create([
            'code' => '2.5',
            'name' => 'Utilidades',
            'type' => 'despesa',
            'parent_id' => $despesas->id,
            'status' => true,
        ]);
    }
}

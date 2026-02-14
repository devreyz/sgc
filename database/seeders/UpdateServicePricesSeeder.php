<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Service;

class UpdateServicePricesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Atualizando preços dos serviços...');
        
        $services = Service::all();
        
        foreach ($services as $service) {
            // Se não tem associate_price, usar base_price
            if (!$service->associate_price && $service->base_price) {
                $service->associate_price = $service->base_price;
            }
            
            // Se não tem non_associate_price, adicionar 20% ao base_price
            if (!$service->non_associate_price && $service->base_price) {
                $service->non_associate_price = $service->base_price * 1.20;
            }
            
            $service->save();
        }
        
        $this->command->info('Preços atualizados com sucesso!');
        $this->command->info("Total de serviços atualizados: {$services->count()}");
    }
}

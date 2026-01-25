<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Enums\ServiceType;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        Service::create([
            'code' => 'SRV001',
            'name' => 'Hora de Trator',
            'type' => ServiceType::HORA_MAQUINA,
            'unit' => 'hora',
            'base_price' => 150.00,
            'description' => 'Serviço de trator para preparo de solo',
            'status' => true,
        ]);

        Service::create([
            'code' => 'SRV002',
            'name' => 'Hora de Colheitadeira',
            'type' => ServiceType::HORA_MAQUINA,
            'unit' => 'hora',
            'base_price' => 350.00,
            'description' => 'Serviço de colheitadeira',
            'status' => true,
        ]);

        Service::create([
            'code' => 'SRV003',
            'name' => 'Frete Interno',
            'type' => ServiceType::FRETE,
            'unit' => 'km',
            'base_price' => 5.00,
            'description' => 'Frete dentro do município',
            'status' => true,
        ]);

        Service::create([
            'code' => 'SRV004',
            'name' => 'Frete Externo',
            'type' => ServiceType::FRETE,
            'unit' => 'km',
            'base_price' => 7.50,
            'description' => 'Frete intermunicipal',
            'status' => true,
        ]);

        Service::create([
            'code' => 'SRV005',
            'name' => 'Pulverização',
            'type' => ServiceType::HORA_MAQUINA,
            'unit' => 'hectare',
            'base_price' => 80.00,
            'description' => 'Serviço de pulverização',
            'status' => true,
        ]);

        Service::create([
            'code' => 'SRV006',
            'name' => 'Assistência Técnica',
            'type' => ServiceType::CONSULTORIA,
            'unit' => 'visita',
            'base_price' => 200.00,
            'description' => 'Visita técnica para orientação',
            'status' => true,
        ]);
    }
}

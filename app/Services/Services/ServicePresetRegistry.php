<?php

namespace App\Services\Services;

class ServicePresetRegistry
{
    public function all(): array
    {
        return [
            'tractor_hour' => ['label' => 'Trator / hora', 'unit' => 'hora', 'customer_pricing_method' => 'quantity_x_rate', 'review_mode' => 'manual', 'execution_config' => ['meter_start_field' => 'horimeter_start', 'meter_end_field' => 'horimeter_end', 'quantity_field' => null, 'meters' => [['start_key' => 'horimeter_start', 'end_key' => 'horimeter_end', 'increasing' => true]]], 'fields' => [
                ['key' => 'location', 'label' => 'Localidade', 'type' => 'location', 'phase' => 'order', 'required' => true, 'reportable' => true],
                ['key' => 'horimeter_start', 'label' => 'Horímetro inicial', 'type' => 'meter', 'phase' => 'start', 'required' => true, 'unit' => 'h', 'reportable' => true],
                ['key' => 'before_photo', 'label' => 'Foto inicial', 'type' => 'image', 'phase' => 'start', 'required' => true, 'include_in_documents' => true],
                ['key' => 'horimeter_end', 'label' => 'Horímetro final', 'type' => 'meter', 'phase' => 'finish', 'required' => true, 'unit' => 'h', 'reportable' => true],
                ['key' => 'after_photo', 'label' => 'Foto final', 'type' => 'image', 'phase' => 'finish', 'required' => true, 'include_in_documents' => true],
                ['key' => 'fuel_used', 'label' => 'Combustível utilizado', 'type' => 'quantity', 'phase' => 'finish', 'required' => false, 'unit' => 'L', 'reportable' => true],
                ['key' => 'notes', 'label' => 'Observação', 'type' => 'textarea', 'phase' => 'finish', 'required' => false],
            ]],
            'freight' => ['label' => 'Frete', 'unit' => 'km', 'customer_pricing_method' => 'quantity_x_rate', 'review_mode' => 'manual', 'execution_config' => ['quantity_field' => 'distance'], 'fields' => [
                ['key' => 'origin', 'label' => 'Origem', 'type' => 'location', 'phase' => 'order', 'required' => true, 'reportable' => true],
                ['key' => 'destination', 'label' => 'Destino', 'type' => 'location', 'phase' => 'order', 'required' => true, 'reportable' => true],
                ['key' => 'distance', 'label' => 'Distância percorrida', 'type' => 'quantity', 'phase' => 'finish', 'required' => true, 'unit' => 'km', 'reportable' => true],
                ['key' => 'delivery_proof', 'label' => 'Comprovante de entrega', 'type' => 'image', 'phase' => 'finish', 'required' => true, 'include_in_documents' => true],
            ]],
            'simple' => ['label' => 'Serviço simples', 'unit' => 'un', 'customer_pricing_method' => 'fixed', 'review_mode' => 'automatic', 'execution_config' => [], 'fields' => [
                ['key' => 'description', 'label' => 'O que foi realizado?', 'type' => 'textarea', 'phase' => 'finish', 'required' => true, 'include_in_documents' => true],
            ]],
        ];
    }

    public function get(?string $key): array
    {
        return $this->all()[$key] ?? $this->all()['simple'];
    }
}

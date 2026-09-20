<?php

namespace App\Services\Services;

use App\Enums\ServiceType;

class ServicePresetRegistry
{
    public function all(): array
    {
        return [
            'tractor_hour' => $this->preset('Máquina por horímetro', 'Para tratores e máquinas cobrados pela diferença do horímetro.', ServiceType::HORA_MAQUINA, 'hora', 'meter_difference', $this->meterFields(), true, true, ['meter_start_field' => 'horimeter_start', 'meter_end_field' => 'horimeter_end']),
            'freight' => $this->preset('Frete por distância', 'Registra origem, destino, quilômetros e comprovante de entrega.', ServiceType::FRETE, 'km', 'field', [
                $this->field('origin', 'Origem', 'location', 'order', true),
                $this->field('destination', 'Destino', 'location', 'order', true),
                $this->field('distance', 'Distância percorrida', 'quantity', 'finish', true, 'km'),
                $this->field('delivery_proof', 'Comprovante de entrega', 'image', 'finish', true, null, true),
            ], true, true, ['quantity_field' => 'distance']),
            'daily_labor' => $this->preset('Mão de obra por diária', 'Controla o local, as diárias e o trabalho realizado.', ServiceType::MAO_DE_OBRA, 'dia', 'field', [
                $this->field('work_location', 'Local do serviço', 'location', 'order', true),
                $this->field('daily_quantity', 'Quantidade de diárias', 'quantity', 'finish', true, 'dia'),
                $this->field('work_report', 'Atividades realizadas', 'textarea', 'finish', true, null, true),
                $this->field('completion_photo', 'Foto da conclusão', 'image', 'finish', false, null, true),
            ], true, true, ['quantity_field' => 'daily_quantity']),
            'technical_visit' => $this->preset('Visita técnica', 'Registra local, diagnóstico, duração e conclusão da visita.', ServiceType::VISITA_TECNICA, 'visita', 'fixed_one', [
                $this->field('visit_location', 'Local da visita', 'location', 'order', true),
                $this->field('diagnosis', 'Diagnóstico', 'textarea', 'execution', true, null, true),
                $this->field('minutes_spent', 'Tempo utilizado', 'integer', 'finish', false, 'min'),
                $this->field('visit_report', 'Relatório da visita', 'file', 'finish', false, null, true),
            ], true, true),
            'maintenance' => $this->preset('Manutenção ou reparo', 'Registra equipamento, defeito, solução e evidências.', ServiceType::MANUTENCAO, 'serviço', 'fixed_one', [
                $this->field('equipment', 'Equipamento atendido', 'asset', 'order', true),
                $this->field('reported_problem', 'Problema informado', 'textarea', 'order', true),
                $this->field('diagnosis', 'Diagnóstico técnico', 'textarea', 'execution', true, null, true),
                $this->field('solution', 'Serviço realizado', 'textarea', 'finish', true, null, true),
                $this->field('after_photo', 'Foto após o reparo', 'image', 'finish', false, null, true),
            ], true, true),
            'rental' => $this->preset('Locação por período', 'Controla retirada, devolução e dias de locação.', ServiceType::LOCACAO, 'dia', 'field', [
                $this->field('rented_asset', 'Item locado', 'asset', 'order', true),
                $this->field('checkout_at', 'Data e hora da retirada', 'datetime', 'start', true),
                $this->field('return_at', 'Data e hora da devolução', 'datetime', 'finish', true),
                $this->field('rental_days', 'Dias cobrados', 'quantity', 'finish', true, 'dia'),
                $this->field('return_photo', 'Foto da devolução', 'image', 'finish', false, null, true),
            ], true, false, ['quantity_field' => 'rental_days']),
            'production' => $this->preset('Beneficiamento por quantidade', 'Para moagem, secagem, seleção, embalagem e serviços por peso ou volume.', ServiceType::BENEFICIAMENTO, 'kg', 'field', [
                $this->field('input_quantity', 'Quantidade recebida', 'quantity', 'start', true, 'kg'),
                $this->field('output_quantity', 'Quantidade processada', 'quantity', 'finish', true, 'kg'),
                $this->field('batch', 'Lote ou referência', 'text', 'order', false),
                $this->field('processing_notes', 'Observações do processamento', 'textarea', 'finish', false, null, true),
            ], true, true, ['quantity_field' => 'output_quantity']),
            'consulting_hour' => $this->preset('Consultoria por horas', 'Registra assunto, horas trabalhadas e relatório.', ServiceType::CONSULTORIA, 'hora', 'field', [
                $this->field('subject', 'Assunto da consultoria', 'text', 'order', true),
                $this->field('hours_worked', 'Horas trabalhadas', 'decimal', 'finish', true, 'hora'),
                $this->field('consulting_report', 'Relatório ou orientação', 'textarea', 'finish', true, null, true),
            ], true, true, ['quantity_field' => 'hours_worked']),
            'simple' => $this->preset('Serviço simples por execução', 'Uma cobrança por serviço concluído, sem cálculo de quantidade.', ServiceType::OUTRO, 'serviço', 'fixed_one', [
                $this->field('description', 'O que foi realizado?', 'textarea', 'finish', true, null, true),
                $this->field('completion_proof', 'Comprovante da conclusão', 'image', 'finish', false, null, true),
            ], true, false),
        ];
    }

    public function get(?string $key): array
    {
        return $this->all()[$key] ?? $this->all()['simple'];
    }

    private function preset(string $label, string $description, ServiceType $type, string $unit, string $quantityMode, array $fields, bool $receivable, bool $payable, array $execution = []): array
    {
        $method = $quantityMode === 'fixed_one' ? 'fixed' : 'quantity_x_rate';

        return [
            'label' => $label, 'description' => $description, 'service_type' => $type->value,
            'unit' => $unit, 'review_mode' => 'manual', 'receivable_enabled' => $receivable,
            'customer_pricing_method' => $method, 'payable_enabled' => $payable,
            'provider_pricing_method' => $payable ? $method : null,
            'execution_config' => array_replace([
                'quantity_mode' => $quantityMode, 'customer_quantity_mode' => 'primary',
                'provider_quantity_mode' => 'primary', 'customer_unit' => $unit, 'provider_unit' => $unit,
            ], $execution),
            'fields' => $fields,
        ];
    }

    private function meterFields(): array
    {
        return [
            $this->field('location', 'Local do serviço', 'location', 'order', true),
            $this->field('horimeter_start', 'Horímetro inicial', 'meter', 'start', true, 'h'),
            $this->field('before_photo', 'Foto da leitura inicial', 'image', 'start', true, null, true, 'horimeter_start'),
            $this->field('horimeter_end', 'Horímetro final', 'meter', 'finish', true, 'h'),
            $this->field('after_photo', 'Foto da leitura final', 'image', 'finish', true, null, true, 'horimeter_end'),
            $this->field('fuel_used', 'Combustível utilizado', 'quantity', 'finish', false, 'L', true),
            $this->field('notes', 'Observações', 'textarea', 'finish', false, null, true),
        ];
    }

    private function field(string $key, string $label, string $type, string $phase, bool $required, ?string $unit = null, bool $documents = false, ?string $evidenceFor = null): array
    {
        return [
            'key' => $key, 'label' => $label, 'type' => $type, 'phase' => $phase,
            'required' => $required, 'unit' => $unit, 'reportable' => true,
            'visible_to_provider' => true, 'editable_by_provider' => true,
            'visible_to_management' => true, 'include_in_documents' => $documents,
            'evidence_for_field' => $evidenceFor,
        ];
    }
}

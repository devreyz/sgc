<?php

namespace App\Services\Services;

use App\Models\DocumentTemplate;
use App\Models\ServiceOrder;
use App\Models\Tenant;
use App\Services\SystemPdfConfigurationResolver;
use App\Services\TemplatedPdfService;
use App\Support\PdfQuantityFormatter;
use Illuminate\Http\Response;

class ServiceDocumentService
{
    public const PDF_VIEWS = [
        'service_order' => 'pdf.service-order',
        'service_internal_note' => 'pdf.service-internal-note',
        'service_execution' => 'pdf.service-execution',
        'service_negotiation' => 'pdf.service-negotiation',
        'service_report' => 'pdf.service-accountability',
    ];

    public static function extraContent(?DocumentTemplate $template, string $slot): string
    {
        return app(\App\Services\DocumentContentSanitizer::class)->sanitize((string) data_get($template?->report_contents, $slot, ''));
    }

    public function orderPdf(Tenant $tenant, ServiceOrder $order, string $copy, bool $financialVisible, bool $inline = true): Response
    {
        $label = $copy === 'beneficiary' ? 'Via do beneficiário' : 'Via da associação';

        return $this->pdf($tenant, 'service_order', 'Ordem de serviço '.$order->number.' · '.$label,
            $this->orderVariables($order, $financialVisible) + ['copy' => $copy], $inline,
            'Ordem de serviço', $order->number.' · '.$label);
    }

    public function pdf(Tenant $tenant, string $templateKey, string $title, array $variables, bool $inline = true, ?string $heading = null, ?string $subtitle = null): Response
    {
        $configurationView = self::PDF_VIEWS[$templateKey];
        $config = app(SystemPdfConfigurationResolver::class)->resolve($configurationView, $tenant->id);
        $template = $config['template'] ?? null;
        $sections = $config['visible_sections'] ?? [];
        $columns = $config['visible_columns'] ?? [];
        $content = view($templateKey === 'service_negotiation' ? 'pdf.partials.service-negotiation-body' : 'pdf.partials.service-order-body',
            compact('variables', 'sections', 'columns', 'templateKey'))->render();
        $body = self::extraContent($template, 'intro').$content.self::extraContent($template, 'closing');
        $pdf = app(TemplatedPdfService::class)->generateSystemPdf('pdf.service-issued-document', [
            'tenant' => $tenant, 'title' => $heading ?? $title, 'subtitle' => $subtitle, 'body' => $body,
            'suppress_internal_footer' => true, 'hide_header_email' => true,
        ], ['configuration_view' => $configurationView, 'title' => $heading ?? $title, 'standard_header' => true,
            'header_subtitle' => $subtitle, 'paper' => 'a4', 'orientation' => 'portrait', 'prefer_runtime_layout' => true]);
        $filename = str($title)->slug().'.pdf';
        $response = $inline ? $pdf->stream($filename) : $pdf->download($filename);
        $response->headers->add([
            'Cache-Control' => 'no-store, private',
            'X-SGC-Document-Path' => 'Serviços/Documentos',
            'X-SGC-Document-Title' => $title,
        ]);

        return $response;
    }

    public function orderVariables(ServiceOrder $order, bool $financialVisible): array
    {
        $execution = $order->execution;
        $fields = collect(data_get($execution->catalog_snapshot, 'fields', []))
            ->filter(fn (array $field) => ($field['include_in_documents'] ?? false)
                && ! in_array($field['type'] ?? null, ['file', 'image', 'signature'], true))
            ->map(function (array $field) use ($execution): array {
                $key = $field['key'];
                $raw = data_get($execution->values, $key);
                $value = match (true) {
                    is_bool($raw) => $raw ? 'Sim' : 'Não',
                    is_array($raw) => implode(', ', $raw),
                    default => trim((string) $raw),
                };

                return ['key' => $key, 'label' => $field['label'] ?? $key,
                    'value' => in_array($value, ['-', '–', '—'], true) ? '' : $value,
                    'unit' => $field['unit'] ?? ''];
            });
        [$meterStart, $meterEnd] = ServiceCalculationRules::meterPair((array) $execution->catalog_snapshot);
        $pauses = $meterStart ? json_decode((string) data_get($execution->values, 'meter_pauses_'.$meterStart, '[]'), true) : [];
        $frozen = $execution->isFrozen();
        $preview = (array) ($execution->management_preview ?? []);
        $receivable = $financialVisible && $frozen ? $execution->obligations->where('direction', 'receivable') : collect();
        $payable = $financialVisible && $frozen ? $execution->obligations->where('direction', 'payable') : collect();
        $totalReceived = $receivable->sum('paid_amount');
        $totalDue = $receivable->isNotEmpty() ? $receivable->sum('total_amount')
            : ($financialVisible ? ($frozen ? $execution->compositionLines->where('direction', 'receivable')->sum('amount') : ($preview['receivable_total'] ?? null)) : null);
        $adjustments = $receivable->flatMap->adjustments;
        $quantity = $execution->quantity ?? ($preview['quantity'] ?? null);

        return [
            'order_number' => $order->number, 'service_name' => $order->service?->name,
            'provider_name' => data_get($order->provider_snapshot, 'name') ?: $order->serviceProvider?->name,
            'beneficiary_name' => data_get($order->beneficiary_snapshot, 'name'),
            'scheduled_at' => $order->scheduled_at?->format('d/m/Y H:i') ?? $order->scheduled_date?->format('d/m/Y'),
            'execution_date' => $order->execution_date?->format('d/m/Y'),
            'start_time' => $order->start_time ? substr($order->start_time, 0, 5) : null,
            'end_time' => $order->end_time ? substr($order->end_time, 0, 5) : null,
            'location' => $order->location, 'notes' => data_get($order->order_data, 'notes'),
            'quantity' => $quantity === null ? null : PdfQuantityFormatter::format($quantity),
            'unit' => $execution->unit,
            'frozen' => $frozen, 'fields' => $fields, 'meter_keys' => array_filter([$meterStart, $meterEnd]),
            'meter_pauses' => is_array($pauses) ? array_filter($pauses, 'is_array') : [],
            'financial_visible' => $financialVisible, 'total_due' => $totalDue,
            'total_received' => $totalReceived, 'balance' => $totalDue === null ? null : max(0, $totalDue - $totalReceived),
            'provider_total' => $payable->isNotEmpty() ? $payable->sum('total_amount') : null,
            'receivable_lines' => $financialVisible && $frozen ? $execution->compositionLines->where('direction', 'receivable') : collect(),
            'adjustments' => $adjustments,
        ];
    }
}

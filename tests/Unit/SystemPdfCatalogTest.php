<?php

namespace Tests\Unit;

use App\Models\DocumentTemplate;
use PHPUnit\Framework\TestCase;

class SystemPdfCatalogTest extends TestCase
{
    public function test_every_operational_pdf_has_a_catalog_definition(): void
    {
        foreach ([
            'pdf.delivery-sheet',
            'pdf.distributions-by-customer',
            'pdf.distributions-by-customer-compact',
            'pdf.customer-delivery-statement',
            'pdf.project-associate-receipt',
            'pdf.associate-payment-statement',
            'pdf.associate-receipt-payments',
            'pdf.customer-billing-receipt',
            'pdf.customer-organization-receipt',
            'pdf.service-order',
        ] as $view) {
            $definition = DocumentTemplate::systemDefinitionForView($view);

            $this->assertNotNull($definition, "The {$view} PDF must be configurable in the system catalog.");
            $this->assertNotEmpty($definition['key']);
            $this->assertNotEmpty($definition['label']);
        }
    }

    public function test_catalog_does_not_keep_legacy_duplicate_views(): void
    {
        $views = array_column(DocumentTemplate::getSystemTemplateDefinitions(), 'blade_view');

        $this->assertNotContains('pdf.deliveries-report', $views);
        $this->assertNotContains('pdf.project-final-report', $views);
    }

    public function test_every_operational_pdf_uses_the_shared_design_system(): void
    {
        foreach (DocumentTemplate::getSystemTemplateDefinitions() as $definition) {
            $path = dirname(__DIR__, 2).'/resources/views/'.str_replace('.', '/', $definition['blade_view']).'.blade.php';
            $source = file_get_contents($path);

            $this->assertTrue(
                str_contains($source, "@extends('pdf.partials.header')")
                    || str_contains($source, "@include('pdf.partials.theme')"),
                "The {$definition['blade_view']} PDF must use the shared PDF theme.",
            );
        }
    }

    public function test_dense_reports_define_concise_default_columns(): void
    {
        $definitions = DocumentTemplate::getSystemTemplateDefinitions();

        foreach (['deliveries_associate', 'deliveries_product', 'deliveries_report', 'project_final_report'] as $key) {
            $this->assertNotEmpty($definitions[$key]['default_columns'] ?? null);
            $this->assertLessThan(
                count($definitions[$key]['columns']),
                count($definitions[$key]['default_columns']),
                "The {$key} report should not show every available column by default.",
            );
        }
    }
}

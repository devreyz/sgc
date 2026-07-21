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
}

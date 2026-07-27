<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\PdfLayoutTemplate;
use App\Services\DocumentGeneratorService;
use App\Services\TemplatedPdfService;
use Tests\TestCase;

class PdfExtensiveVariablesTest extends TestCase
{
    public function test_pdf_catalog_exposes_typed_extensive_variables(): void
    {
        $documentVariables = collect(DocumentTemplate::getAvailableVariables())->collapse();
        $layoutVariables = PdfLayoutTemplate::getAvailableVariables();

        foreach ([
            '{{projeto.valor_total_extenso}}',
            '{{projeto.taxa_admin_extenso}}',
            '{{financeiro.valor_extenso}}',
            '{{financeiro.saldo_extenso}}',
            '{{data.ano_atual_extenso}}',
        ] as $variable) {
            $this->assertTrue($documentVariables->has($variable), $variable.' is missing from document variables.');
            $this->assertArrayHasKey($variable, $layoutVariables);
        }
    }

    public function test_templated_pdf_resolves_money_percentage_dates_and_year_in_words(): void
    {
        $variables = app(TemplatedPdfService::class)->resolveSystemVariables(null, [
            '{{financeiro.valor}}' => 1250.75,
            '{{financeiro.saldo}}' => 50.01,
            '{{projeto.valor_total}}' => 2000,
            '{{projeto.taxa_admin}}' => 7.5,
            '{{projeto.data_inicio}}' => '27/07/2026',
        ]);

        $this->assertSame('mil duzentos e cinquenta reais e setenta e cinco centavos', $variables['{{financeiro.valor_extenso}}']);
        $this->assertSame('cinquenta reais e um centavo', $variables['{{financeiro.saldo_extenso}}']);
        $this->assertSame('dois mil reais', $variables['{{projeto.valor_total_extenso}}']);
        $this->assertSame('sete vírgula cinco por cento', $variables['{{projeto.taxa_admin_extenso}}']);
        $this->assertSame('27 de julho de 2026', $variables['{{projeto.data_inicio_extenso}}']);
        $this->assertNotSame('', $variables['{{data.ano_atual_extenso}}']);
    }

    public function test_document_generator_calculates_financial_value_in_words(): void
    {
        $variables = app(DocumentGeneratorService::class)
            ->setFinancialValue(10.25)
            ->getVariables();

        $this->assertSame('dez reais e vinte e cinco centavos', $variables['{{financeiro.valor_extenso}}']);
    }
}

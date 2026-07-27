<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\AssociateReceipt;
use App\Models\SalesProject;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PdfRenderingCompatibilityTest extends TestCase
{
    public function test_updated_pdf_engine_generates_a_valid_pdf(): void
    {
        $contents = Pdf::loadHTML('<html><body><h1>Comprovante SGC</h1></body></html>')
            ->setPaper('a4', 'portrait')
            ->output();

        $this->assertStringStartsWith('%PDF-', $contents);
        $this->assertGreaterThan(500, strlen($contents));
    }

    public function test_operational_report_renders_with_shared_theme_and_concise_columns(): void
    {
        $html = view('pdf.deliveries-report-v2', [
            'tenant' => null,
            'title' => 'Relatório de Entregas',
            'generated_at' => '21/07/2026 12:00',
            'filters' => [],
            'deliveries' => collect(),
            'totals' => ['quantity' => 0, 'gross' => 0, 'admin_fee' => 0, 'net' => 0],
        ])->render();

        $this->assertStringContainsString('#374151', $html);
        $this->assertStringContainsString('margin: 16mm 15mm 18mm 15mm', $html);
        $this->assertStringContainsString('background: #eceeef', $html);
        $this->assertStringContainsString('font-size: 9.4px', $html);
        $this->assertStringContainsString('Projeto', $html);
        $this->assertStringNotContainsString('Taxa Admin</th>', $html);

        $contents = Pdf::loadHTML($html)->setPaper('a4', 'landscape')->output();
        $this->assertStringStartsWith('%PDF-', $contents);
    }

    public function test_five_distribution_receipts_remain_on_one_page(): void
    {
        if (! Schema::hasTable('document_templates')) {
            Schema::create('document_templates', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('template_category');
                $table->string('system_template_key')->nullable();
                $table->string('project_type')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('consent_enabled')->default(true);
                $table->string('consent_position')->default('after');
                $table->longText('consent_content_before')->nullable();
                $table->longText('consent_content')->nullable();
                $table->boolean('show_recipient_signature')->default(true);
                $table->boolean('show_representative_signature')->default(true);
            });
        }
        if (! Schema::hasTable('sales_project_types')) {
            Schema::create('sales_project_types', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('name');
                $table->string('slug');
            });
        }

        $tenant = new Tenant([
            'name' => 'Cooperativa de Teste',
            'city' => 'Miravania',
            'state' => 'MG',
            'legal_representative_name' => 'Representante Legal',
        ]);
        $tenant->id = 999999;

        $project = new SalesProject([
            'title' => 'PAA 2026',
            'type' => 'paa',
            'total_value' => 10000,
            'admin_fee_percentage' => 5,
        ]);
        $project->id = 999999;
        $project->tenant_id = $tenant->id;

        $associate = new Associate(['user_id' => null]);
        $associate->id = 999999;
        $associate->tenant_id = $tenant->id;

        $receipt = new AssociateReceipt([
            'receipt_number' => 21,
            'receipt_year' => 2026,
            'issued_at' => '2026-07-27',
        ]);

        $products = collect(range(1, 5))->map(fn (int $index): array => [
            'product_name' => 'Produto '.$index,
            'unit' => 'kg',
            'delivery_date' => now()->subDays($index),
            'total_quantity' => 10,
            'total_gross' => 100,
            'total_admin_fee' => 5,
            'total_net' => 95,
            'fee_totals' => [],
            'distributions' => [[
                'customer_name' => 'Cliente Padrao',
                'quantity' => 10,
                'unit_price' => 10,
                'gross' => 100,
                'admin_fee' => 5,
                'net' => 95,
                'fee_values' => [],
            ]],
        ])->all();
        $summary = [
            'gross_value' => 500,
            'admin_fee' => 25,
            'net_value' => 475,
            'deliveries_count' => 5,
            'total_quantity' => 50,
            'fee_totals' => [],
            'customer_ids' => [1],
        ];
        $data = compact('tenant', 'project', 'associate', 'receipt', 'summary');
        $data += [
            'productsSummary' => $products,
            'feeBreakdown' => ['fees' => [], 'has_detail' => false],
            'feeColumns' => [],
        ];

        foreach (['pdf.project-associate-receipt', 'pdf.associate-portal-receipt'] as $view) {
            $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'portrait');
            $pdf->render();

            $this->assertSame(
                1,
                $pdf->getDomPDF()->get_canvas()->get_page_count(),
                $view.' should fit five simple distributions on one page.',
            );
        }
    }
}

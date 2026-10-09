<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\AssociateReceipt;
use App\Models\Customer;
use App\Models\CustomerBillingReceipt;
use App\Models\Organization;
use App\Models\SalesProject;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
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

    public function test_pdf_blade_templates_do_not_use_rowspan(): void
    {
        $templates = File::allFiles(resource_path('views/pdf'));

        $this->assertNotEmpty($templates);
        foreach ($templates as $template) {
            $this->assertDoesNotMatchRegularExpression(
                '/\browspan\s*=/i',
                File::get($template->getPathname()),
                $template->getRelativePathname().' must keep every PDF row structurally independent.',
            );
        }
    }

    public function test_associate_receipt_allows_groups_to_break_only_between_rows(): void
    {
        $template = File::get(resource_path('views/pdf/project-associate-receipt.blade.php'));

        $this->assertStringNotContainsString('receipt-table-page-break', $template);
        $this->assertStringNotContainsString('receipt-product-group', $template);
        $this->assertStringNotContainsString('AssociateReceiptTablePaginator', $template);
        $this->assertStringContainsString('table.receipt-data-table tbody { page-break-inside: auto; break-inside: auto; }', $template);
        $this->assertStringContainsString('table.receipt-data-table tr { page-break-inside: avoid; break-inside: avoid; }', $template);
        $this->assertStringContainsString('table.receipt-data-table thead { display: table-header-group; }', $template);
    }

    public function test_dynamic_report_colspans_match_the_selected_columns(): void
    {
        $group = [
            'associate_name' => 'Produtor Teste',
            'deliveries_count' => 1,
            'total_quantity' => 10,
            'gross_value' => 100,
            'admin_fee' => 5,
            'net_value' => 95,
            'deliveries' => [[
                'delivery_date' => '02/10/2026',
                'project' => 'Projeto Teste',
                'product' => 'Tomate',
                'customer' => 'Escola Central',
                'quantity' => 10,
                'unit_price' => 10,
                'gross_value' => 100,
                'admin_fee' => 5,
                'net_value' => 95,
                'status_value' => 'approved',
                'status' => 'Aprovada',
            ]],
        ];
        $totals = [
            'associates_count' => 1,
            'deliveries_count' => 1,
            'total_quantity' => 10,
            'total_gross' => 100,
            'total_admin_fee' => 5,
            'total_net' => 95,
        ];

        foreach ([
            ['date', 'product', 'quantity', 'gross_value', 'net_value'],
            ['project', 'quantity', 'status'],
        ] as $columns) {
            $html = view('pdf.deliveries-by-associate', [
                'tenant' => null,
                'title' => 'Entregas por associado',
                'subtitle' => null,
                'generated_at' => '02/10/2026 10:00',
                'filters' => [],
                'groups' => [$group],
                'totals' => $totals,
                'visible_columns' => $columns,
                'visible_sections' => ['deliveries', 'totals'],
            ])->render();

            $this->assertTableStructure($html, 'data-table');
        }

        $emptyHtml = view('pdf.document_ee064db1', [
            'tenant' => null,
            'title' => 'Relatório vazio',
            'generated_at' => '02/10/2026 10:00',
            'columns' => ['delivery_date', 'product', 'quantity'],
            'deliveries' => collect(),
            'totals' => ['gross' => 0, 'admin_fee' => 0, 'net' => 0],
        ])->render();

        $this->assertStringContainsString('colspan="3"', $emptyHtml);

        $genericHtml = view('pdf.generic-export', [
            'title' => 'Exportação genérica',
            'columns' => ['name' => 'Nome', 'document' => 'Documento', 'status' => 'Situação'],
            'data' => [
                ['name' => 'Registro A', 'document' => '123', 'status' => 'Ativo'],
                ['name' => 'Registro B', 'document' => null, 'status' => 'Inativo'],
            ],
            'generatedAt' => '02/10/2026 10:00',
        ])->render();

        $this->assertTableStructure($genericHtml, 'generic-export-table');
        $this->assertStringContainsString('Registro A', $genericHtml);
        $this->assertStringContainsString('Registro B', $genericHtml);
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
        $this->assertStringContainsString('background: #f7f7f7', $html);
        $this->assertStringContainsString('font-size: 9.4px', $html);
        $this->assertStringContainsString('Projeto', $html);
        $this->assertStringNotContainsString('Taxa Admin</th>', $html);

        $contents = Pdf::loadHTML($html)->setPaper('a4', 'landscape')->output();
        $this->assertStringStartsWith('%PDF-', $contents);
    }

    public function test_five_distribution_receipts_remain_on_one_page(): void
    {
        [$tenant, $project, $associate, $receipt, $summary, $products] = $this->associateReceiptFixtures();

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

    public function test_small_and_large_distribution_groups_keep_independent_rows(): void
    {
        foreach ([1, 2, 4, 12] as $distributionCount) {
            [$tenant, $project, $associate, $receipt, $summary, $products] = $this->associateReceiptFixtures();
            $distribution = $products[0]['distributions'][0];
            $products[0]['distributions'] = collect(range(1, $distributionCount))
                ->map(fn (int $index): array => array_merge($distribution, ['customer_name' => 'Destino '.$index]))
                ->all();
            $products[0]['total_quantity'] = $distributionCount * 10;
            $products[0]['total_gross'] = $distributionCount * 100;
            $products = [$products[0]];
            $summary['gross_value'] = $distributionCount * 100;
            $summary['deliveries_count'] = $distributionCount;

            $data = compact('tenant', 'project', 'associate', 'receipt', 'summary') + [
                'productsSummary' => $products,
                'feeBreakdown' => ['fees' => [], 'has_detail' => false],
                'feeColumns' => [],
                'visible_sections' => ['deliveries'],
            ];
            $html = view('pdf.project-associate-receipt', $data)->render();

            $this->assertSame(1, substr_count($html, 'class="tbl receipt-data-table"'));
            $this->assertSame(1, substr_count($html, '<tbody>'));
            $this->assertSame($distributionCount, substr_count($html, '<td>Destino '));
            $this->assertReceiptTableStructure($html);

            $pdf = Pdf::loadView('pdf.project-associate-receipt', $data)->setPaper('a4', 'portrait');
            $this->assertStringStartsWith('%PDF-', $pdf->output());
        }
    }

    public function test_multi_page_receipt_keeps_complete_columns_after_page_break(): void
    {
        [$tenant, $project, $associate, $receipt, $summary, $products] = $this->associateReceiptFixtures();
        $distribution = $products[0]['distributions'][0];
        $products[0]['distributions'] = collect(range(1, 34))->map(fn (int $index): array => array_merge($distribution, [
            'customer_name' => 'Destino '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
        ]))->all();
        $products[0]['total_quantity'] = 340;
        $products[0]['total_gross'] = 3400;
        $products[0]['total_admin_fee'] = 170;
        $products[0]['total_net'] = 3230;
        $products = [$products[0]];
        $summary = array_merge($summary, [
            'gross_value' => 3400,
            'admin_fee' => 170,
            'net_value' => 3230,
            'deliveries_count' => 34,
            'total_quantity' => 340,
        ]);
        $data = compact('tenant', 'project', 'associate', 'receipt', 'summary') + [
            'productsSummary' => $products,
            'feeBreakdown' => ['fees' => [], 'has_detail' => false],
            'feeColumns' => [],
        ];

        $html = view('pdf.project-associate-receipt', $data)->render();
        $this->assertStringNotContainsString('class="receipt-product-group"', $html);
        $this->assertStringNotContainsString('rowspan=', $html);
        $this->assertSame(1, substr_count($html, 'class="tbl receipt-data-table"'));
        $this->assertSame(1, substr_count($html, '<tbody>'));
        $this->assertStringContainsString('', $html);
        $this->assertReceiptTableStructure($html);

        $pdf = Pdf::loadView('pdf.project-associate-receipt', $data)->setPaper('a4', 'portrait');
        $contents = $pdf->output();
        $this->assertSame(2, $pdf->getDomPDF()->get_canvas()->get_page_count());

        if ($output = env('SGC_PDF_QA_OUTPUT')) {
            File::ensureDirectoryExists(dirname($output));
            file_put_contents($output, $contents);
        }
    }

    public function test_group_near_page_end_can_continue_without_moving_the_whole_group(): void
    {
        [$tenant, $project, $associate, $receipt, $summary, $products] = $this->associateReceiptFixtures();
        $baseProduct = $products[0];
        $distribution = $baseProduct['distributions'][0];
        $products = collect(range(1, 30))->map(function (int $index) use ($baseProduct, $distribution): array {
            return array_merge($baseProduct, [
                'product_name' => 'Produto anterior '.$index,
                'distributions' => [array_merge($distribution, ['customer_name' => 'Destino anterior '.$index])],
            ]);
        })->all();
        $focusProduct = array_merge($baseProduct, [
            'product_name' => 'Cenoura da quebra natural',
            'total_quantity' => 40,
            'total_gross' => 400,
            'distributions' => collect(range(1, 4))->map(fn (int $index): array => array_merge($distribution, [
                'customer_name' => 'Destino da cenoura '.$index,
            ]))->all(),
        ]);
        $products[] = $focusProduct;
        $products[] = array_merge($baseProduct, [
            'product_name' => 'Produto posterior',
            'distributions' => [array_merge($distribution, ['customer_name' => 'Destino posterior'])],
        ]);
        $summary['gross_value'] = 3500;
        $summary['deliveries_count'] = 35;

        $data = compact('tenant', 'project', 'associate', 'receipt', 'summary') + [
            'productsSummary' => $products,
            'feeBreakdown' => ['fees' => [], 'has_detail' => false],
            'feeColumns' => [],
            'visible_sections' => ['associate_info', 'project_info', 'deliveries'],
        ];
        $html = view('pdf.project-associate-receipt', $data)->render();

        $this->assertSame(1, substr_count($html, 'class="tbl receipt-data-table"'));
        $this->assertSame(1, substr_count($html, '<tbody>'));
        $this->assertStringContainsString('Cenoura da quebra natural', $html);
        $this->assertStringContainsString('Total (4 dist.)', $html);
        $this->assertReceiptTableStructure($html);

        $pdf = Pdf::loadView('pdf.project-associate-receipt', $data)->setPaper('a4', 'portrait');
        $contents = $pdf->output();

        $this->assertSame(2, $pdf->getDomPDF()->get_canvas()->get_page_count());

        if ($output = env('SGC_PDF_QA_BOUNDARY_OUTPUT')) {
            File::ensureDirectoryExists(dirname($output));
            file_put_contents($output, $contents);
        }
    }

    public function test_compact_large_group_renders_three_pages_without_forced_breaks(): void
    {
        [$tenant, $project, $associate, $receipt, $summary, $products] = $this->associateReceiptFixtures();
        $distribution = $products[0]['distributions'][0];
        $products[0]['distributions'] = collect(range(1, 70))->map(fn (int $index): array => array_merge($distribution, [
            'customer_name' => 'Destino '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
        ]))->all();
        $products[0]['total_quantity'] = 700;
        $products[0]['total_gross'] = 7000;
        $products = [$products[0]];
        $summary['gross_value'] = 7000;
        $summary['deliveries_count'] = 70;

        $data = compact('tenant', 'project', 'associate', 'receipt', 'summary') + [
            'productsSummary' => $products,
            'feeBreakdown' => ['fees' => [], 'has_detail' => false],
            'feeColumns' => [],
            'visible_sections' => ['associate_info', 'project_info', 'deliveries'],
        ];

        $pdf = Pdf::loadView('pdf.project-associate-receipt', $data)->setPaper('a4', 'portrait');
        $contents = $pdf->output();

        $this->assertSame(3, $pdf->getDomPDF()->get_canvas()->get_page_count());
        $this->assertStringStartsWith('%PDF-', $contents);

        if ($output = env('SGC_PDF_QA_THREE_PAGE_OUTPUT')) {
            File::ensureDirectoryExists(dirname($output));
            file_put_contents($output, $contents);
        }
    }

    public function test_large_group_renders_five_pages_with_long_labels_and_stable_columns(): void
    {
        [$tenant, $project, $associate, $receipt, $summary, $products] = $this->associateReceiptFixtures();
        $distribution = $products[0]['distributions'][0];
        $products[0]['product_name'] = str_repeat('Produto agroecológico de nome extenso ', 3);
        $products[0]['distributions'] = collect(range(1, 45))->map(fn (int $index): array => array_merge($distribution, [
            'customer_name' => 'Unidade recebedora com nome institucional muito extenso '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'quantity' => 1234.5678,
            'unit_price' => 98765.43,
            'gross' => 121932622.322154,
        ]))->all();
        $products[0]['total_quantity'] = 55555.551;
        $products[0]['total_gross'] = 5486968004.49693;
        $products = [$products[0]];
        $summary['gross_value'] = $products[0]['total_gross'];
        $summary['deliveries_count'] = 45;
        $summary['total_quantity'] = $products[0]['total_quantity'];

        $data = compact('tenant', 'project', 'associate', 'receipt', 'summary') + [
            'productsSummary' => $products,
            'feeBreakdown' => ['fees' => [], 'has_detail' => false],
            'feeColumns' => [],
            'visible_sections' => ['associate_info', 'project_info', 'deliveries'],
        ];
        $html = view('pdf.project-associate-receipt', $data)->render();

        $this->assertStringNotContainsString('rowspan=', $html);
        $this->assertSame(1, substr_count($html, 'class="tbl receipt-data-table"'));
        $this->assertSame(88, substr_count($html, '></span>'));
        $this->assertReceiptTableStructure($html);

        $pdf = Pdf::loadView('pdf.project-associate-receipt', $data)->setPaper('a4', 'portrait');
        $contents = $pdf->output();

        $this->assertSame(5, $pdf->getDomPDF()->get_canvas()->get_page_count());
        $this->assertStringStartsWith('%PDF-', $contents);

        if ($output = env('SGC_PDF_QA_LARGE_OUTPUT')) {
            File::ensureDirectoryExists(dirname($output));
            file_put_contents($output, $contents);
        }
    }

    public function test_associate_receipt_delivery_date_column_can_be_hidden(): void
    {
        [$tenant, $project, $associate, $receipt, $summary, $products] = $this->associateReceiptFixtures();

        $html = view('pdf.project-associate-receipt', [
            'tenant' => $tenant,
            'project' => $project,
            'associate' => $associate,
            'receipt' => $receipt,
            'summary' => $summary,
            'productsSummary' => $products,
            'feeBreakdown' => ['fees' => [], 'has_detail' => false],
            'feeColumns' => [],
            'visible_columns' => ['unit_price', 'gross'],
        ])->render();

        $this->assertStringNotContainsString('<th style="width:11%;">Data</th>', $html);
    }

    public function test_associate_receipt_date_option_uses_grouped_rows_and_optional_product_totals(): void
    {
        [$tenant, $project, $associate, $receipt, $summary, $products] = $this->associateReceiptFixtures();
        $grouped = [$products[0]];
        $grouped[0]['product_name'] = 'Produto agrupado no dia';
        $grouped[0]['total_quantity'] = 20;
        $grouped[0]['total_gross'] = 200;
        $grouped[0]['total_admin_fee'] = 10;
        $grouped[0]['total_net'] = 190;
        $grouped[0]['distributions'][0]['quantity'] = 20;
        $grouped[0]['distributions'][0]['gross'] = 200;
        $grouped[0]['distributions'][0]['admin_fee'] = 10;
        $grouped[0]['distributions'][0]['net'] = 190;
        $totals = [[
            'product_name' => 'Produto agrupado no período',
            'unit' => 'kg',
            'total_quantity' => 50,
            'total_gross' => 500,
            'total_admin_fee' => 25,
            'total_net' => 475,
            'fee_totals' => [],
        ]];

        $html = view('pdf.project-associate-receipt', [
            'tenant' => $tenant,
            'project' => $project,
            'associate' => $associate,
            'receipt' => $receipt,
            'summary' => $summary,
            'productsSummary' => $products,
            'productsByDate' => $grouped,
            'productTotals' => $totals,
            'feeBreakdown' => ['fees' => [], 'has_detail' => false],
            'feeColumns' => [],
            'visible_columns' => ['delivery_date', 'gross', 'net'],
            'visible_sections' => ['deliveries', 'product_totals'],
        ])->render();

        $this->assertStringContainsString('Produto agrupado no dia', $html);
        $this->assertStringNotContainsString('Produto 2', $html);
        $this->assertStringContainsString('Totais gerais por produto no período', $html);
        $this->assertStringContainsString('Produto agrupado no período', $html);
    }

    public function test_associate_portal_receipt_respects_grouping_and_product_total_sections(): void
    {
        [$tenant, $project, $associate, $receipt, $summary, $products] = $this->associateReceiptFixtures();
        $grouped = [$products[0]];
        $grouped[0]['product_name'] = 'Produto agrupado no portal';
        $totals = [[
            'product_name' => 'Produto total no portal',
            'unit' => 'kg',
            'total_quantity' => 50,
            'total_gross' => 500,
            'total_admin_fee' => 25,
            'total_net' => 475,
            'fee_totals' => [],
        ]];

        $html = view('pdf.associate-portal-receipt', [
            'tenant' => $tenant,
            'project' => $project,
            'associate' => $associate,
            'receipt' => $receipt,
            'summary' => $summary,
            'productsSummary' => $products,
            'productsByDate' => $grouped,
            'productTotals' => $totals,
            'visible_columns' => ['date', 'product', 'quantity', 'gross_value'],
            'visible_sections' => ['distributions', 'product_totals'],
        ])->render();

        $this->assertStringContainsString('Produto agrupado no portal', $html);
        $this->assertStringNotContainsString('Produto 2', $html);
        $this->assertStringContainsString('Totais gerais por produto no período', $html);
        $this->assertStringContainsString('Produto total no portal', $html);
        $this->assertStringNotContainsString('class="portal-summary"', $html);
    }

    public function test_two_copy_receipt_reuses_the_same_layout_on_two_pages(): void
    {
        [$tenant, $project, $associate, $receipt, $summary, $products] = $this->associateReceiptFixtures();
        $data = [
            'tenant' => $tenant,
            'project' => $project,
            'associate' => $associate,
            'receipt' => $receipt,
            'summary' => $summary,
            'productsSummary' => $products,
            'feeBreakdown' => ['fees' => [], 'has_detail' => false],
            'feeColumns' => [],
            'copyLabels' => ['1ª VIA — MEMBRO', '2ª VIA — ORGANIZAÇÃO'],
        ];

        $html = view('pdf.project-associate-receipt', $data)->render();
        $this->assertStringContainsString('1ª VIA — MEMBRO', $html);
        $this->assertStringContainsString('2ª VIA — ORGANIZAÇÃO', $html);

        $pdf = Pdf::loadView('pdf.project-associate-receipt', $data)->setPaper('a4', 'portrait');
        $pdf->render();

        $this->assertSame(2, $pdf->getDomPDF()->get_canvas()->get_page_count());
    }

    public function test_organization_receipt_respects_sections_and_places_total_quantity_before_price(): void
    {
        $tenant = new Tenant(['name' => 'Cooperativa Teste']);
        $project = new SalesProject(['title' => 'PAA 2026']);
        $organization = new Organization(['name' => 'Prefeitura Municipal']);
        $customer = new Customer(['name' => 'Escola Central']);
        $customer->id = 10;
        $receipt = new CustomerBillingReceipt([
            'receipt_year' => 2026,
            'receipt_number' => 12,
            'issued_at' => '2026-08-05',
        ]);
        $priceGroups = [[
            'price_table_name' => 'Tabela 2026',
            'customers' => collect([$customer]),
            'table' => [[
                'product' => 'Banana',
                'unit' => 'kg',
                'unit_price' => 5.5,
                'by_customer' => [10 => 20],
                'total_qty' => 20,
                'total_gross' => 110,
                'fee_values' => [],
            ]],
            'subtotal_gross' => 110,
            'subtotal_net' => 110,
            'fee_totals' => [],
        ]];

        $html = view('pdf.customer-organization-receipt', [
            'tenant' => $tenant,
            'project' => $project,
            'organization' => $organization,
            'receipt' => $receipt,
            'customers' => collect([$customer]),
            'priceGroups' => $priceGroups,
            'multiplePriceTables' => false,
            'totalGross' => 110,
            'totalFees' => 0,
            'totalNet' => 110,
            'periodLabel' => '05/08/2026',
            'feeColumns' => [],
            'visibleColumns' => ['unit_price', 'gross'],
            'visible_sections' => ['document_info', 'organization_info', 'project_info', 'deliveries'],
        ])->render();

        $this->assertStringContainsString('Nº Documento:', $html);
        $this->assertStringNotContainsString('<div class="sec-label">Resumo financeiro</div>', $html);
        $this->assertStringNotContainsString('<div class="fin-summary">', $html);
        $this->assertStringNotContainsString('Valor a Receber', $html);
        $this->assertLessThan(strpos($html, 'Vlr. Unit.'), strpos($html, 'Qtd. Total'));
        $this->assertSame(1, substr_count($html, 'Total Geral'));
    }

    public function test_customer_receipt_hides_disabled_financial_and_signature_sections(): void
    {
        $tenant = new Tenant(['name' => 'Cooperativa Teste']);
        $project = new SalesProject(['title' => 'PNAE 2026']);
        $customer = new Customer(['name' => 'Escola Central']);
        $receipt = new CustomerBillingReceipt([
            'receipt_year' => 2026,
            'receipt_number' => 3,
            'issued_at' => '2026-08-05',
        ]);

        $html = view('pdf.customer-billing-receipt', [
            'tenant' => $tenant,
            'project' => $project,
            'customer' => $customer,
            'receipt' => $receipt,
            'productRows' => [[
                'product' => 'Banana',
                'unit' => 'kg',
                'quantity' => 10,
                'unit_price' => 5,
                'gross' => 50,
                'net' => 50,
                'fee_values' => ['fee:customer:7' => 2.5],
            ]],
            'totalGross' => 50,
            'totalFees' => 0,
            'totalNet' => 50,
            'feeBreakdown' => [],
            'feeColumns' => [[
                'key' => 'fee:customer:7',
                'name' => 'Gestão',
                'nature' => 'discount',
            ]],
            'visibleColumns' => ['unit_price', 'gross', 'fee:customer:7'],
            'visible_sections' => ['document_info', 'customer_info', 'project_info', 'deliveries'],
        ])->render();

        $this->assertStringContainsString('Nº Documento:', $html);
        $this->assertStringContainsString('Entregas por Produto', $html);
        $this->assertStringNotContainsString('<div class="sec-label">Resumo financeiro</div>', $html);
        $this->assertStringNotContainsString('<div class="fin-summary">', $html);
        $this->assertStringNotContainsString('Valor Líquido</div>', $html);
        $this->assertStringContainsString('Gestão', $html);
        $this->assertStringContainsString('- R$ 2,50', $html);
    }

    public function test_customer_receipt_places_reference_notes_after_the_table(): void
    {
        $receipt = new CustomerBillingReceipt([
            'receipt_year' => 2026,
            'receipt_number' => 3,
            'issued_at' => '2026-08-05',
            'report_annotations_position' => 'after',
            'report_annotations' => [
                ['target' => 'product:7', 'text' => 'Produto substituído.'],
                ['target' => 'distribution:19', 'text' => 'Conferir esta distribuição.'],
                ['target' => 'global', 'text' => 'Observação geral.'],
            ],
        ]);

        $html = view('pdf.customer-billing-receipt', [
            'tenant' => new Tenant(['name' => 'Cooperativa Teste']),
            'project' => new SalesProject(['title' => 'PNAE 2026']),
            'customer' => new Customer(['name' => 'Escola Central']),
            'receipt' => $receipt,
            'productRows' => [[
                'product_id' => 7,
                'distribution_ids' => [19],
                'product' => 'Milho', 'unit' => 'kg', 'quantity' => 2,
                'unit_price' => 5, 'gross' => 10, 'net' => 10,
            ]],
            'totalGross' => 10, 'totalFees' => 0, 'totalNet' => 10,
            'feeBreakdown' => [], 'feeColumns' => [],
            'visibleColumns' => ['unit_price', 'gross'],
            'visible_sections' => ['deliveries'],
        ])->render();

        self::assertStringContainsString('Milho<sup>¹²</sup>', $html);
        self::assertStringContainsString('Produto substituído.', $html);
        self::assertStringContainsString('Observação geral.', $html);
        self::assertGreaterThan(strpos($html, 'Milho<sup>¹²</sup>'), strpos($html, 'Observações'));

        $receipt->report_annotations_position = 'before';
        $beforeHtml = view('pdf.customer-billing-receipt', [
            'tenant' => new Tenant(['name' => 'Cooperativa Teste']),
            'project' => new SalesProject(['title' => 'PNAE 2026']),
            'customer' => new Customer(['name' => 'Escola Central']),
            'receipt' => $receipt,
            'productRows' => [[
                'product_id' => 7, 'distribution_ids' => [19], 'product' => 'Milho',
                'unit' => 'kg', 'quantity' => 2, 'unit_price' => 5, 'gross' => 10, 'net' => 10,
            ]],
            'totalGross' => 10, 'totalFees' => 0, 'totalNet' => 10,
            'feeBreakdown' => [], 'feeColumns' => [],
            'visibleColumns' => ['unit_price', 'gross'], 'visible_sections' => ['deliveries'],
        ])->render();

        self::assertLessThan(strpos($beforeHtml, 'Milho<sup>¹²</sup>'), strpos($beforeHtml, 'Observações'));
    }

    public function test_customer_receipt_renders_optional_product_totals_section(): void
    {
        $html = view('pdf.customer-billing-receipt', [
            'tenant' => new Tenant(['name' => 'Cooperativa Teste']),
            'project' => new SalesProject(['title' => 'PNAE 2026']),
            'customer' => new Customer(['name' => 'Escola Central']),
            'receipt' => new CustomerBillingReceipt([
                'receipt_year' => 2026,
                'receipt_number' => 3,
                'issued_at' => '2026-08-05',
            ]),
            'productRows' => [],
            'productTotals' => [[
                'product' => 'Banana',
                'unit' => 'kg',
                'quantity' => '25',
                'gross' => '125.00',
                'adjustments' => '-5.00',
                'net' => '120.00',
            ]],
            'totalGross' => 125,
            'totalFees' => 5,
            'totalNet' => 120,
            'feeBreakdown' => [],
            'feeColumns' => [],
            'visibleColumns' => ['gross'],
            'visible_sections' => ['product_totals'],
        ])->render();

        $this->assertStringContainsString('Totais gerais por produto no período', $html);
        $this->assertStringContainsString('25&nbsp;kg', $html);
        $this->assertStringContainsString('R$ 125,00', $html);
        $this->assertStringContainsString('- R$ 5,00', $html);
        $this->assertStringNotContainsString('Entregas por Produto', $html);
    }

    public function test_associate_receipt_hides_disabled_financial_section(): void
    {
        [$tenant, $project, $associate, $receipt, $summary, $products] = $this->associateReceiptFixtures();

        $html = view('pdf.project-associate-receipt', [
            'tenant' => $tenant,
            'project' => $project,
            'associate' => $associate,
            'receipt' => $receipt,
            'summary' => $summary,
            'productsSummary' => $products,
            'feeBreakdown' => ['fees' => [], 'has_detail' => false],
            'feeColumns' => [],
            'visible_sections' => ['associate_info', 'project_info', 'deliveries'],
        ])->render();

        $this->assertStringContainsString('TOTAL GERAL', $html);
        $this->assertStringNotContainsString('<div class="fin-summary">', $html);
        $this->assertStringNotContainsString('Valor Líquido a Receber', $html);
    }

    public function test_associate_receipt_renders_selected_project_fee_column(): void
    {
        [$tenant, $project, $associate, $receipt, $summary, $products] = $this->associateReceiptFixtures();
        foreach ($products as &$product) {
            $product['fee_totals']['fee:associate:7'] = 3.5;
            foreach ($product['distributions'] as &$distribution) {
                $distribution['fee_values']['fee:associate:7'] = 3.5;
            }
        }

        $html = view('pdf.project-associate-receipt', [
            'tenant' => $tenant,
            'project' => $project,
            'associate' => $associate,
            'receipt' => $receipt,
            'summary' => $summary,
            'productsSummary' => $products,
            'feeBreakdown' => ['fees' => [], 'has_detail' => false],
            'feeColumns' => [[
                'key' => 'fee:associate:7',
                'name' => 'Frete',
                'nature' => 'discount',
            ]],
            'visible_columns' => ['gross', 'fee:associate:7'],
        ])->render();

        $this->assertStringContainsString('<th class="r fee-col">Frete</th>', $html);
        $this->assertStringContainsString('-&nbsp;R$&nbsp;3,50', $html);
    }

    public function test_multi_project_customer_receipt_renders_one_table_per_project(): void
    {
        $project = function (int $id, string $title): SalesProject {
            $model = new class extends SalesProject
            {
                public function getTypeLabelAttribute(): string
                {
                    return 'PNAE';
                }
            };
            $model->id = $id;
            $model->tenant_id = 1;
            $model->title = $title;
            $model->type = 'pnae';

            return $model;
        };
        $january = $project(10, 'PNAE Janeiro');
        $february = $project(20, 'PNAE Fevereiro');
        $receipt = new CustomerBillingReceipt([
            'receipt_year' => 2026,
            'receipt_number' => 17,
            'issued_at' => '2026-03-01',
            'document_number' => 'NF-que-nao-deve-aparecer-no-resumo',
            'notes' => 'Cobrança consolidada dos períodos de janeiro e fevereiro.',
        ]);
        $receipt->setRelation('project', $january);
        $rows = [
            ['project_id' => 10, 'project' => $january->title, 'product' => 'Alface', 'unit' => 'kg', 'quantity' => 10,
                'unit_price' => 5, 'gross' => 50, 'net' => 45, 'fee_values' => ['fee:customer:1' => 5]],
            ['project_id' => 20, 'project' => $february->title, 'product' => 'Cenoura', 'unit' => 'kg', 'quantity' => 10,
                'unit_price' => 6, 'gross' => 60, 'net' => 48, 'fee_values' => ['fee:customer:2' => 12]],
        ];
        $projectGroups = [
            ['project' => $january, 'period' => '01/01/2026 a 31/01/2026', 'rows' => [$rows[0]],
                'fee_columns' => [['key' => 'fee:customer:1', 'name' => 'Taxa Jan.', 'nature' => 'discount']],
                'fee_totals' => ['fee:customer:1' => 5], 'subtotal_gross' => 50, 'subtotal_net' => 45],
            ['project' => $february, 'period' => '01/02/2026 a 28/02/2026', 'rows' => [$rows[1]],
                'fee_columns' => [['key' => 'fee:customer:2', 'name' => 'Taxa Fev.', 'nature' => 'discount']],
                'fee_totals' => ['fee:customer:2' => 12], 'subtotal_gross' => 60, 'subtotal_net' => 48],
        ];

        $html = view('pdf.customer-billing-receipt', [
            'tenant' => new Tenant(['name' => 'Cooperativa Teste']),
            'project' => $january,
            'projects' => collect([$january, $february]),
            'projectPeriods' => [
                ['project' => $january, 'period' => '01/01/2026 a 31/01/2026'],
                ['project' => $february, 'period' => '01/02/2026 a 28/02/2026'],
            ],
            'customer' => new Customer(['name' => 'Escola Central']),
            'receipt' => $receipt,
            'productRows' => $rows,
            'projectGroups' => $projectGroups,
            'isMultiProject' => true,
            'totalGross' => 110,
            'totalFees' => 17,
            'totalNet' => 93,
            'feeBreakdown' => [],
            'feeColumns' => array_merge($projectGroups[0]['fee_columns'], $projectGroups[1]['fee_columns']),
            'visibleColumns' => ['unit_price', 'gross', 'net', 'fee:customer:1', 'fee:customer:2'],
            'visible_sections' => ['document_info', 'customer_info', 'project_info', 'deliveries', 'financial'],
            'periodLabel' => '01/01/2026 a 28/02/2026',
        ])->render();

        $this->assertSame(2, substr_count($html, '<table class="tbl receipt-data-table">'));
        $this->assertStringContainsString('PNAE Janeiro', $html);
        $this->assertStringContainsString('PNAE Fevereiro', $html);
        $this->assertStringContainsString('Taxa Jan.', $html);
        $this->assertStringContainsString('Taxa Fev.', $html);
        $this->assertStringNotContainsString('<th>Projeto</th>', $html);
        $this->assertStringContainsString('Observações', $html);
        $this->assertStringContainsString('Cobrança consolidada dos períodos de janeiro e fevereiro.', $html);
        $this->assertStringNotContainsString('NF-que-nao-deve-aparecer-no-resumo', $html);
    }

    private function associateReceiptFixtures(): array
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

        return [$tenant, $project, $associate, $receipt, $summary, $products];
    }

    private function assertReceiptTableStructure(string $html): void
    {
        $this->assertTableStructure($html, 'receipt-data-table');
    }

    private function assertTableStructure(string $html, string $tableClass): void
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        $tables = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " '.$tableClass.' ")]');

        $this->assertGreaterThan(0, $tables->length);

        foreach ($tables as $table) {
            $header = $xpath->query('./thead/tr[1]', $table)->item(0);
            $expectedColumns = $this->structuralColumnCount($xpath, $header);
            $this->assertGreaterThan(0, $expectedColumns);

            foreach ($xpath->query('./tbody/tr | ./tfoot/tr', $table) as $row) {
                $this->assertSame($expectedColumns, $this->structuralColumnCount($xpath, $row));
                $this->assertSame(0, $xpath->query('./td[@rowspan] | ./th[@rowspan]', $row)->length);
            }
        }
    }

    private function structuralColumnCount(\DOMXPath $xpath, ?\DOMNode $row): int
    {
        if (! $row) {
            return 0;
        }

        $count = 0;
        foreach ($xpath->query('./td | ./th', $row) as $cell) {
            $count += max(1, (int) ($cell->attributes?->getNamedItem('colspan')?->nodeValue ?? 1));
        }

        return $count;
    }
}

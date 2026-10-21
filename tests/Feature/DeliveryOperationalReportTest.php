<?php

namespace Tests\Feature;

use App\Exports\DeliveryOperationalReportExport;
use App\Models\SalesProject;
use App\Services\DeliveryReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryOperationalReportTest extends TestCase
{
    use \Tests\Concerns\BuildsDeliveryReportSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildDeliveryReportSchema();
    }

    public function test_report_keeps_reception_physical_and_uses_distributions_for_money(): void
    {
        session(['tenant_id' => 1]);
        $project = SalesProject::query()->with('tenant')->findOrFail(10);
        $report = app(DeliveryReportService::class)->build($project, ['type' => 'associate']);

        $this->assertSame(1, $report['totals']['receptions_count']);
        $this->assertSame(2, $report['totals']['distributions_count']);
        $this->assertSame(100.0, $report['totals']['received_quantity']);
        $this->assertSame(60.0, $report['totals']['distributed_quantity']);
        $this->assertSame(320.0, $report['totals']['gross']);
        $this->assertSame(305.0, $report['totals']['net']);
        $this->assertSame('Maria Local', $report['rows']->first()['associate']);
        $this->assertStringNotContainsString('Outro Tenant', json_encode($report));
    }

    public function test_customer_filter_does_not_corrupt_real_remaining_quantity(): void
    {
        session(['tenant_id' => 1]);
        $project = SalesProject::query()->with('tenant')->findOrFail(10);
        $report = app(DeliveryReportService::class)->build($project, [
            'type' => 'customer',
            'customer_ids' => [80],
        ]);

        $row = $report['rows']->first();
        $this->assertSame(40.0, $row['distributed_quantity']);
        $this->assertSame(40.0, $row['remaining_quantity']);
        $this->assertSame(200.0, $report['totals']['gross']);
        $this->assertSame('Escola A', $report['groups']->first()['title']);
    }

    public function test_filter_options_are_tenant_scoped_and_use_configured_member_term(): void
    {
        session(['tenant_id' => 1]);
        $project = SalesProject::query()->with('tenant')->findOrFail(10);
        $options = app(DeliveryReportService::class)->options($project);

        $this->assertSame('Cooperado', $options['member_term']);
        $this->assertSame('Cooperados', $options['member_term_plural']);
        $this->assertSame(['Maria Local'], $options['members']->pluck('name')->all());
        $this->assertSame(['Banana'], $options['products']->pluck('name')->all());
        $this->assertSame(['Escola A', 'Escola B'], $options['customers']->pluck('name')->all());
        $this->assertStringNotContainsString('Produto externo', json_encode($options));
    }

    public function test_excel_keeps_one_delivery_row_with_destinations_and_pdf_renders(): void
    {
        session(['tenant_id' => 1]);
        $project = SalesProject::query()->with('tenant')->findOrFail(10);
        $report = app(DeliveryReportService::class)->build($project, ['type' => 'associate']);
        $sheet = (new DeliveryOperationalReportExport($report))->array();

        $this->assertSame("Escola A: 40,000 kg\nEscola B: 20,000 kg", $sheet[6][2]);
        $this->assertSame('=SUM(E7:E7)', $sheet[7][4]);
        $this->assertSame('=SUM(F7:F7)', $sheet[7][5]);
        $this->assertSame('=E8', $sheet[8][4]);

        $contents = Pdf::loadView('pdf.delivery-operational-report', $report + [
            'tenant' => $project->tenant,
            'title' => 'Entregas por Cooperado',
            'generated_at' => '15/08/2026 10:00',
        ])->setPaper('a4', 'landscape')->output();

        $this->assertStringStartsWith('%PDF-', $contents);
    }

    public function test_customer_report_aggregates_by_date_and_product_by_default(): void
    {
        session(['tenant_id' => 1]);
        $project = SalesProject::query()->with('tenant')->findOrFail(10);
        $report = app(DeliveryReportService::class)->build($project, ['type' => 'customer']);

        $this->assertSame('product', $report['preferences']['grouping']);
        $this->assertSame(2, $report['groups']->count());
        $this->assertSame(40.0, $report['groups']->first()['sections']->first()['totals']['distributed_quantity']);
        $this->assertSame('01/08/2026', $report['groups']->first()['sections']->first()['title']);
    }

    public function test_report_preferences_are_persisted_per_type(): void
    {
        $project = SalesProject::query()->findOrFail(10);
        $service = app(DeliveryReportService::class);
        $saved = $service->updatePreferences($project, 'customer', [
            'columns' => ['date', 'associate', 'product', 'net_value'],
            'grouping' => 'associate',
            'orientation' => 'landscape',
            'table_scale' => 85,
        ]);

        $this->assertSame('associate', $saved['grouping']);
        $this->assertSame('landscape', $saved['orientation']);
        $this->assertSame($saved, $service->preferences($project->fresh(), 'customer'));
        $this->assertSame('delivery', $service->preferences($project->fresh(), 'associate')['grouping']);
    }

    public function test_excel_preserves_formulas_when_first_visible_column_is_numeric(): void
    {
        session(['tenant_id' => 1]);
        $project = SalesProject::query()->with('tenant')->findOrFail(10);
        app(DeliveryReportService::class)->updatePreferences($project, 'associate', [
            'columns' => ['received_quantity', 'distributed_quantity', 'gross_value', 'admin_fee', 'net_value'],
            'grouping' => 'delivery', 'orientation' => 'portrait', 'table_scale' => 90,
        ]);

        $report = app(DeliveryReportService::class)->build($project->fresh()->load('tenant'), ['type' => 'associate']);
        $sheet = (new DeliveryOperationalReportExport($report))->array();

        $this->assertSame('=SUM(A7:A7)', $sheet[7][0]);
        $this->assertSame('=SUM(B7:B7)', $sheet[7][1]);
        $this->assertSame('=SUM(C7:C7)', $sheet[7][2]);
        $this->assertSame('=SUM(D7:D7)', $sheet[7][3]);
        $this->assertSame('=SUM(E7:E7)', $sheet[7][4]);
        $this->assertSame('=A8', $sheet[8][0]);
        $this->assertSame('=E8', $sheet[8][4]);
    }
}

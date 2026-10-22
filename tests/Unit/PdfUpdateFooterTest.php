<?php

namespace Tests\Unit;

use App\Services\PdfUpdateFooterService;
use Barryvdh\DomPDF\PDF;
use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class PdfUpdateFooterTest extends TestCase
{
    public function test_system_layout_fallbacks_can_omit_generation_dates_without_losing_the_tenant_identity(): void
    {
        $service = app(\App\Services\TemplatedPdfService::class);
        $method = new \ReflectionMethod($service, 'resolveLayoutHtml');
        $tenant = new \App\Models\Tenant(['name' => 'Associação Teste', 'cnpj' => '00.000.000/0001-00']);
        foreach (['header', 'footer'] as $type) {
            $html = $method->invoke($service, null, 'Relatório', '#374151', '#64786f', $type, [], $tenant, true);
            self::assertStringNotContainsString('Gerado em', $html);
            self::assertStringContainsString('Associação Teste', $html);
            self::assertStringContainsString('00.000.000/0001-00', $html);
            $legacy = $method->invoke($service, null, 'Relatório', '#374151', '#64786f', $type, [], $tenant);
            self::assertStringContainsString('Gerado em', $legacy);
        }
    }

    public function test_timestamp_uses_brasilia_time_and_is_added_to_every_page_without_rerendering_custom_layouts(): void
    {
        foreach ([false, true] as $alreadyRendered) {
            $canvas = Mockery::mock(Canvas::class);
            $canvas->shouldReceive('get_height')->once()->andReturn(842);
            $canvas->shouldReceive('page_text')->once()->with(43, 818,
                'Gerado em 21/10/2026 10:20:30 (Brasília)', 'font-file', 7, [0.40, 0.44, 0.46]);
            $fonts = Mockery::mock(FontMetrics::class);
            $fonts->shouldReceive('getFont')->with('DejaVu Sans', 'normal')->once()->andReturn('font-file');
            $dompdf = Mockery::mock(Dompdf::class);
            $dompdf->shouldReceive('getCanvas')->once()->andReturn($canvas);
            $dompdf->shouldReceive('getFontMetrics')->once()->andReturn($fonts);
            $pdf = Mockery::mock(PDF::class);
            $pdf->shouldReceive('getDomPDF')->once()->andReturn($dompdf);
            if ($alreadyRendered) $pdf->shouldNotReceive('render');
            else $pdf->shouldReceive('render')->once();
            $timestamp = Carbon::parse('2026-10-21 13:20:30', 'UTC');
            self::assertSame($pdf, app(PdfUpdateFooterService::class)->apply($pdf, $timestamp, $alreadyRendered));
            self::assertSame('UTC', $timestamp->timezoneName);
        }
    }
}

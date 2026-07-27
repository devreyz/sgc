<?php

namespace Tests\Unit;

use App\Models\SalesProject;
use App\Services\ReceiptFeeColumnService;
use PHPUnit\Framework\TestCase;

class ReceiptFeeColumnServiceTest extends TestCase
{
    public function test_snapshot_definitions_calculate_percentage_and_fixed_columns(): void
    {
        $project = new SalesProject();
        $service = new ReceiptFeeColumnService();
        $definitions = $service->definitions($project, 'customer', [
            'fees' => [
                [
                    'id' => 7,
                    'name' => 'Taxa de gestão',
                    'column_name' => 'Gestão',
                    'type' => 'percentage',
                    'nature' => 'discount',
                    'rate' => '5',
                    'label' => '5,00%',
                ],
                [
                    'id' => 8,
                    'name' => 'Frete',
                    'type' => 'fixed',
                    'nature' => 'accrual',
                    'rate' => '12.50',
                    'label' => 'R$ 12,50',
                ],
            ],
        ]);

        $values = $service->values(200, $definitions);

        $this->assertSame(10.0, $values['fee:customer:7']);
        $this->assertSame(12.5, $values['fee:customer:8']);
        $this->assertSame('Gestão', $definitions[0]['name']);
        $this->assertSame('Taxa de gestão', $definitions[0]['full_name']);
        $this->assertStringContainsString(
            'Taxa de gestão',
            $service->options($definitions)['fee:customer:7'],
        );
        $this->assertStringContainsString(
            'coluna: Gestão',
            $service->options($definitions)['fee:customer:7'],
        );
    }

    public function test_sanitize_rejects_unknown_or_forged_columns(): void
    {
        $project = new SalesProject();
        $service = new ReceiptFeeColumnService();
        $definitions = $service->definitions($project, 'associate', [
            'fees' => [[
                'id' => 2,
                'name' => 'Taxa',
                'type' => 'percentage',
                'nature' => 'discount',
                'rate' => '2',
            ]],
        ]);

        $columns = $service->sanitize(
            ['gross', 'fee:associate:2', 'fee:associate:999', ['invalid']],
            $definitions,
            ['unit_price', 'gross'],
        );

        $this->assertSame(['gross', 'fee:associate:2'], $columns);
    }
}

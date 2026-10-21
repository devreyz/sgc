<?php

namespace Tests\Unit;

use Tests\TestCase;

class ReceiptQuantityTotalTest extends TestCase
{
    public function test_period_summary_has_padded_cells_and_preserves_separate_unit_totals(): void
    {
        $html = view('pdf.partials.receipt-quantity-summary', ['quantityRows' => [
            ['unit' => 'kg', 'quantity' => '683.61'],
            ['unit' => 'un', 'quantity' => '12'],
        ]])->render();
        self::assertStringContainsString('Quantidade total entregue no período', $html);
        self::assertSame(2, substr_count($html, 'padding:10px 12px'));
        self::assertStringContainsString('page-break-inside:avoid', $html);
        self::assertStringContainsString('683,61&nbsp;kg', $html);
        self::assertStringContainsString('12&nbsp;un', $html);
        foreach (['customer-billing-receipt', 'customer-organization-receipt'] as $template) {
            self::assertStringContainsString("@include('pdf.partials.receipt-quantity-summary'", file_get_contents(resource_path("views/pdf/{$template}.blade.php")));
        }
    }

    public function test_quantities_are_added_per_unit_without_mixing_units(): void
    {
        $html = view('pdf.partials.receipt-quantity-total', ['quantityRows' => [
            ['unit' => 'kg', 'quantity' => '678.6100'],
            ['unit' => 'kg', 'quantity' => '5.0000'],
            ['unit' => 'un', 'quantity' => '12.0000'],
        ]])->render();
        self::assertStringContainsString('683,61&nbsp;kg', $html);
        self::assertStringContainsString('12&nbsp;un', $html);
        self::assertStringNotContainsString('695,61', $html);
    }

    public function test_organization_totals_use_each_customers_delivered_quantity(): void
    {
        $rows = [
            ['unit' => 'kg', 'total_qty' => '10', 'by_customer' => [1 => '7.5', 2 => '2.5']],
            ['unit' => 'kg', 'total_qty' => '5', 'by_customer' => [1 => '1.5', 2 => '3.5']],
        ];
        $customer = view('pdf.partials.receipt-quantity-total', ['quantityRows' => $rows, 'quantityKey' => 'total_qty', 'quantityCustomerId' => 1])->render();
        $organization = view('pdf.partials.receipt-quantity-total', ['quantityRows' => $rows, 'quantityKey' => 'total_qty'])->render();
        self::assertStringContainsString('9&nbsp;kg', $customer);
        self::assertStringContainsString('15&nbsp;kg', $organization);
    }

    public function test_decimal_quantities_retain_four_places_without_accumulating_float_errors(): void
    {
        $html = view('pdf.partials.receipt-quantity-total', ['quantityRows' => [
            ['unit' => 'kg', 'quantity' => '0.3333'],
            ['unit' => 'kg', 'quantity' => '0.3333'],
            ['unit' => 'kg', 'quantity' => '0.3334'],
        ]])->render();
        self::assertStringContainsString('1&nbsp;kg', $html);
    }
}

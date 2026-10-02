<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionDelivery;
use App\Services\ReceiptDataBuilder;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReceiptDataBuilderGroupingTest extends TestCase
{
    public function test_associate_receipt_groups_same_product_date_customer_and_price(): void
    {
        $product = new Product;
        $product->setRawAttributes(['id' => 7, 'name' => 'Banana', 'unit' => 'kg'], true);
        $customer = new Customer;
        $customer->setRawAttributes(['id' => 9, 'name' => 'Escola Central'], true);
        $deliveries = collect([
            $this->distribution(1, 101, '2026-08-18', 2, 20, 2, 18, $product, $customer),
            $this->distribution(2, 102, '2026-08-18', 3, 30, 3, 27, $product, $customer),
            $this->distribution(3, 103, '2026-08-19', 4, 40, 4, 36, $product, $customer),
        ]);

        $data = ReceiptDataBuilder::fromDeliveries($deliveries);

        self::assertCount(3, $data['productsSummary']);
        self::assertCount(2, $data['productsByDate']);
        self::assertSame('2026-08-18', $data['productsByDate'][0]['delivery_date']->format('Y-m-d'));
        self::assertCount(1, $data['productsByDate'][0]['distributions']);
        self::assertSame(5.0, $data['productsByDate'][0]['distributions'][0]['quantity']);
        self::assertSame(50.0, $data['productsByDate'][0]['total_gross']);
        self::assertCount(1, $data['productTotals']);
        self::assertSame(9.0, $data['productTotals'][0]['total_quantity']);
        self::assertSame(90.0, $data['productTotals'][0]['total_gross']);
        self::assertSame(81.0, $data['productTotals'][0]['total_net']);
    }

    private function distribution(
        int $id,
        int $parentId,
        string $date,
        float $quantity,
        float $gross,
        float $fee,
        float $net,
        Product $product,
        Customer $customer,
    ): ProductionDelivery {
        $distribution = new ProductionDelivery;
        $distribution->setRawAttributes([
            'id' => $id,
            'parent_delivery_id' => $parentId,
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'delivery_date' => $date,
            'quantity' => $quantity,
            'unit_price' => 10,
            'gross_value' => $gross,
            'admin_fee_amount' => $fee,
            'net_value' => $net,
        ], true);
        $distribution->setAttribute('delivery_date', Carbon::parse($date));
        $distribution->setRelation('product', $product);
        $distribution->setRelation('customer', $customer);

        return $distribution;
    }
}

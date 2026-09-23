<?php

namespace Tests\Feature;

use App\Models\ProductionDelivery;
use App\Models\User;
use App\Services\DeliveryQuantityAdjustmentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DeliveryQuantityAdjustmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('production_delivery_quantity_adjustments');
        Schema::dropIfExists('production_deliveries');

        Schema::create('production_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('parent_delivery_id')->nullable();
            $table->decimal('quantity', 12, 4);
            $table->decimal('original_quantity', 12, 4)->nullable();
            $table->string('status');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('production_delivery_quantity_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('production_delivery_id');
            $table->string('kind');
            $table->decimal('quantity', 12, 4);
            $table->decimal('quantity_before', 12, 4);
            $table->decimal('quantity_after', 12, 4);
            $table->text('reason');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        session(['tenant_id' => 1]);
    }

    public function test_partial_rejection_keeps_only_the_accepted_quantity_and_records_audit_history(): void
    {
        $this->delivery(1, null, 100, 'pending');
        $actor = new User;
        $actor->id = 7;

        $adjustment = ProductionDelivery::withoutEvents(fn () => app(DeliveryQuantityAdjustmentService::class)
            ->adjust(ProductionDelivery::findOrFail(1), 25, 'Produto fora do padrão.', $actor));

        $delivery = ProductionDelivery::findOrFail(1);
        $this->assertSame('75.0000', $delivery->quantity);
        $this->assertSame('100.0000', $delivery->original_quantity);
        $this->assertSame('approved', $delivery->status->value);
        $this->assertSame('rejection', $adjustment->kind);
        $this->assertSame('25.0000', $adjustment->quantity);
        $this->assertSame('Produto fora do padrão.', $adjustment->reason);
    }

    public function test_return_never_reduces_a_delivery_below_its_distributed_quantity(): void
    {
        $this->delivery(1, null, 80, 'approved');
        $this->delivery(2, 1, 50, 'approved');
        $actor = new User;
        $actor->id = 7;

        try {
            ProductionDelivery::withoutEvents(fn () => app(DeliveryQuantityAdjustmentService::class)
                ->adjust(ProductionDelivery::findOrFail(1), 31, 'Tentativa acima do saldo.', $actor));
            $this->fail('Uma devolução acima do saldo livre deveria ser rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }

        $adjustment = ProductionDelivery::withoutEvents(fn () => app(DeliveryQuantityAdjustmentService::class)
            ->adjust(ProductionDelivery::findOrFail(1), 30, 'Saldo não utilizado devolvido.', $actor));

        $this->assertSame('return', $adjustment->kind);
        $this->assertSame('50.0000', ProductionDelivery::findOrFail(1)->quantity);
    }

    private function delivery(int $id, ?int $parentId, float $quantity, string $status): void
    {
        DB::table('production_deliveries')->insert([
            'id' => $id,
            'tenant_id' => 1,
            'parent_delivery_id' => $parentId,
            'quantity' => $quantity,
            'original_quantity' => $quantity,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

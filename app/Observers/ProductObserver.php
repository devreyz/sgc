<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\NotificationService;

class ProductObserver
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        // Check if stock just went below minimum
        if ($product->wasChanged('current_stock')) {
            $previousStock = $product->getOriginal('current_stock');
            
            // Only notify if crossing the threshold downward
            if ($previousStock > $product->min_stock && $product->current_stock <= $product->min_stock) {
                $this->notificationService->notifyLowStock($product);
            }
        }
    }
}

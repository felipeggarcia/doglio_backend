<?php

namespace App\Observers;

use App\Jobs\NotifyLowStockJob;
use App\Jobs\NotifyRestockJob;
use App\Models\Product;

class ProductObserver
{
    /**
     * Threshold para considerar estoque baixo.
     */
    private const LOW_STOCK_THRESHOLD = 5;

    public function updating(Product $product): void
    {
        if (!$product->isDirty('stock_quantity')) {
            return;
        }

        $oldStock = (int) $product->getOriginal('stock_quantity');
        $newStock = (int) $product->stock_quantity;

        // Produto voltou ao estoque (estava zerado, agora tem)
        if ($oldStock === 0 && $newStock > 0) {
            NotifyRestockJob::dispatch($product->id);
            return;
        }

        // Estoque ficou baixo (cruzou o threshold de cima para baixo)
        if ($oldStock > self::LOW_STOCK_THRESHOLD && $newStock <= self::LOW_STOCK_THRESHOLD && $newStock > 0) {
            NotifyLowStockJob::dispatch($product->id, $newStock);
        }
    }
}

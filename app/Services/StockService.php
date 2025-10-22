<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Adjust product stock and create movement record
     */
    public function adjustStock(
        Product $product,
        float $qtyIn = 0,
        float $qtyOut = 0,
        string $refType = 'ADJUSTMENT',
        ?string $refId = null,
        ?string $notes = null
    ): StockMovement {
        return DB::transaction(function () use ($product, $qtyIn, $qtyOut, $refType, $refId, $notes) {
            // Calculate new balance
            $newBalance = $product->stock_qty + $qtyIn - $qtyOut;

            // Create stock movement record (append-only ledger)
            $movement = StockMovement::create([
                'company_id' => $product->company_id,
                'product_id' => $product->id,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'qty_in' => $qtyIn,
                'qty_out' => $qtyOut,
                'balance_after' => $newBalance,
                'notes' => $notes,
            ]);

            // Update product stock_qty
            $product->update(['stock_qty' => $newBalance]);

            return $movement;
        });
    }

    /**
     * Get stock movements for a product
     */
    public function getMovements(Product $product, int $limit = 50)
    {
        return $product->stockMovements()
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get products with low stock
     */
    public function getLowStockProducts(float $threshold = 10)
    {
        return Product::where('stock_qty', '<=', $threshold)
            ->where('is_active', true)
            ->orderBy('stock_qty')
            ->get();
    }
}

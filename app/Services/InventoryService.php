<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * InventoryService - Single source of truth for all stock adjustments
 *
 * Key Features:
 * - Row-level locking to prevent negative balances
 * - Append-only ledger for complete audit trail
 * - All stock changes MUST go through this service
 */
class InventoryService
{
    /**
     * Adjust product stock with row-level locking
     *
     * @param Product $product The product to adjust
     * @param float $qtyDelta Delta quantity (positive for increase, negative for decrease)
     * @param string $refType Reference type (OPENING, PURCHASE, SALE, RETURN, ADJUSTMENT)
     * @param string|null $refId Reference ID (order number, PO number, etc.)
     * @param string|null $notes Additional notes
     * @return StockMovement
     * @throws \Exception If adjustment would result in negative balance
     */
    public function adjust(
        Product $product,
        float $qtyDelta,
        string $refType = 'ADJUSTMENT',
        ?string $refId = null,
        ?string $notes = null
    ): StockMovement {
        return DB::transaction(function () use ($product, $qtyDelta, $refType, $refId, $notes) {
            // CRITICAL: Lock the product row for update (prevents concurrent modifications)
            $lockedProduct = Product::where('id', $product->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedProduct) {
                throw new \Exception("Product not found: {$product->id}");
            }

            // Calculate new balance
            $newBalance = $lockedProduct->stock_qty + $qtyDelta;

            // PREVENT NEGATIVE BALANCES
            if ($newBalance < 0) {
                throw new \Exception(
                    "Insufficient stock for product {$lockedProduct->sku}. " .
                    "Current: {$lockedProduct->stock_qty}, Requested: " . abs($qtyDelta) .
                    ", Shortage: " . abs($newBalance)
                );
            }

            // Split delta into qty_in and qty_out for ledger accuracy
            $qtyIn = $qtyDelta > 0 ? $qtyDelta : 0;
            $qtyOut = $qtyDelta < 0 ? abs($qtyDelta) : 0;

            // Create append-only movement record (audit trail)
            $movement = StockMovement::create([
                'company_id' => $lockedProduct->company_id,
                'product_id' => $lockedProduct->id,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'qty_in' => $qtyIn,
                'qty_out' => $qtyOut,
                'balance_after' => $newBalance,
                'notes' => $notes,
            ]);

            // Update product stock_qty (denormalized for performance)
            $lockedProduct->update(['stock_qty' => $newBalance]);

            return $movement;
        });
    }

    /**
     * Get stock movement history for a product
     *
     * @param Product $product
     * @param int $limit
     * @param string|null $refType Filter by reference type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getMovements(
        Product $product,
        int $limit = 50,
        ?string $refType = null
    ) {
        $query = $product->stockMovements()
            ->latest('created_at');

        if ($refType) {
            $query->where('ref_type', $refType);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Get all stock movements for a company (paginated)
     *
     * @param int $companyId
     * @param int $perPage
     * @param array $filters Optional filters (product_id, ref_type, date_from, date_to)
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getCompanyMovements(
        int $companyId,
        int $perPage = 50,
        array $filters = []
    ) {
        $query = StockMovement::where('company_id', $companyId)
            ->with(['product:id,sku,name'])
            ->latest('created_at');

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['ref_type'])) {
            $query->where('ref_type', $filters['ref_type']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get products with low stock
     *
     * @param int $companyId
     * @param float $threshold
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLowStockProducts(int $companyId, float $threshold = 10)
    {
        return Product::where('company_id', $companyId)
            ->where('stock_qty', '<=', $threshold)
            ->where('stock_qty', '>', 0)
            ->where('is_active', true)
            ->orderBy('stock_qty')
            ->get();
    }

    /**
     * Get out-of-stock products
     *
     * @param int $companyId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getOutOfStockProducts(int $companyId)
    {
        return Product::where('company_id', $companyId)
            ->where('stock_qty', '<=', 0)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Bulk adjust stock for multiple products
     *
     * @param array $adjustments Array of ['product_id' => qty_delta]
     * @param int $companyId
     * @param string $refType
     * @param string|null $refId
     * @param string|null $notes
     * @return array Array of created StockMovement records
     * @throws \Exception
     */
    public function bulkAdjust(
        array $adjustments,
        int $companyId,
        string $refType = 'ADJUSTMENT',
        ?string $refId = null,
        ?string $notes = null
    ): array {
        return DB::transaction(function () use ($adjustments, $companyId, $refType, $refId, $notes) {
            $movements = [];

            foreach ($adjustments as $productId => $qtyDelta) {
                $product = Product::where('company_id', $companyId)
                    ->where('id', $productId)
                    ->firstOrFail();

                $movements[] = $this->adjust(
                    product: $product,
                    qtyDelta: $qtyDelta,
                    refType: $refType,
                    refId: $refId,
                    notes: $notes
                );
            }

            return $movements;
        });
    }
}

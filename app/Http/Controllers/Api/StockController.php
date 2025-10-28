<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StockController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Adjust stock for a product (manual adjustment)
     *
     * POST /api/{company}/stock/adjust
     *
     * Body:
     * {
     *   "product_id": 1,
     *   "qty_delta": 10,  // Positive for increase, negative for decrease
     *   "ref_type": "ADJUSTMENT",  // OPENING, PURCHASE, SALE, RETURN, ADJUSTMENT
     *   "ref_id": "optional-ref-id",
     *   "notes": "Optional notes"
     * }
     */
    public function adjust(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
            'qty_delta' => 'required|numeric',
            'ref_type' => 'required|string|in:OPENING,PURCHASE,SALE,RETURN,ADJUSTMENT',
            'ref_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        try {
            $product = Product::findOrFail($request->product_id);

            // Verify product belongs to current tenant
            if ($product->company_id !== $request->get('company_id')) {
                return response()->json([
                    'error' => 'Product not found or access denied',
                ], 404);
            }

            $movement = $this->inventoryService->adjust(
                product: $product,
                qtyDelta: (float) $request->qty_delta,
                refType: $request->ref_type,
                refId: $request->ref_id,
                notes: $request->notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Stock adjusted successfully',
                'data' => [
                    'movement' => $movement,
                    'product' => [
                        'id' => $product->id,
                        'sku' => $product->sku,
                        'name' => $product->name,
                        'stock_qty' => $product->fresh()->stock_qty,
                    ],
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Stock adjustment failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get stock movement history
     *
     * GET /api/{company}/stock/movements?product_id=1&limit=50&ref_type=SALE
     */
    public function movements(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'nullable|integer|exists:products,id',
            'ref_type' => 'nullable|string|in:OPENING,PURCHASE,SALE,RETURN,ADJUSTMENT',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $companyId = $request->get('company_id');
        $perPage = $request->get('per_page', 50);

        $filters = [
            'product_id' => $request->get('product_id'),
            'ref_type' => $request->get('ref_type'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
        ];

        $movements = $this->inventoryService->getCompanyMovements(
            companyId: $companyId,
            perPage: $perPage,
            filters: array_filter($filters)
        );

        return response()->json([
            'success' => true,
            'data' => $movements,
        ], 200);
    }

    /**
     * Get low stock products
     *
     * GET /api/{company}/stock/low-stock?threshold=10
     */
    public function lowStock(Request $request): JsonResponse
    {
        $threshold = $request->get('threshold', 10);
        $companyId = $request->get('company_id');

        $products = $this->inventoryService->getLowStockProducts(
            companyId: $companyId,
            threshold: (float) $threshold
        );

        return response()->json([
            'success' => true,
            'data' => $products,
        ], 200);
    }

    /**
     * Get out-of-stock products
     *
     * GET /api/{company}/stock/out-of-stock
     */
    public function outOfStock(Request $request): JsonResponse
    {
        $companyId = $request->get('company_id');

        $products = $this->inventoryService->getOutOfStockProducts(
            companyId: $companyId
        );

        return response()->json([
            'success' => true,
            'data' => $products,
        ], 200);
    }
}

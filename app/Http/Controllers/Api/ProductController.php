<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(protected StockService $stockService) {}

    public function index()
    {
        return Product::with('company')
            ->latest()
            ->paginate(20);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'base_uom' => 'nullable|string',
            'attributes' => 'nullable|array',
        ]);

        $product = Product::create($validated);

        return response()->json($product, 201);
    }

    public function show(Product $product)
    {
        return $product->load('stockMovements');
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'sku' => 'sometimes|string|max:255',
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'base_uom' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'attributes' => 'nullable|array',
        ]);

        $product->update($validated);

        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    public function adjustStock(Request $request, Product $product)
    {
        $validated = $request->validate([
            'qty_in' => 'nullable|numeric|min:0',
            'qty_out' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $movement = $this->stockService->adjustStock(
            product: $product,
            qtyIn: $validated['qty_in'] ?? 0,
            qtyOut: $validated['qty_out'] ?? 0,
            refType: 'ADJUSTMENT',
            notes: $validated['notes'] ?? null
        );

        return response()->json($movement->load('product'));
    }
}

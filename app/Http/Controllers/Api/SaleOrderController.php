<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SaleOrder;
use App\Services\SaleOrderService;
use Illuminate\Http\Request;

class SaleOrderController extends Controller
{
    public function __construct(protected SaleOrderService $saleOrderService) {}

    public function index()
    {
        return SaleOrder::with(['customer', 'items.product'])
            ->latest()
            ->paginate(20);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.uom' => 'nullable|string',
            'items.*.discount_value' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
            'items.*.attributes' => 'nullable|array',
            'attributes' => 'nullable|array',
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);

        $saleOrder = $this->saleOrderService->createDraft(
            customer: $customer,
            items: $validated['items'],
            attributes: $validated['attributes'] ?? null,
            createdBy: auth()->id()
        );

        return response()->json($saleOrder, 201);
    }

    public function show(SaleOrder $saleOrder)
    {
        return $saleOrder->load(['customer', 'items.product', 'createdBy', 'approvedBy']);
    }

    public function confirm(SaleOrder $saleOrder)
    {
        $order = $this->saleOrderService->confirm($saleOrder, auth()->id());

        return response()->json($order);
    }

    public function cancel(SaleOrder $saleOrder)
    {
        $order = $this->saleOrderService->cancel($saleOrder);

        return response()->json($order);
    }

    public function markPaymentReceived(Request $request, SaleOrder $saleOrder)
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:cash,transfer',
        ]);

        $order = $this->saleOrderService->markPaymentReceived(
            $saleOrder,
            $validated['payment_method']
        );

        return response()->json($order);
    }
}

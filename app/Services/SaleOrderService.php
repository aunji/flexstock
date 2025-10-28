<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaleOrderService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected DocumentNumberingService $docNumbering
    ) {}

    /**
     * Create a new sale order in Draft status
     */
    public function createDraft(
        Customer $customer,
        array $items,
        ?array $attributes = null,
        ?int $createdBy = null
    ): SaleOrder {
        return DB::transaction(function () use ($customer, $items, $attributes, $createdBy) {
            // Generate sequential transaction ID using DocumentNumberingService
            $txId = $this->docNumbering->generateNumber(
                companyId: $customer->company_id,
                docType: 'SO'
            );

            // Calculate totals
            $subtotal = 0;
            $discountTotal = 0;
            $taxTotal = 0;

            $saleOrder = SaleOrder::create([
                'company_id' => $customer->company_id,
                'tx_id' => $txId,
                'customer_id' => $customer->id,
                'status' => 'Draft',
                'payment_state' => 'PendingReceipt',
                'attributes' => $attributes,
                'created_by' => $createdBy,
            ]);

            // Create items
            foreach ($items as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);

                $lineTotal = ($itemData['qty'] * $itemData['unit_price'])
                    - ($itemData['discount_value'] ?? 0);
                $lineTax = $lineTotal * (($itemData['tax_rate'] ?? 0) / 100);
                $lineTotal += $lineTax;

                SaleOrderItem::create([
                    'sale_order_id' => $saleOrder->id,
                    'product_id' => $product->id,
                    'qty' => $itemData['qty'],
                    'uom' => $itemData['uom'] ?? 'unit',
                    'unit_price' => $itemData['unit_price'],
                    'discount_value' => $itemData['discount_value'] ?? 0,
                    'tax_rate' => $itemData['tax_rate'] ?? 0,
                    'line_total' => $lineTotal,
                    'attributes' => $itemData['attributes'] ?? null,
                ]);

                $subtotal += ($itemData['qty'] * $itemData['unit_price']);
                $discountTotal += ($itemData['discount_value'] ?? 0);
                $taxTotal += $lineTax;
            }

            // Apply customer tier discount if applicable
            if ($customer->tier) {
                $tierDiscount = $this->calculateTierDiscount($subtotal, $customer->tier);
                $discountTotal += $tierDiscount;
            }

            $grandTotal = $subtotal - $discountTotal + $taxTotal;

            $saleOrder->update([
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
            ]);

            return $saleOrder->fresh(['items', 'customer']);
        });
    }

    /**
     * Confirm sale order and deduct stock
     */
    public function confirm(SaleOrder $saleOrder, ?int $approvedBy = null): SaleOrder
    {
        if ($saleOrder->status !== 'Draft') {
            throw new \Exception('Only draft orders can be confirmed');
        }

        return DB::transaction(function () use ($saleOrder, $approvedBy) {
            // Deduct stock for each item using InventoryService
            foreach ($saleOrder->items as $item) {
                $this->inventoryService->adjust(
                    product: $item->product,
                    qtyDelta: -$item->qty, // Negative for stock deduction
                    refType: 'SALE',
                    refId: $saleOrder->tx_id,
                    notes: "Sale Order: {$saleOrder->tx_id}"
                );
            }

            $saleOrder->update([
                'status' => 'Confirmed',
                'approved_by' => $approvedBy,
            ]);

            return $saleOrder->fresh();
        });
    }

    /**
     * Cancel sale order
     */
    public function cancel(SaleOrder $saleOrder): SaleOrder
    {
        if ($saleOrder->status === 'Cancelled') {
            throw new \Exception('Order is already cancelled');
        }

        return DB::transaction(function () use ($saleOrder) {
            // If order was confirmed, restore stock
            if ($saleOrder->status === 'Confirmed') {
                foreach ($saleOrder->items as $item) {
                    $this->inventoryService->adjust(
                        product: $item->product,
                        qtyDelta: $item->qty, // Positive for stock restoration
                        refType: 'RETURN',
                        refId: $saleOrder->tx_id,
                        notes: "Cancelled Sale Order: {$saleOrder->tx_id}"
                    );
                }
            }

            $saleOrder->update(['status' => 'Cancelled']);

            return $saleOrder->fresh();
        });
    }

    /**
     * Mark payment as received (for cash payments or manual approval)
     *
     * @param SaleOrder $saleOrder
     * @param string $paymentMethod cash|transfer
     * @param string|null $notes
     * @return SaleOrder
     */
    public function markPaymentReceived(
        SaleOrder $saleOrder,
        string $paymentMethod,
        ?string $notes = null
    ): SaleOrder {
        // For cash payments: immediate Received status
        // For transfer payments: requires slip upload and approval
        $paymentState = ($paymentMethod === 'cash') ? 'Received' : 'PendingReceipt';

        $saleOrder->update([
            'payment_state' => $paymentState,
            'payment_method' => $paymentMethod,
            'payment_notes' => $notes,
        ]);

        return $saleOrder->fresh();
    }

    /**
     * Calculate tier discount
     */
    protected function calculateTierDiscount(float $subtotal, $tier): float
    {
        if ($tier->discount_type === 'percent') {
            return $subtotal * ($tier->discount_value / 100);
        }

        return $tier->discount_value;
    }
}

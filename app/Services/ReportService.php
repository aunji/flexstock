<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SaleOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Get sales summary for date range
     */
    public function getSalesSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $query = SaleOrder::where('status', 'Confirmed');

        if ($startDate) {
            $query->where('created_at', '>=', Carbon::parse($startDate));
        }

        if ($endDate) {
            $query->where('created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        $orders = $query->get();

        return [
            'total_orders' => $orders->count(),
            'total_revenue' => $orders->sum('grand_total'),
            'total_discount' => $orders->sum('discount_total'),
            'total_tax' => $orders->sum('tax_total'),
            'payment_methods' => $orders->groupBy('payment_method')->map(fn($group) => [
                'count' => $group->count(),
                'total' => $group->sum('grand_total'),
            ]),
        ];
    }

    /**
     * Get top selling products
     */
    public function getTopProducts(int $limit = 10, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = DB::table('sale_order_items')
            ->join('sale_orders', 'sale_order_items.sale_order_id', '=', 'sale_orders.id')
            ->join('products', 'sale_order_items.product_id', '=', 'products.id')
            ->where('sale_orders.status', 'Confirmed')
            ->where('products.company_id', app('current_company_id'));

        if ($startDate) {
            $query->where('sale_orders.created_at', '>=', Carbon::parse($startDate));
        }

        if ($endDate) {
            $query->where('sale_orders.created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        return $query
            ->select(
                'products.id',
                'products.sku',
                'products.name',
                DB::raw('SUM(sale_order_items.qty) as total_qty'),
                DB::raw('SUM(sale_order_items.line_total) as total_revenue'),
                DB::raw('COUNT(DISTINCT sale_orders.id) as order_count')
            )
            ->groupBy('products.id', 'products.sku', 'products.name')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get low stock products
     */
    public function getLowStockReport(float $threshold = 10): array
    {
        return Product::where('stock_qty', '<=', $threshold)
            ->where('is_active', true)
            ->select('id', 'sku', 'name', 'stock_qty', 'base_uom')
            ->orderBy('stock_qty')
            ->get()
            ->toArray();
    }

    /**
     * Get daily sales data
     */
    public function getDailySales(?string $startDate = null, ?string $endDate = null): array
    {
        $query = SaleOrder::where('status', 'Confirmed');

        if ($startDate) {
            $query->where('created_at', '>=', Carbon::parse($startDate));
        }

        if ($endDate) {
            $query->where('created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        return $query
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(grand_total) as revenue'),
                DB::raw('SUM(discount_total) as discounts')
            )
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get()
            ->toArray();
    }
}

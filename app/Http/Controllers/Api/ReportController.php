<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    public function salesSummary(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $summary = $this->reportService->getSalesSummary(
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        return response()->json($summary);
    }

    public function topProducts(Request $request)
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $products = $this->reportService->getTopProducts(
            $validated['limit'] ?? 10,
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        return response()->json($products);
    }

    public function lowStock(Request $request)
    {
        $validated = $request->validate([
            'threshold' => 'nullable|numeric|min:0',
        ]);

        $products = $this->reportService->getLowStockReport(
            $validated['threshold'] ?? 10
        );

        return response()->json($products);
    }

    public function dailySales(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $sales = $this->reportService->getDailySales(
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        return response()->json($sales);
    }
}

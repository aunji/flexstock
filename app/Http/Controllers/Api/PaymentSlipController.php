<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentSlip;
use App\Models\SaleOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentSlipController extends Controller
{
    /**
     * Upload payment slip for a sale order
     *
     * POST /api/{company}/payment-slips
     *
     * Body (multipart/form-data):
     * - sale_order_id: int
     * - slip_file: file (image or PDF)
     * - notes: string (optional)
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'sale_order_id' => 'required|integer|exists:sale_orders,id',
            'slip_file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', // Max 5MB
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $companyId = $request->get('company_id');

            // Verify sale order belongs to company
            $saleOrder = SaleOrder::where('id', $request->sale_order_id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            // Check if payment method is transfer
            if ($saleOrder->payment_method !== 'transfer') {
                return response()->json([
                    'error' => 'Payment slips are only required for transfer payments',
                ], 400);
            }

            // Store file: storage/app/public/slips/{company_id}/{tx_id}/
            $file = $request->file('slip_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs(
                "slips/{$companyId}/{$saleOrder->tx_id}",
                $filename,
                'public'
            );

            // Create payment slip record
            $paymentSlip = PaymentSlip::create([
                'company_id' => $companyId,
                'sale_order_id' => $saleOrder->id,
                'slip_path' => $path,
                'slip_url' => Storage::url($path),
                'status' => 'Pending',
                'notes' => $request->notes,
                'uploaded_by' => $request->user()->id,
            ]);

            // Update sale order payment state to PendingReceipt
            $saleOrder->update([
                'payment_state' => 'PendingReceipt',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment slip uploaded successfully',
                'data' => $paymentSlip->load('saleOrder'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to upload payment slip',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * List payment slips (with optional filters)
     *
     * GET /api/{company}/payment-slips?status=Pending&per_page=20
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|string|in:Pending,Approved,Rejected',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $companyId = $request->get('company_id');
        $perPage = $request->get('per_page', 50);

        $query = PaymentSlip::where('company_id', $companyId)
            ->with(['saleOrder:id,tx_id,customer_id,grand_total', 'uploader:id,name', 'approver:id,name'])
            ->latest();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $slips = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $slips,
        ], 200);
    }

    /**
     * Get a specific payment slip
     *
     * GET /api/{company}/payment-slips/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $request->get('company_id');

        $slip = PaymentSlip::where('company_id', $companyId)
            ->where('id', $id)
            ->with(['saleOrder', 'uploader', 'approver'])
            ->first();

        if (!$slip) {
            return response()->json([
                'error' => 'Payment slip not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $slip,
        ], 200);
    }

    /**
     * Approve a payment slip (admin only)
     *
     * POST /api/{company}/payment-slips/{id}/approve
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $companyId = $request->get('company_id');

            $slip = PaymentSlip::where('company_id', $companyId)
                ->where('id', $id)
                ->with('saleOrder')
                ->firstOrFail();

            if ($slip->status !== 'Pending') {
                return response()->json([
                    'error' => 'Payment slip already processed',
                ], 400);
            }

            // Update slip status
            $slip->update([
                'status' => 'Approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'notes' => $request->notes ?? $slip->notes,
            ]);

            // Update sale order payment state to Received
            $slip->saleOrder->update([
                'payment_state' => 'Received',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment slip approved successfully',
                'data' => $slip->fresh(['saleOrder', 'approver']),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to approve payment slip',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Reject a payment slip (admin only)
     *
     * POST /api/{company}/payment-slips/{id}/reject
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'notes' => 'required|string|max:1000', // Reason for rejection
        ]);

        try {
            $companyId = $request->get('company_id');

            $slip = PaymentSlip::where('company_id', $companyId)
                ->where('id', $id)
                ->firstOrFail();

            if ($slip->status !== 'Pending') {
                return response()->json([
                    'error' => 'Payment slip already processed',
                ], 400);
            }

            // Update slip status
            $slip->update([
                'status' => 'Rejected',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'notes' => $request->notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment slip rejected',
                'data' => $slip->fresh(['saleOrder', 'approver']),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to reject payment slip',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete a payment slip
     *
     * DELETE /api/{company}/payment-slips/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $companyId = $request->get('company_id');

            $slip = PaymentSlip::where('company_id', $companyId)
                ->where('id', $id)
                ->firstOrFail();

            // Delete file from storage
            if ($slip->slip_path) {
                Storage::disk('public')->delete($slip->slip_path);
            }

            $slip->delete();

            return response()->json([
                'success' => true,
                'message' => 'Payment slip deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete payment slip',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

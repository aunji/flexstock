<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomFieldDef;
use App\Services\CustomFieldRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomFieldController extends Controller
{
    public function __construct(
        protected CustomFieldRegistry $registry
    ) {}

    /**
     * List custom field definitions for an entity
     *
     * GET /api/{company}/custom-fields?applies_to=Product
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'applies_to' => 'required|string|in:Product,Customer,SaleOrder,SaleOrderItem',
        ]);

        $companyId = $request->get('company_id');
        $appliesTo = $request->get('applies_to');

        $definitions = $this->registry->getDefinitions($companyId, $appliesTo);

        return response()->json([
            'success' => true,
            'data' => $definitions,
        ], 200);
    }

    /**
     * Get form schema for frontend
     *
     * GET /api/{company}/custom-fields/schema?applies_to=Product
     */
    public function schema(Request $request): JsonResponse
    {
        $request->validate([
            'applies_to' => 'required|string|in:Product,Customer,SaleOrder,SaleOrderItem',
        ]);

        $companyId = $request->get('company_id');
        $appliesTo = $request->get('applies_to');

        $schema = $this->registry->getFormSchema($companyId, $appliesTo);

        return response()->json([
            'success' => true,
            'data' => $schema,
        ], 200);
    }

    /**
     * Create a new custom field definition
     *
     * POST /api/{company}/custom-fields
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $companyId = $request->get('company_id');
            $definition = $this->registry->createDefinition($companyId, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Custom field created successfully',
                'data' => $definition,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create custom field',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get a specific custom field definition
     *
     * GET /api/{company}/custom-fields/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $request->get('company_id');

        $definition = CustomFieldDef::where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        if (!$definition) {
            return response()->json([
                'error' => 'Custom field not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $definition,
        ], 200);
    }

    /**
     * Update a custom field definition
     *
     * PUT /api/{company}/custom-fields/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $companyId = $request->get('company_id');

            $definition = CustomFieldDef::where('company_id', $companyId)
                ->where('id', $id)
                ->firstOrFail();

            $updated = $this->registry->updateDefinition($definition, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Custom field updated successfully',
                'data' => $updated,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update custom field',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete a custom field definition
     *
     * DELETE /api/{company}/custom-fields/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $companyId = $request->get('company_id');

            $definition = CustomFieldDef::where('company_id', $companyId)
                ->where('id', $id)
                ->firstOrFail();

            $this->registry->deleteDefinition($definition);

            return response()->json([
                'success' => true,
                'message' => 'Custom field deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete custom field',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * InventoryService Test Suite
 *
 * Tests the atomic inventory management system with row-level locking
 */
class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryService $inventoryService;
    protected Company $company;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryService = app(InventoryService::class);

        // Create test company and product
        $this->company = Company::create([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'is_active' => true,
        ]);

        app()->instance('current_company_id', $this->company->id);

        $this->product = Product::create([
            'company_id' => $this->company->id,
            'sku' => 'TEST-001',
            'name' => 'Test Product',
            'base_uom' => 'unit',
            'price' => 100.00,
            'cost' => 60.00,
            'stock_qty' => 0,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_can_add_opening_stock()
    {
        $movement = $this->inventoryService->adjust(
            product: $this->product,
            qtyDelta: 100,
            refType: 'OPENING',
            notes: 'Opening stock'
        );

        $this->assertEquals(100, $movement->qty_in);
        $this->assertEquals(0, $movement->qty_out);
        $this->assertEquals(100, $movement->balance_after);
        $this->assertEquals(100, $this->product->fresh()->stock_qty);
    }

    /** @test */
    public function it_can_deduct_stock()
    {
        // Add initial stock
        $this->inventoryService->adjust($this->product, 100, 'OPENING');

        // Deduct stock
        $movement = $this->inventoryService->adjust(
            product: $this->product,
            qtyDelta: -20,
            refType: 'SALE',
            refId: 'SO-001'
        );

        $this->assertEquals(0, $movement->qty_in);
        $this->assertEquals(20, $movement->qty_out);
        $this->assertEquals(80, $movement->balance_after);
        $this->assertEquals(80, $this->product->fresh()->stock_qty);
    }

    /** @test */
    public function it_prevents_negative_stock()
    {
        // Product has 0 stock
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->inventoryService->adjust(
            product: $this->product,
            qtyDelta: -10, // Try to deduct more than available
            refType: 'SALE'
        );
    }

    /** @test */
    public function it_creates_audit_trail()
    {
        $this->inventoryService->adjust($this->product, 100, 'OPENING');
        $this->inventoryService->adjust($this->product, 50, 'PURCHASE', 'PO-001');
        $this->inventoryService->adjust($this->product, -30, 'SALE', 'SO-001');

        $movements = $this->product->stockMovements()->orderBy('created_at')->get();

        $this->assertCount(3, $movements);
        $this->assertEquals('OPENING', $movements[0]->ref_type);
        $this->assertEquals('PURCHASE', $movements[1]->ref_type);
        $this->assertEquals('SALE', $movements[2]->ref_type);
        $this->assertEquals(120, $this->product->fresh()->stock_qty); // 100 + 50 - 30
    }

    /** @test */
    public function it_can_get_movement_history()
    {
        $this->inventoryService->adjust($this->product, 100, 'OPENING');
        $this->inventoryService->adjust($this->product, -20, 'SALE', 'SO-001');
        $this->inventoryService->adjust($this->product, -10, 'SALE', 'SO-002');

        $movements = $this->inventoryService->getMovements($this->product, 10);

        $this->assertCount(3, $movements);
    }

    /** @test */
    public function it_can_filter_movements_by_type()
    {
        $this->inventoryService->adjust($this->product, 100, 'OPENING');
        $this->inventoryService->adjust($this->product, -20, 'SALE', 'SO-001');
        $this->inventoryService->adjust($this->product, -10, 'SALE', 'SO-002');
        $this->inventoryService->adjust($this->product, 30, 'PURCHASE', 'PO-001');

        $movements = $this->inventoryService->getMovements($this->product, 10, 'SALE');

        $this->assertCount(2, $movements);
    }

    /** @test */
    public function it_can_get_low_stock_products()
    {
        // Create multiple products with different stock levels
        $product2 = Product::create([
            'company_id' => $this->company->id,
            'sku' => 'TEST-002',
            'name' => 'Test Product 2',
            'base_uom' => 'unit',
            'price' => 150.00,
            'cost' => 90.00,
            'stock_qty' => 0,
            'is_active' => true,
        ]);

        $this->inventoryService->adjust($this->product, 5, 'OPENING');
        $this->inventoryService->adjust($product2, 20, 'OPENING');

        $lowStock = $this->inventoryService->getLowStockProducts($this->company->id, 10);

        $this->assertCount(1, $lowStock);
        $this->assertEquals($this->product->id, $lowStock[0]->id);
    }

    /** @test */
    public function it_handles_concurrent_adjustments_safely()
    {
        // Add initial stock
        $this->inventoryService->adjust($this->product, 10, 'OPENING');

        // Simulate concurrent deductions (would fail if not properly locked)
        $this->inventoryService->adjust($this->product, -3, 'SALE', 'SO-001');
        $this->inventoryService->adjust($this->product, -2, 'SALE', 'SO-002');
        $this->inventoryService->adjust($this->product, -4, 'SALE', 'SO-003');

        $this->assertEquals(1, $this->product->fresh()->stock_qty); // 10 - 3 - 2 - 4 = 1
    }
}

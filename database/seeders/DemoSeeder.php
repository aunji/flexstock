<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerTier;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo company
        $company = Company::create([
            'id' => Str::uuid(),
            'name' => 'Demo SME Store',
            'slug' => 'demo-sme',
            'is_active' => true,
        ]);

        // Create demo user
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@demo.com',
            'password' => Hash::make('password123'),
        ]);

        // Attach user to company as admin
        $company->users()->attach($user->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);

        // Create customer tiers
        CustomerTier::create([
            'company_id' => $company->id,
            'code' => 'GOLD',
            'name' => 'Gold Member',
            'discount_type' => 'percent',
            'discount_value' => 10,
        ]);

        CustomerTier::create([
            'company_id' => $company->id,
            'code' => 'SILVER',
            'name' => 'Silver Member',
            'discount_type' => 'percent',
            'discount_value' => 5,
        ]);

        // Set current company for tenant scope
        app()->instance('current_company_id', $company->id);

        // Create customers
        Customer::create([
            'company_id' => $company->id,
            'phone' => '0812345678',
            'name' => 'John Doe',
            'tier_code' => 'GOLD',
        ]);

        Customer::create([
            'company_id' => $company->id,
            'phone' => '0898765432',
            'name' => 'Jane Smith',
            'tier_code' => 'SILVER',
        ]);

        // Create products
        $products = [
            ['sku' => 'P001', 'name' => 'Product A', 'price' => 100.00, 'cost' => 60.00, 'stock_qty' => 50],
            ['sku' => 'P002', 'name' => 'Product B', 'price' => 200.00, 'cost' => 120.00, 'stock_qty' => 30],
            ['sku' => 'P003', 'name' => 'Product C', 'price' => 150.00, 'cost' => 90.00, 'stock_qty' => 20],
            ['sku' => 'P004', 'name' => 'Product D', 'price' => 250.00, 'cost' => 150.00, 'stock_qty' => 5],
        ];

        foreach ($products as $productData) {
            Product::create(array_merge($productData, [
                'company_id' => $company->id,
                'base_uom' => 'unit',
                'is_active' => true,
            ]));
        }

        $this->command->info('Demo data seeded successfully!');
        $this->command->info('Login: admin@demo.com / password123');
        $this->command->info('Company slug: demo-sme');
    }
}

# FlexStock - Quick Start Guide

## What is FlexStock?

FlexStock is a **multi-tenant inventory and sales management system** designed specifically for small and medium enterprises (SMEs). It provides everything you need to manage products, customers, sales orders, and generate reports - all with complete data isolation between companies.

## Key Features

✅ **Multi-Tenant**: Each company has isolated data using `company_id`
✅ **User Roles**: Admin, Cashier, Viewer per company
✅ **Customer Tiers**: Automatic discounts based on customer tier (Gold, Silver, etc.)
✅ **Stock Tracking**: Append-only ledger for complete audit trail
✅ **Sale Orders**: Draft → Confirmed → Cancelled workflow
✅ **Payment Tracking**: Cash/Transfer with PendingReceipt → Received states
✅ **Reports**: Sales summary, top products, low stock alerts, daily sales
✅ **Custom Fields**: Add custom fields to any entity dynamically

## Running Locally (5 Minutes Setup)

### Option 1: Docker (Recommended)

```bash
# 1. Copy environment file
cp .env.example .env

# 2. Start containers
docker-compose up -d

# 3. Install dependencies (if not done)
docker-compose exec app composer install

# 4. Run migrations
docker-compose exec app php artisan migrate

# 5. Seed demo data
docker-compose exec app php artisan db:seed --class=DemoSeeder

# 6. Access the app
# API: http://localhost:8000/api
```

### Option 2: Local PHP

```bash
# 1. Copy environment
cp .env.example .env

# 2. Update .env to use local database
# DB_HOST=127.0.0.1
# CACHE_DRIVER=file
# QUEUE_CONNECTION=sync

# 3. Install dependencies
composer install

# 4. Generate key
php artisan key:generate

# 5. Run migrations
php artisan migrate

# 6. Seed demo data
php artisan db:seed --class=DemoSeeder

# 7. Serve
php artisan serve
```

## Testing the API

### 1. Login

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@demo.com",
    "password": "password123"
  }'
```

**Response:**
```json
{
  "user": { "id": 1, "name": "Admin User", ... },
  "token": "1|xxxxxxxxxxxxxxxxxxxx"
}
```

### 2. Get Products

```bash
curl -X GET http://localhost:8000/api/demo-sme/products \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### 3. Create Sale Order

```bash
curl -X POST http://localhost:8000/api/demo-sme/sale-orders \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": 1,
    "items": [
      {
        "product_id": 1,
        "qty": 2,
        "unit_price": 100.00
      }
    ]
  }'
```

### 4. Confirm Sale Order

```bash
curl -X POST http://localhost:8000/api/demo-sme/sale-orders/1/confirm \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### 5. Get Sales Report

```bash
curl -X GET "http://localhost:8000/api/demo-sme/reports/sales-summary?start_date=2025-01-01&end_date=2025-12-31" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## Demo Data

After seeding, you'll have:

- **Company**: "Demo SME Store" (slug: `demo-sme`)
- **User**: admin@demo.com / password123
- **Customer Tiers**: GOLD (10% off), SILVER (5% off)
- **Customers**: 2 sample customers
- **Products**: 4 sample products with varying stock levels

## API Endpoints Overview

### Auth
- `POST /api/login` - Login
- `POST /api/logout` - Logout
- `GET /api/me` - Current user

### Products (tenant-scoped)
- `GET /api/{company}/products` - List products
- `POST /api/{company}/products` - Create product
- `GET /api/{company}/products/{id}` - Get product
- `PUT /api/{company}/products/{id}` - Update product
- `DELETE /api/{company}/products/{id}` - Delete product
- `POST /api/{company}/products/{id}/adjust-stock` - Adjust stock

### Sale Orders (tenant-scoped)
- `GET /api/{company}/sale-orders` - List orders
- `POST /api/{company}/sale-orders` - Create draft order
- `GET /api/{company}/sale-orders/{id}` - Get order
- `POST /api/{company}/sale-orders/{id}/confirm` - Confirm order
- `POST /api/{company}/sale-orders/{id}/cancel` - Cancel order
- `POST /api/{company}/sale-orders/{id}/mark-payment-received` - Mark paid

### Reports (tenant-scoped)
- `GET /api/{company}/reports/sales-summary` - Sales summary
- `GET /api/{company}/reports/top-products` - Best sellers
- `GET /api/{company}/reports/low-stock` - Low stock items
- `GET /api/{company}/reports/daily-sales` - Daily breakdown

## Architecture Highlights

### Multi-Tenancy
- URL format: `/api/{company}/...`
- Middleware automatically filters all queries by `company_id`
- Global scope ensures no data leakage between tenants
- Users can belong to multiple companies with different roles

### Stock Management
- Append-only `stock_movements` table (audit trail)
- Every adjustment creates a new record
- Product `stock_qty` is denormalized for performance
- Transactions ensure consistency

### Sale Order Workflow
1. **Draft**: Created but not confirmed (no stock impact)
2. **Confirmed**: Stock deducted, order finalized
3. **Cancelled**: If was confirmed, stock restored

### Custom Fields
- Define custom fields per company per entity
- Stored in JSON `attributes` column
- Supports: text, number, boolean, date, select, multiselect
- Validation rules can be defined

## Database Schema

```
companies (tenant)
  ├─ company_user (pivot with roles)
  ├─ customer_tiers
  ├─ customers
  ├─ products
  │    └─ stock_movements (append-only ledger)
  ├─ sale_orders
  │    └─ sale_order_items
  └─ custom_field_defs
```

## Production Deployment

1. Update `.env` for production:
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - Set strong `APP_KEY`
   - Configure real database

2. Set up SSL with Caddy:
   ```
   your-domain.com {
       reverse_proxy app:9000
   }
   ```

3. Run migrations:
   ```bash
   php artisan migrate --force
   ```

4. Optimize for production:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

## Next Steps

- Add more customers and products
- Configure custom fields for your business
- Set up customer tiers with appropriate discounts
- Integrate with frontend (React, Vue, Mobile app)
- Add more reports as needed
- Implement Filament admin panel (already installed!)

## Support

- Repository: https://github.com/aunji/flexstock
- Issues: https://github.com/aunji/flexstock/issues

---

Built with Laravel 10, MySQL 8, Redis, and Filament v3

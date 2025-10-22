# FlexStock - Multi-Tenant Inventory & Sales Management System

**FlexStock** is a simple yet powerful inventory and sales management system designed for small and medium enterprises (SMEs). Built with Laravel 10, it features multi-tenancy, custom fields, stock movements tracking, and comprehensive reporting.

## Features

- **Multi-Tenant Architecture**: Separate data per company using `company_id` and route prefix `{company}`
- **User & Role Management**: Admin, Cashier, and Viewer roles per company with Sanctum authentication
- **Customer Management**: Customer database with phone unique per company + tier-based discounts
- **Product & Inventory**: SKU management, pricing, cost tracking, and append-only stock movement ledger
- **Sale Orders**: Draft → Confirmed → Cancelled workflow with cash/transfer payment tracking
- **Custom Fields**: Dynamically add custom fields to Products, Customers, Sale Orders, and Items
- **Reports**: Sales summary, top products, low stock alerts, daily sales, and payment mix analysis

## Tech Stack

- **Backend**: Laravel 10 (PHP 8.1)
- **Database**: MySQL 8.0
- **Cache/Queue**: Redis
- **Authentication**: Laravel Sanctum (API tokens)
- **Admin Panel**: Filament v3
- **Containerization**: Docker Compose + Caddy

## Installation

### Prerequisites
- Docker & Docker Compose
- Composer (if running locally)
- PHP 8.1+ (if running locally)

### Setup with Docker

1. **Clone the repository**
```bash
git clone <your-repo-url> flexstock
cd flexstock
```

2. **Copy environment file**
```bash
cp .env.example .env
```

3. **Generate application key**
```bash
php artisan key:generate
```

4. **Start Docker containers**
```bash
docker-compose up -d
```

5. **Install dependencies**
```bash
docker-compose exec app composer install
```

6. **Run migrations**
```bash
docker-compose exec app php artisan migrate
```

7. **Seed demo data**
```bash
docker-compose exec app php artisan db:seed --class=DemoSeeder
```

8. **Access the application**
- API: `http://localhost:8000/api`
- Admin Panel: `http://localhost:8000/admin`

### Demo Credentials
- Email: `admin@demo.com`
- Password: `password123`
- Company Slug: `demo-sme`

## API Documentation

### Authentication

**Login**
```http
POST /api/login
Content-Type: application/json

{
  "email": "admin@demo.com",
  "password": "password123"
}
```

**Response**
```json
{
  "user": {...},
  "token": "1|..."
}
```

### Multi-Tenant Routes

All tenant routes require:
- Bearer token authentication
- Company slug in URL: `/api/{company}/...`

**Example: List Products**
```http
GET /api/demo-sme/products
Authorization: Bearer {token}
```

**Example: Create Sale Order**
```http
POST /api/demo-sme/sale-orders
Authorization: Bearer {token}
Content-Type: application/json

{
  "customer_id": 1,
  "items": [
    {
      "product_id": 1,
      "qty": 2,
      "unit_price": 100.00,
      "uom": "unit"
    }
  ]
}
```

**Example: Confirm Sale Order**
```http
POST /api/demo-sme/sale-orders/{id}/confirm
Authorization: Bearer {token}
```

**Example: Reports**
```http
GET /api/demo-sme/reports/sales-summary?start_date=2025-01-01&end_date=2025-01-31
GET /api/demo-sme/reports/top-products?limit=10
GET /api/demo-sme/reports/low-stock?threshold=10
GET /api/demo-sme/reports/daily-sales
```

## Database Structure

### Core Tables
- `companies` - Tenant companies
- `users` - System users
- `company_user` - User-company membership with roles
- `customer_tiers` - Discount tiers
- `customers` - Customer database
- `products` - Product catalog
- `stock_movements` - Append-only inventory ledger
- `sale_orders` - Sales transactions
- `sale_order_items` - Line items
- `custom_field_defs` - Custom field definitions

## Development

### Running Tests
```bash
docker-compose exec app php artisan test
```

### Code Style
```bash
docker-compose exec app ./vendor/bin/pint
```

### Clear Cache
```bash
docker-compose exec app php artisan optimize:clear
```

## Deployment

For production deployment:

1. Set `APP_ENV=production` in `.env`
2. Set `APP_DEBUG=false`
3. Configure proper database credentials
4. Set up Redis for caching and queues
5. Configure Caddy with your domain and SSL

## License

This project is open-sourced software licensed under the MIT license.

## Support

For issues and feature requests, please use the GitHub issue tracker.

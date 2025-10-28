# FlexStock Enhancement Pack - Implementation Guide

## Phase 1: ✅ COMPLETED

The following enhancements have been implemented:

1. ✅ Database hardening (composite indexes + foreign keys)
2. ✅ Atomic InventoryService with row-level locking
3. ✅ Document numbering system
4. ✅ Stock API endpoints
5. ✅ Payment slip infrastructure

## Phase 2: Pending Implementation

### Step 1: Run Migrations & Test

```bash
# 1. Ensure database is running
docker-compose up -d mysql

# 2. Run migrations
php artisan migrate

# 3. Test with demo seeder
php artisan db:seed --class=DemoSeeder
```

### Step 2: Implement Remaining Features

#### A. CustomFieldRegistry Service
Create `app/Services/CustomFieldRegistry.php`:
- Dynamic validation rule generation
- JSON expression indexing support
- Custom field definition CRUD

#### B. Payment Slip Approval Workflow
Create `app/Http/Controllers/Api/PaymentSlipController.php`:
- Upload payment slip (transfer method)
- Approve/reject payment slips (admin only)
- Auto-approve for cash payments
- File storage in `storage/app/public/slips/{company}/{tx}/`

#### C. Report Consistency
Update `app/Services/ReportService.php`:
- Filter by `status = 'Confirmed'` AND `payment_state = 'Received'`
- Add indexes to support these queries (already done)

#### D. Security Layer
1. **RBAC Middleware** (`app/Http/Middleware/CheckRole.php`):
   - Admin can do everything
   - Cashier can create/confirm orders, manage payments
   - Viewer can only read

2. **Rate Limiting** (`app/Providers/RouteServiceProvider.php`):
   - Auth endpoints: 5 requests/minute
   - Financial operations: 20 requests/minute
   - General API: 60 requests/minute

3. **CORS Configuration** (`config/cors.php`):
   - Configure allowed origins
   - Set proper headers

### Step 3: Testing

#### A. Create Feature Tests

```bash
php artisan make:test InventoryServiceTest
php artisan make:test StockAPITest
php artisan make:test PaymentWorkflowTest
php artisan make:test DocumentNumberingTest
```

**Test Coverage:**
- Stock deduction prevents negative balances
- Order confirmation triggers stock deduction
- Payment slip approval workflow
- Document number sequencing
- Tenant isolation
- Report filtering

#### B. Run Tests

```bash
php artisan test --coverage
```

### Step 4: CI/CD Pipeline

Create `.github/workflows/ci.yml`:

```yaml
name: CI Pipeline

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: laravel_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.1
          extensions: mbstring, pdo_mysql

      - name: Install Dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Copy .env
        run: cp .env.example .env

      - name: Generate Key
        run: php artisan key:generate

      - name: Run Migrations
        run: php artisan migrate --force
        env:
          DB_HOST: 127.0.0.1
          DB_DATABASE: laravel_test
          DB_USERNAME: root
          DB_PASSWORD: password

      - name: Run Tests
        run: php artisan test
```

### Step 5: Documentation

Update the following:

1. **README.md** - Add new API endpoints
2. **QUICKSTART.md** - Update with new features
3. **API_REFERENCE.md** - Create comprehensive API docs

## Quick Command Reference

### New API Endpoints

#### Stock Management
```bash
# Manual stock adjustment
curl -X POST http://localhost:8000/api/demo-sme/stock/adjust \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 1,
    "qty_delta": 50,
    "ref_type": "OPENING",
    "notes": "Initial stock"
  }'

# Get stock movements
curl -X GET "http://localhost:8000/api/demo-sme/stock/movements?product_id=1&per_page=20" \
  -H "Authorization: Bearer {token}"

# Low stock alerts
curl -X GET "http://localhost:8000/api/demo-sme/stock/low-stock?threshold=10" \
  -H "Authorization: Bearer {token}"
```

## Smoke Test Sequence

1. **Setup**: Run migrations and seeder
2. **Opening Stock**: Adjust stock for all products via `/stock/adjust`
3. **Create Order**: Create sale order with multiple items
4. **Verify Draft**: Check stock hasn't changed
5. **Confirm Order**: Confirm order and verify stock deduction
6. **Check Movement**: View stock movements ledger
7. **Payment**: Upload slip (transfer) or mark received (cash)
8. **Reports**: Verify only Confirmed+Received orders appear

## Production Deployment Checklist

- [ ] All migrations run successfully
- [ ] All tests passing (100% coverage on critical paths)
- [ ] Foreign key constraints verified
- [ ] Indexes optimized for query patterns
- [ ] Rate limiting configured
- [ ] CORS properly set
- [ ] File uploads working (payment slips)
- [ ] Document numbering tested across month boundaries
- [ ] Negative balance prevention verified
- [ ] Tenant isolation validated

## Next Claude Code Session

When continuing, ask Claude Code to:

1. "Implement the CustomFieldRegistry service for dynamic validation"
2. "Create the payment slip approval workflow with file uploads"
3. "Add RBAC middleware and rate limiting"
4. "Write comprehensive feature tests for all new functionality"
5. "Set up the GitHub Actions CI/CD pipeline"
6. "Update all documentation with new API endpoints and examples"

---

**Current Status**: Phase 1 complete, ready for migrations and Phase 2 implementation.

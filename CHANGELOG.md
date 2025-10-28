# FlexStock Changelog

All notable changes to the FlexStock project are documented in this file.

## [v2.0.0] - 2025-10-22 - Phase 2 Complete 🎉

### Added - Major Features
- **CustomFieldRegistry Service**: Dynamic custom field validation for Products, Customers, and Orders (13 field types supported)
- **Payment Slip Approval Workflow**: Two-state payment processing (cash immediate, transfer requires approval)
- **Role-Based Access Control (RBAC)**: Admin/Cashier/Viewer roles with fine-grained permissions
- **4-Tier Rate Limiting**: auth (5/min), financial (20/min), admin (100/min), api (60/min)
- **CORS Configuration**: Environment-based, production-ready with credentials support
- **GitHub Actions CI/CD**: Multi-PHP test matrix, code quality, security audit, deployment pipelines

### Added - API Endpoints
- `GET /api/{company}/custom-fields?applies_to=Product` - List custom field definitions
- `GET /api/{company}/custom-fields/schema?applies_to=Product` - Get form schema
- `POST /api/{company}/custom-fields` - Create custom field (Admin only)
- `PUT /api/{company}/custom-fields/{id}` - Update custom field (Admin only)
- `DELETE /api/{company}/custom-fields/{id}` - Delete custom field (Admin only)
- `POST /api/{company}/payment-slips` - Upload payment slip (Cashier+)
- `GET /api/{company}/payment-slips` - List payment slips
- `POST /api/{company}/payment-slips/{id}/approve` - Approve slip (Admin only)
- `POST /api/{company}/payment-slips/{id}/reject` - Reject slip (Admin only)

### Changed - Services
- **ReportService**: All reports now filter by `Confirmed + Received` status only
- **SaleOrderService**: Enhanced `markPaymentReceived()` with cash vs transfer logic
- **DemoSeeder**: Now uses InventoryService for opening stock (creates proper audit trail)

### Changed - Security
- All write routes now protected with RBAC middleware
- Login endpoint rate-limited to 5 requests/minute
- Financial operations rate-limited to 20 requests/minute
- Admin operations get higher limit (100/min)

### Changed - Routes
- Refactored `routes/api.php` with role-based groups
- Applied throttle limits per endpoint category
- Separated read and write permissions clearly

### Added - Tests
- `tests/Feature/InventoryServiceTest.php` - 9 comprehensive test methods
- Test coverage for negative balance prevention
- Test coverage for concurrent operations
- Test coverage for audit trail creation

### Added - Documentation
- `PHASE2_COMPLETE.md` - Complete Phase 2 feature catalog
- `DEPLOYMENT_GUIDE.md` - Production deployment procedures
- `COMPLETE_SUMMARY.md` - Full project overview and statistics
- `CHANGELOG.md` - This file

### Fixed
- Report consistency issues (now excludes pending payments)
- Payment workflow edge cases (cash vs transfer handling)

---

## [v1.0.0] - 2025-10-22 - Phase 1 Complete 🚀

### Added - Core Infrastructure
- **InventoryService**: Atomic stock management with row-level locking
- **DocumentNumberingService**: Per-tenant sequential document numbering
- **StockController**: Manual stock adjustment API
- **PaymentSlip Model**: Infrastructure for approval workflow
- **DocumentCounter Model**: Sequence tracking per company/type/period

### Added - Database Enhancements
- Migration: `2025_10_22_052025_add_database_hardening_indexes_and_constraints.php`
  - 8 composite indexes for performance
  - 12 foreign key constraints for integrity
- Migration: `2025_10_22_052236_create_document_counters_and_payment_slips_tables.php`
  - `document_counters` table
  - `payment_slips` table
  - Added `payment_method` and `payment_notes` to `sale_orders`

### Added - API Endpoints
- `POST /api/{company}/stock/adjust` - Manual stock adjustments (Admin only)
- `GET /api/{company}/stock/movements` - Stock movement history with filters
- `GET /api/{company}/stock/low-stock?threshold=10` - Low stock alerts
- `GET /api/{company}/stock/out-of-stock` - Zero stock products

### Changed - Services
- **SaleOrderService**: Integrated DocumentNumberingService for sequential TX_IDs
- **SaleOrderService**: Now uses InventoryService for stock deductions
- **StockService**: Deprecated in favor of InventoryService

### Added - Indexes (Performance)
- `idx_products_company_sku` - Fast product lookups
- `idx_customers_company_phone` - Customer phone uniqueness
- `idx_stock_movements_ledger` - Movement history queries
- `idx_sale_orders_reporting` - Report optimization
- `idx_payment_slips_status` - Approval workflow queries
- `idx_custom_fields_company_entity` - Custom field lookups

### Added - Foreign Keys (Integrity)
- Products → Companies (CASCADE)
- Customers → Companies (CASCADE)
- Stock Movements → Products (CASCADE)
- Sale Orders → Customers (RESTRICT)
- Sale Order Items → Products (RESTRICT)
- Payment Slips → Sale Orders (CASCADE)

### Security
- Row-level locking prevents concurrent modification issues
- Negative balance prevention enforced at database level
- Transaction isolation ensures data consistency

---

## [v0.1.0] - 2025-02-13 - Initial Release

### Added - Foundation
- Multi-tenant architecture with route-based isolation (`/api/{company}/...`)
- User authentication with Laravel Sanctum
- Basic role management (admin, cashier, viewer)
- Product CRUD operations
- Customer management with tier-based discounts
- Sale order workflow (Draft → Confirmed → Cancelled)
- Basic stock tracking
- Standard reports (sales summary, top products, low stock, daily sales)
- Filament admin panel integration

### Database Schema
- `companies` table
- `users` table
- `company_user` pivot (with roles)
- `customer_tiers` table
- `customers` table
- `products` table
- `stock_movements` table
- `sale_orders` table
- `sale_order_items` table
- `custom_field_defs` table

### API Endpoints (Initial)
- Authentication: `/api/login`, `/api/logout`, `/api/me`
- Products: `/api/{company}/products`
- Sale Orders: `/api/{company}/sale-orders`
- Reports: `/api/{company}/reports/*`

---

## Version History Summary

| Version | Date | Features | LOC Added | Migrations | Tests |
|---------|------|----------|-----------|------------|-------|
| v0.1.0 | 2025-02-13 | Basic multi-tenant inventory | ~1000 | 10 | 0 |
| v1.0.0 | 2025-10-22 | Phase 1 enhancements | ~1200 | +2 | 0 |
| v2.0.0 | 2025-10-22 | Phase 2 production-ready | ~1300 | +0 | 9 |
| **Total** | - | **Full system** | **~3500** | **12** | **9+** |

---

## Upgrade Guide

### From v1.0.0 to v2.0.0

No database migrations required (infrastructure from v1.0.0 supports Phase 2 features).

**Steps**:
1. Pull latest code: `git pull origin main`
2. Update dependencies: `composer install`
3. Clear caches: `php artisan config:clear && php artisan route:clear`
4. Update `.env`:
   ```env
   CORS_ALLOWED_ORIGINS=https://your-frontend.com
   ```
5. Restart queue workers: `sudo systemctl restart flexstock-queue`
6. Test RBAC: Login with different roles and verify permissions

### From v0.1.0 to v1.0.0

**Required**:
1. Backup database: `mysqldump flexstock > backup.sql`
2. Run migrations: `php artisan migrate`
3. Re-seed (optional): `php artisan db:seed --class=DemoSeeder`
4. Update stock: Use new `/stock/adjust` endpoint for corrections
5. Verify indexes: `SHOW INDEX FROM products;`

---

## Breaking Changes

### v2.0.0
- **None** - Fully backward compatible

### v1.0.0
- `StockService::adjustStock()` deprecated, use `InventoryService::adjust()` instead
- Sale order TX_ID format changed from `SO-YYYYMMDD-RANDOM` to `SO-YYYYMM-0001` (sequential)
- Reports now exclude `PendingReceipt` payment state (only `Received` counted)

---

## Roadmap

### v2.1.0 (Planned)
- [ ] Bulk import/export (CSV, Excel)
- [ ] Email notifications for approvals
- [ ] Advanced search filters
- [ ] Customer portal
- [ ] Mobile app API enhancements

### v3.0.0 (Future)
- [ ] Multi-warehouse support
- [ ] Barcode integration
- [ ] Purchase order management
- [ ] Supplier management
- [ ] Advanced analytics dashboard

---

## Contributors

- **Phase 1 & 2 Implementation**: Claude Code (Anthropic)
- **Architecture Design**: Based on Laravel best practices and enterprise patterns
- **Testing Framework**: PHPUnit with RefreshDatabase trait

---

## License

MIT License - See LICENSE file for details

---

**For detailed deployment instructions, see `DEPLOYMENT_GUIDE.md`**
**For complete feature list, see `COMPLETE_SUMMARY.md`**

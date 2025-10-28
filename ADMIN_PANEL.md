# FlexStock Admin Panel Documentation

## Overview

The FlexStock Admin Panel is a production-ready web interface built with **Filament v3** that provides comprehensive management capabilities for the FlexStock inventory and sales system. It features multi-tenant architecture, role-based access control, and real-time analytics.

**Access URL:** `/admin`

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Authentication & Users](#authentication--users)
3. [Multi-Tenant System](#multi-tenant-system)
4. [Role-Based Access Control (RBAC)](#role-based-access-control-rbac)
5. [Resources](#resources)
6. [Custom Fields](#custom-fields)
7. [Payment Workflow](#payment-workflow)
8. [Dashboard & Analytics](#dashboard--analytics)
9. [Troubleshooting](#troubleshooting)

---

## Getting Started

### First-Time Setup

1. **Create Admin User** (if not exists):
```bash
php artisan tinker
$user = User::create([
    'name' => 'Admin User',
    'email' => 'admin@example.com',
    'password' => bcrypt('password'),
]);
```

2. **Assign User to Company with Admin Role**:
```php
$company = Company::first(); // or find your company
$user->companies()->attach($company->id, ['role' => 'admin']);
```

3. **Access Admin Panel**:
   - Navigate to: `http://your-domain/admin`
   - Login with credentials
   - Select your company (if multiple)

### Navigation Structure

The admin panel is organized into **4 main groups**:

- **Sales** - Sale Orders
- **Inventory** - Products, Stock Movements
- **Customers** - Customer Management
- **Settings** - Custom Fields, Switch Company

---

## Authentication & Users

### Login Process

1. Go to `/admin/login`
2. Enter email and password
3. System authenticates via Laravel Sanctum
4. Redirects to dashboard

### Session Management

- Sessions are tied to browser cookies
- Logout via account menu (top right)
- Idle timeout: 120 minutes (configurable)

---

## Multi-Tenant System

### Company Switcher

**Location:** Settings → Switch Company

**How it Works:**
1. Shows current active company
2. Dropdown lists all companies user has access to
3. Selecting a company:
   - Updates session (`current_company_id`)
   - Applies global scope to all queries
   - Refreshes dashboard

**Behind the Scenes:**
- Middleware: `SetTenantForFilament`
- Session key: `current_company_id`
- Global scopes on: Products, Customers, Orders, Stock Movements, Custom Fields

### Isolation Guarantee

All data operations are **automatically scoped** to the selected company:
- ✅ Users can only see data from their current company
- ✅ Cannot accidentally access other companies' data
- ✅ All creates inherit `company_id` from session

---

## Role-Based Access Control (RBAC)

### Role Hierarchy

| Role | Level | Description |
|------|-------|-------------|
| **Admin** | Full Access | All operations including payment approval |
| **Cashier** | Operations | Create/edit products, orders, customers |
| **Viewer** | Read-Only | View all data, no modifications |

### Permission Matrix

| Feature | Admin | Cashier | Viewer |
|---------|-------|---------|--------|
| **Products** |
| View | ✅ | ✅ | ✅ |
| Create/Edit | ✅ | ✅ | ❌ |
| Delete | ✅ | ❌ | ❌ |
| Adjust Stock | ✅ | ❌ | ❌ |
| **Customers** |
| View | ✅ | ✅ | ✅ |
| Create/Edit | ✅ | ✅ | ❌ |
| Delete | ✅ | ❌ | ❌ |
| **Sale Orders** |
| View | ✅ | ✅ | ✅ |
| Create | ✅ | ✅ | ❌ |
| Edit (Draft) | ✅ | ✅ | ❌ |
| Confirm | ✅ | ✅ | ❌ |
| Cancel | ✅ | ✅ | ❌ |
| Mark Payment | ✅ | ✅ | ❌ |
| **Approve Payment** | ✅ | ❌ | ❌ |
| **Custom Fields** |
| View | ✅ | ✅ | ✅ |
| Manage | ✅ | ❌ | ❌ |
| **Stock Movements** |
| View | ✅ | ✅ | ✅ |

### Assigning Roles

Roles are assigned at the **company-user** level via the `company_user` pivot table:

```php
// Assign user to company with role
$user->companies()->attach($companyId, ['role' => 'cashier']);

// Update existing role
$user->companies()->updateExistingPivot($companyId, ['role' => 'admin']);
```

---

## Resources

### 1. Products

**Path:** Inventory → Products

#### Features:
- **List View:**
  - SKU (searchable, copyable)
  - Name, Price, Cost
  - Stock quantity with low-stock warnings (≤ 10)
  - Active status

- **Filters:**
  - Status (Active/Inactive)
  - Low Stock (≤ 10)
  - Out of Stock

- **Create/Edit:**
  - Basic info: SKU, Name, Base UOM
  - Pricing: Price, Cost
  - Initial stock (create only)
  - Custom fields (if defined)

- **Actions:**
  - **Adjust Stock** (Admin only)
    - Enter qty delta (+/-)
    - Reason/notes required
    - Creates audit trail via `InventoryService`
  - **View Stock History**
    - Links to Stock Movements filtered by product

#### Stock Management Rules:
- Stock can only be adjusted via **Adjust Stock** action (Admin)
- Order confirmations automatically deduct stock
- Cancellations restore stock if order was confirmed

---

### 2. Customers

**Path:** Customers → Customers

#### Features:
- **List View:**
  - Phone (primary identifier)
  - Name
  - Customer tier (badge)
  - Total orders count

- **Create/Edit:**
  - Phone number (unique per company)
  - Name
  - Customer tier selection
  - Custom fields

- **Actions:**
  - **View Orders** - Quick filter to customer's orders

#### Customer Tiers:
- Tiers are defined per company
- Apply automatic discounts:
  - **Percent:** % off subtotal
  - **Fixed:** ฿ off subtotal

---

### 3. Sale Orders

**Path:** Sales → Sale Orders

#### Workflow:

```
Draft → Confirm → Mark Payment → Approved (Admin)
                       ↓
                   Cancelled
```

#### List View:
- TX ID (auto-generated, sequential)
- Customer name & phone
- Status badge (Draft/Confirmed/Cancelled)
- Payment state (PendingReceipt/Received)
- Payment method
- Grand total

#### Creating an Order:

1. **Select Customer**
   - Search existing or create inline

2. **Add Items**
   - Select product (auto-fills price & UOM)
   - Enter quantity
   - Optional: discount, tax rate
   - Add multiple items with repeater

3. **Save as Draft**
   - TX_ID generated automatically (format: `SO-YYYYMM-0001`)
   - Order status: Draft
   - Stock not yet deducted

#### Order Actions:

**Confirm Order** (Admin/Cashier):
- Changes status: Draft → Confirmed
- Deducts stock via `InventoryService`
- Creates stock movement records
- Cannot be undone (use Cancel instead)

**Mark Payment** (Admin/Cashier):
- **Cash:**
  - Immediate → payment_state: Received
  - No approval needed

- **Bank Transfer:**
  - Upload payment slip (image, max 5MB)
  - Status → PendingReceipt
  - Requires Admin approval

**Cancel** (Admin/Cashier):
- Cancels order
- If confirmed: restores stock
- Requires cancellation reason

#### View Order Page:
- Order details (TX ID, customer, dates)
- Items table with totals
- Payment info
- Actions:
  - **Approve Payment** (Admin, transfer only)
    - View payment slip preview
    - Add approval notes
    - Updates: payment_state → Received
  - **Reject Payment** (Admin)
    - Rejection reason required
    - Cashier must re-upload corrected slip

---

### 4. Stock Movements

**Path:** Inventory → Stock Movements

**Read-Only Audit Trail**

#### Displays:
- Date/time
- Product SKU & name
- Reference type (SALE, ADJUSTMENT, OPENING, RETURN)
- Reference ID (TX_ID for sales)
- Qty In / Qty Out
- Balance after transaction
- Notes

#### Filters:
- Product
- Reference type
- Date range

#### Use Cases:
- Audit stock changes
- Investigate discrepancies
- Track order deductions

---

### 5. Custom Fields

**Path:** Settings → Custom Fields

**Admin Only**

#### Purpose:
Extend Products, Customers, or Sale Orders with company-specific fields.

#### Supported Field Types:
1. **Text** - Short text input
2. **Textarea** - Long text
3. **Number** - Numeric (integer or decimal)
4. **Boolean** - Yes/No toggle
5. **Date** / **Datetime**
6. **Select** - Single choice dropdown
7. **Multiselect** - Multiple choices
8. **Email** - Email validation
9. **URL** - URL validation
10. **Phone** - Phone validation

#### Creating a Custom Field:

1. **Field Definition:**
   - Entity type (Product/Customer/SaleOrder)
   - Field key (e.g., `warranty_months`)
   - Label (display name)
   - Data type

2. **Options:**
   - Required field?
   - Indexed (for performance)?
   - Display order
   - Placeholder text

3. **Validation:**
   - Min/max values
   - Regex pattern
   - Custom rules (key-value)

4. **For Select Fields:**
   - Define options (value → label)

#### Dynamic Rendering:
- Custom fields automatically appear in forms
- Validation applied based on rules
- Stored in `attributes` JSON column

#### JSON Indexing:

For high-performance queries on custom field values:

```bash
php artisan db:execute "
  ALTER TABLE products
  ADD INDEX idx_attr_warranty
  ((CAST(JSON_EXTRACT(attributes, '$.warranty_months') AS UNSIGNED)))
"
```

---

## Payment Workflow

### Overview

FlexStock supports **two payment methods** with different approval flows:

### 1. Cash Payment (Immediate)

```
Confirm Order → Mark Payment (Cash) → payment_state: Received ✅
```

**Process:**
1. Cashier confirms order
2. Selects "Mark Payment"
3. Chooses "Cash"
4. Optional: Add payment notes
5. **Status immediately**: Received

**No approval needed** - instant completion.

### 2. Bank Transfer (With Approval)

```
Confirm Order → Upload Slip → Admin Reviews → Approve/Reject
                                    ↓
                             payment_state: Received ✅
```

**Process:**
1. Cashier confirms order
2. Selects "Mark Payment"
3. Chooses "Bank Transfer"
4. **Uploads payment slip** (JPG, PNG, PDF, max 5MB)
5. Status: PendingReceipt
6. **Admin reviews:**
   - Views slip preview
   - Approves (with notes) → Received
   - Or rejects (with reason) → Cashier re-uploads

**File Storage:**
- Path: `storage/app/public/slips/{company_id}/{tx_id}/`
- Accessible via: `Storage::url($slip->slip_path)`

### Payment Slip Model

**Table:** `payment_slips`

**Fields:**
- `sale_order_id` - FK to order
- `slip_path` - File path
- `status` - Pending/Approved/Rejected
- `uploaded_by` - User ID
- `approved_by` - Admin user ID
- `approved_at` - Timestamp
- `notes` - Combined upload + approval notes

### Approval Actions

**Location:** Sale Orders → View Order (for transfer payments)

**Admin sees:**
- Image preview of payment slip
- Approve button
- Reject button

**On Approval:**
1. Slip status → Approved
2. Order payment_state → Received
3. Records approved_by & approved_at
4. Adds approval notes

**On Rejection:**
1. Slip status → Rejected
2. Order remains PendingReceipt
3. Cashier must upload new slip

---

## Dashboard & Analytics

**Path:** Home (default landing page)

### Widgets:

#### 1. Sales Today (Stats)
- **Today's Revenue**
  - Total from confirmed + received orders
  - Mini trend chart
- **Cash Payments** count
- **Transfer Payments** count

**Data Source:** `ReportService::getSalesSummary()`

#### 2. Top Products (Table)
- **Period:** Last 30 days
- **Displays:**
  - SKU, Name
  - Qty Sold
  - Total Revenue
  - Order Count
- **Sorted by:** Revenue (desc)
- **Limit:** Top 10

**Data Source:** `ReportService::getTopProducts()`

#### 3. Low Stock Alert (Table)
- **Threshold:** Stock ≤ 10 units
- **Displays:**
  - SKU, Name
  - Current stock (color-coded)
  - Price
- **Actions:** Quick link to edit product

**Data Source:** `Product::where('stock_qty', '<=', 10)`

#### 4. Payment Mix (Doughnut Chart)
- **Period:** Last 30 days
- **Breakdown:**
  - Cash (Yellow)
  - Bank Transfer (Blue)
- **Shows:** Revenue amounts

**Data Source:** `ReportService::getSalesSummary()`

### Refreshing Data

- Widgets auto-refresh on page load
- Manual refresh: Reload page (F5)
- Real-time updates: Not implemented (future enhancement)

---

## Troubleshooting

### Cannot Access Admin Panel

**Symptom:** `/admin` redirects to login, even after logging in

**Solutions:**
1. Clear caches:
```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

2. Check user has company membership:
```php
$user->companies()->count(); // Should be > 0
```

3. Verify user has valid role:
```php
$user->companies()->first()->pivot->role; // Should be: admin, cashier, or viewer
```

### Company Switcher Shows No Companies

**Cause:** User not assigned to any active companies

**Solution:**
```bash
php artisan tinker
$user = User::where('email', 'user@example.com')->first();
$company = Company::where('is_active', true)->first();
$user->companies()->attach($company->id, ['role' => 'admin']);
```

### Cannot Confirm Order: "Insufficient Stock"

**Cause:** Product stock is lower than order quantity

**Solutions:**
1. **Check current stock:**
   - Inventory → Products → Find product
   - View "Stock" column

2. **Adjust stock (Admin only):**
   - Click product → Adjust Stock action
   - Enter positive qty_delta
   - Add reason (e.g., "Stock count correction")

3. **View stock history:**
   - Products → View Stock History
   - Check for unexpected deductions

### Payment Slip Upload Fails

**Common Issues:**

1. **File too large:**
   - Max size: 5MB
   - Compress image before upload

2. **Invalid format:**
   - Accepted: JPG, PNG, PDF
   - Convert other formats

3. **Storage permissions:**
```bash
chmod -R 775 storage/
chown -R www-data:www-data storage/
```

4. **Storage link not created:**
```bash
php artisan storage:link
```

### Custom Fields Not Showing

**Checklist:**
1. Field is **Active** (check Settings → Custom Fields)
2. Field `applies_to` matches entity (Product/Customer/SaleOrder)
3. Form is in create/edit mode (not view mode)
4. Clear cache: `php artisan view:clear`

### Dashboard Widgets Show Zero

**Causes:**
1. **No data in selected company**
   - Create test orders and confirm them

2. **Wrong company selected**
   - Check company switcher (Settings)

3. **Payment state filtering**
   - Reports only count orders with `payment_state = Received`
   - Ensure payments are approved (for transfers)

---

## Advanced Topics

### Git Hash in Footer

The admin panel footer displays the current git commit hash:

```bash
git rev-parse --short HEAD
```

**Customization:** Edit `app/Providers/Filament/AdminPanelProvider.php`:

```php
->footer(function () {
    return view('filament.components.footer');
})
```

### Custom Themes

Filament v3 uses Tailwind CSS. To customize:

1. Publish Filament assets:
```bash
php artisan filament:assets
```

2. Edit `resources/css/filament/admin/theme.css`

3. Rebuild:
```bash
npm run build
```

### Performance Optimization

**For large datasets:**

1. **Index Custom Fields:**
```sql
ALTER TABLE products
ADD INDEX idx_attr_fieldname
((CAST(JSON_EXTRACT(attributes, '$.field_name') AS CHAR(255))));
```

2. **Enable Query Caching:**
- Add Redis cache driver
- Cache report results

3. **Paginate Large Tables:**
- Default: 25 records per page
- Adjustable in table settings

---

## API Reference

The admin panel uses the same backend services as the REST API:

- `SaleOrderService` - Order operations
- `InventoryService` - Stock management
- `ReportService` - Analytics
- `CustomFieldRegistry` - Field validation

**For programmatic access, use the REST API:**
See `routes/api.php` and `QUICKSTART.md` for API documentation.

---

## Security Considerations

### Authentication
- Laravel Sanctum with session guards
- CSRF protection enabled
- Password hashing: bcrypt

### Authorization
- Policies for all resources
- Gates for company-level operations
- Middleware-based role checking

### Data Protection
- Tenant isolation via global scopes
- No cross-company data leakage
- SQL injection prevention (Eloquent ORM)
- XSS protection (Blade escaping)

### File Uploads
- Type validation (images only)
- Size limits (5MB)
- Secure storage (outside public root)
- Unique paths per company/order

---

## Support & Resources

- **Laravel Documentation:** https://laravel.com/docs
- **Filament Documentation:** https://filamentphp.com/docs
- **FlexStock API Docs:** See `QUICKSTART.md`
- **Issue Tracker:** GitHub repository issues

---

## Changelog

See `CHANGELOG.md` for version history and updates.

---

**FlexStock Admin Panel v3.0**
Built with ❤️ using Laravel 10 & Filament v3
Documentation generated by Claude Code

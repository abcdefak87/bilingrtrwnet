# Role-Based Access Control (RBAC) Guide

## Overview

The ISP Billing System implements a comprehensive Role-Based Access Control (RBAC) system to manage user permissions and access to different parts of the application.

## Roles

The system supports five distinct roles:

### 1. Super Admin (`super_admin`)
- **Description**: Super Administrator with full system access
- **Access**: Complete control over all system features including user management, settings, and all operational features
- **Use Case**: System owner, primary administrator

### 2. Admin (`admin`)
- **Description**: Administrator with full operational access
- **Access**: Full CRUD operations on customers, services, invoices, payments, tickets, routers, ODP, monitoring, and reports
- **Restrictions**: Cannot manage other users or system settings
- **Use Case**: ISP operations manager

### 3. Technician (`technician`)
- **Description**: Field technician with limited access to assigned tasks
- **Access**: View customers and services, manage assigned tickets, update installation tasks, manage ODP ports
- **Restrictions**: Cannot create/delete records, limited to assigned tasks
- **Use Case**: Field installation and support staff

### 4. Customer (`customer`)
- **Description**: Customer with access to their own data only
- **Access**: View own profile, services, invoices, payments, create and manage own tickets, view available packages
- **Restrictions**: Can only access their own data
- **Use Case**: End-user customers

### 5. Reseller (`reseller`)
- **Description**: Reseller with access to their tenant data
- **Access**: Manage customers, services, invoices, payments, tickets within their tenant, view reports for their tenant
- **Restrictions**: Data isolated by tenant_id, cannot access other tenants' data
- **Use Case**: Sub-ISP operators, franchise partners

## Permission System

### Permission Format

Permissions follow the format: `resource.action`

Examples:
- `customers.view` - View customers
- `customers.create` - Create new customers
- `services.isolate` - Isolate services
- `reports.financial` - Access financial reports

### Permission Categories

1. **User Management**: `users.*`
2. **Customer Management**: `customers.*`
3. **Package Management**: `packages.*`
4. **Service Management**: `services.*`
5. **Invoice Management**: `invoices.*`
6. **Payment Management**: `payments.*`
7. **Ticket Management**: `tickets.*`
8. **Router Management**: `routers.*`
9. **ODP Management**: `odp.*`
10. **Monitoring**: `monitoring.*`
11. **Reports**: `reports.*`
12. **Settings**: `settings.*`
13. **Audit Logs**: `audit_logs.*`
14. **Bulk Operations**: `bulk.*`

## Usage

### In Controllers

#### Using Middleware

```php
// Single permission
Route::get('/customers', [CustomerController::class, 'index'])
    ->middleware('permission:customers.view');

// Multiple permissions (any)
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('permission:customers.view');

// Role-based
Route::get('/admin/users', [UserController::class, 'index'])
    ->middleware('role:super_admin,admin');
```

#### In Controller Methods

```php
public function index()
{
    // Check permission
    if (!auth()->user()->hasPermission('customers.view')) {
        abort(403, 'Unauthorized');
    }
    
    // Check role
    if (!auth()->user()->hasRole('admin')) {
        abort(403, 'Unauthorized');
    }
    
    // Check if admin (super_admin or admin)
    if (!auth()->user()->isAdmin()) {
        abort(403, 'Unauthorized');
    }
}
```

### In Blade Views

#### Using Blade Directives

```blade
{{-- Check permission --}}
@permission('customers.create')
    <a href="{{ route('customers.create') }}" class="btn btn-primary">
        Create Customer
    </a>
@endpermission

{{-- Check role --}}
@role('admin', 'super_admin')
    <div class="admin-panel">
        <!-- Admin content -->
    </div>
@endrole

{{-- Check if super admin --}}
@superadmin
    <a href="{{ route('settings') }}">System Settings</a>
@endsuperadmin

{{-- Check if admin (super_admin or admin) --}}
@admin
    <a href="{{ route('reports') }}">Reports</a>
@endadmin
```

### Using Helper Functions

```php
// Check permission
if (can_access('customers.create')) {
    // User has permission
}

// Check role
if (has_role('admin')) {
    // User has admin role
}

// Check multiple roles
if (has_role(['admin', 'super_admin'])) {
    // User has any of these roles
}

// Check if super admin
if (is_super_admin()) {
    // User is super admin
}

// Check if admin
if (is_admin()) {
    // User is admin or super_admin
}

// Get all user permissions
$permissions = get_user_permissions();
```

### In User Model

```php
$user = auth()->user();

// Check single permission
$user->hasPermission('customers.view');

// Check any permission
$user->hasAnyPermission(['customers.view', 'services.view']);

// Check all permissions
$user->hasAllPermissions(['customers.view', 'customers.create']);

// Check role
$user->hasRole('admin');

// Check any role
$user->hasAnyRole(['admin', 'super_admin']);

// Role helpers
$user->isSuperAdmin();
$user->isAdmin();
$user->isTechnician();
$user->isCustomer();
$user->isReseller();

// Get all permissions
$user->getPermissions();
```

## Configuration

All permissions are defined in `config/permissions.php`. To modify permissions:

1. Open `config/permissions.php`
2. Locate the role you want to modify
3. Add or remove permissions from the `permissions` array
4. Clear config cache: `php artisan config:clear`

### Adding New Permissions

```php
'roles' => [
    'admin' => [
        'permissions' => [
            // Existing permissions...
            'new_resource.view',
            'new_resource.create',
            'new_resource.update',
            'new_resource.delete',
        ],
    ],
],
```

## Testing

### Seeding Test Users

Run the role seeder to create test users:

```bash
php artisan db:seed --class=RoleSeeder
```

This creates:
- Super Admin: `superadmin@ispbilling.test` / `password`
- Admin: `admin@ispbilling.test` / `password`
- Technician: `technician@ispbilling.test` / `password`
- Customer: `customer@ispbilling.test` / `password`
- Reseller: `reseller@ispbilling.test` / `password`

### Testing Permissions

```php
// In tests
public function test_admin_can_view_customers()
{
    $admin = User::factory()->create(['role' => 'admin']);
    
    $this->actingAs($admin)
        ->get('/customers')
        ->assertStatus(200);
}

public function test_customer_cannot_view_all_customers()
{
    $customer = User::factory()->create(['role' => 'customer']);
    
    $this->actingAs($customer)
        ->get('/customers')
        ->assertStatus(403);
}
```

## Multi-Tenancy

For resellers and tenant isolation:

1. **Tenant Assignment**: When a reseller creates a customer, the customer is automatically assigned to the reseller's `tenant_id`
2. **Data Filtering**: All queries for resellers are automatically filtered by `tenant_id`
3. **Cross-Tenant Protection**: Resellers cannot access data from other tenants

### Implementing Tenant Scopes

```php
// In Model
protected static function booted()
{
    static::addGlobalScope('tenant', function (Builder $builder) {
        if (auth()->check() && auth()->user()->isReseller()) {
            $builder->where('tenant_id', auth()->user()->tenant_id);
        }
    });
}
```

## Security Best Practices

1. **Always check permissions** before performing sensitive operations
2. **Use middleware** for route protection
3. **Validate tenant_id** for multi-tenant operations
4. **Log permission denials** for security auditing
5. **Regularly review** role permissions
6. **Test authorization** in all critical paths

## Troubleshooting

### Permission Denied Errors

If you get 403 errors:

1. Check if the user has the required permission:
   ```php
   dd(auth()->user()->getPermissions());
   ```

2. Verify the permission exists in `config/permissions.php`

3. Clear config cache:
   ```bash
   php artisan config:clear
   ```

### Middleware Not Working

1. Ensure middleware is registered in `bootstrap/app.php`
2. Check middleware alias is correct
3. Verify route middleware syntax

### Helper Functions Not Found

1. Ensure `app/Helpers/PermissionHelper.php` is in `composer.json` autoload files
2. Run `composer dump-autoload`
3. Clear application cache: `php artisan cache:clear`

## API Reference

### User Model Methods

| Method | Parameters | Returns | Description |
|--------|-----------|---------|-------------|
| `hasPermission()` | `string $permission` | `bool` | Check if user has a specific permission |
| `hasAnyPermission()` | `array $permissions` | `bool` | Check if user has any of the given permissions |
| `hasAllPermissions()` | `array $permissions` | `bool` | Check if user has all of the given permissions |
| `hasRole()` | `string $role` | `bool` | Check if user has a specific role |
| `hasAnyRole()` | `array $roles` | `bool` | Check if user has any of the given roles |
| `isSuperAdmin()` | - | `bool` | Check if user is super admin |
| `isAdmin()` | - | `bool` | Check if user is admin or super admin |
| `isTechnician()` | - | `bool` | Check if user is technician |
| `isCustomer()` | - | `bool` | Check if user is customer |
| `isReseller()` | - | `bool` | Check if user is reseller |
| `getPermissions()` | - | `array` | Get all permissions for user's role |

### Helper Functions

| Function | Parameters | Returns | Description |
|----------|-----------|---------|-------------|
| `can_access()` | `string $permission` | `bool` | Check if current user has permission |
| `has_role()` | `string\|array $roles` | `bool` | Check if current user has role(s) |
| `is_super_admin()` | - | `bool` | Check if current user is super admin |
| `is_admin()` | - | `bool` | Check if current user is admin |
| `get_user_permissions()` | - | `array` | Get all permissions for current user |

### Blade Directives

| Directive | Parameters | Description |
|-----------|-----------|-------------|
| `@permission` | `string $permission` | Show content if user has permission |
| `@role` | `string ...$roles` | Show content if user has any of the roles |
| `@superadmin` | - | Show content if user is super admin |
| `@admin` | - | Show content if user is admin or super admin |

## Requirements Validation

This RBAC implementation validates the following requirements:

- **Requirement 14.1**: User authentication with role loading
- **Requirement 14.2**: Route protection with permission verification
- **Requirement 14.3**: 403 Forbidden response for unauthorized access
- **Requirement 14.4**: Super Admin user management capabilities
- **Requirement 14.5**: Admin customer management capabilities
- **Requirement 14.6**: Technician access restrictions
- **Requirement 14.7**: Customer data isolation

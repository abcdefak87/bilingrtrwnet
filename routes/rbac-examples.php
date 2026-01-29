<?php

/**
 * RBAC Route Examples
 * 
 * This file contains example route definitions demonstrating how to use
 * the Role-Based Access Control system in the ISP Billing application.
 * 
 * These are examples only - actual routes should be defined in web.php
 */

use Illuminate\Support\Facades\Route;

// ============================================================================
// EXAMPLE 1: Using permission middleware
// ============================================================================

// Single permission check
Route::get('/customers', function () {
    return 'Customer list';
})->middleware('permission:customers.view');

// Multiple routes with same permission
Route::middleware(['auth', 'permission:customers.view'])->group(function () {
    Route::get('/customers', function () {
        return 'Customer list';
    });
    Route::get('/customers/{id}', function ($id) {
        return "Customer $id details";
    });
});

// Different permissions for different actions
Route::middleware('auth')->group(function () {
    Route::get('/customers', function () {
        return 'Customer list';
    })->middleware('permission:customers.view');
    
    Route::post('/customers', function () {
        return 'Create customer';
    })->middleware('permission:customers.create');
    
    Route::put('/customers/{id}', function ($id) {
        return "Update customer $id";
    })->middleware('permission:customers.update');
    
    Route::delete('/customers/{id}', function ($id) {
        return "Delete customer $id";
    })->middleware('permission:customers.delete');
});

// ============================================================================
// EXAMPLE 2: Using role middleware
// ============================================================================

// Single role
Route::get('/admin/dashboard', function () {
    return 'Admin dashboard';
})->middleware('role:admin');

// Multiple roles (user must have ANY of these roles)
Route::get('/admin/settings', function () {
    return 'Settings';
})->middleware('role:super_admin,admin');

// Role-based route groups
Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->group(function () {
    Route::get('/users', function () {
        return 'User management';
    });
    Route::get('/settings', function () {
        return 'System settings';
    });
});

// ============================================================================
// EXAMPLE 3: Combining authentication and authorization
// ============================================================================

Route::middleware(['auth', 'permission:customers.view'])->group(function () {
    Route::get('/customers', function () {
        return 'Customer list';
    });
    Route::get('/customers/{id}', function ($id) {
        return "Customer $id";
    });
});

// ============================================================================
// EXAMPLE 4: Resource routes with permissions
// ============================================================================

// All CRUD operations with appropriate permissions
Route::middleware('auth')->group(function () {
    // Index - requires view permission
    Route::get('/packages', function () {
        return 'Package list';
    })->middleware('permission:packages.view');
    
    // Create - requires create permission
    Route::get('/packages/create', function () {
        return 'Create package form';
    })->middleware('permission:packages.create');
    
    Route::post('/packages', function () {
        return 'Store package';
    })->middleware('permission:packages.create');
    
    // Edit - requires update permission
    Route::get('/packages/{id}/edit', function ($id) {
        return "Edit package $id";
    })->middleware('permission:packages.update');
    
    Route::put('/packages/{id}', function ($id) {
        return "Update package $id";
    })->middleware('permission:packages.update');
    
    // Delete - requires delete permission
    Route::delete('/packages/{id}', function ($id) {
        return "Delete package $id";
    })->middleware('permission:packages.delete');
});

// ============================================================================
// EXAMPLE 5: Customer portal routes (own data only)
// ============================================================================

Route::middleware(['auth', 'role:customer'])->prefix('portal')->group(function () {
    // Customer can only view their own data
    Route::get('/dashboard', function () {
        return 'Customer dashboard';
    })->middleware('permission:profile.view');
    
    Route::get('/services', function () {
        return 'My services';
    })->middleware('permission:services.view_own');
    
    Route::get('/invoices', function () {
        return 'My invoices';
    })->middleware('permission:invoices.view_own');
    
    Route::get('/tickets', function () {
        return 'My tickets';
    })->middleware('permission:tickets.view_own');
    
    Route::post('/tickets', function () {
        return 'Create ticket';
    })->middleware('permission:tickets.create_own');
});

// ============================================================================
// EXAMPLE 6: Technician routes (assigned tasks only)
// ============================================================================

Route::middleware(['auth', 'role:technician'])->prefix('technician')->group(function () {
    Route::get('/dashboard', function () {
        return 'Technician dashboard';
    });
    
    Route::get('/tickets', function () {
        return 'Assigned tickets';
    })->middleware('permission:tickets.view');
    
    Route::put('/tickets/{id}', function ($id) {
        return "Update ticket $id";
    })->middleware('permission:tickets.update');
    
    Route::get('/installations', function () {
        return 'Installation tasks';
    })->middleware('permission:installations.view');
    
    Route::put('/installations/{id}', function ($id) {
        return "Update installation $id";
    })->middleware('permission:installations.update');
});

// ============================================================================
// EXAMPLE 7: Reseller routes (tenant-scoped)
// ============================================================================

Route::middleware(['auth', 'role:reseller'])->prefix('reseller')->group(function () {
    // Reseller can manage their tenant's customers
    Route::get('/customers', function () {
        return 'Tenant customers';
    })->middleware('permission:customers.view');
    
    Route::post('/customers', function () {
        return 'Create tenant customer';
    })->middleware('permission:customers.create');
    
    // Reseller can view their tenant's reports
    Route::get('/reports', function () {
        return 'Tenant reports';
    })->middleware('permission:reports.view');
    
    Route::get('/reports/financial', function () {
        return 'Tenant financial report';
    })->middleware('permission:reports.financial');
});

// ============================================================================
// EXAMPLE 8: Admin routes with different permission levels
// ============================================================================

Route::middleware(['auth', 'role:admin,super_admin'])->prefix('admin')->group(function () {
    // Dashboard - all admins
    Route::get('/dashboard', function () {
        return 'Admin dashboard';
    });
    
    // Customer management - all admins
    Route::resource('customers', 'CustomerController');
    
    // User management - super admin only
    Route::middleware('role:super_admin')->group(function () {
        Route::resource('users', 'UserController');
    });
    
    // Settings - super admin only
    Route::middleware('permission:settings.update')->group(function () {
        Route::get('/settings', function () {
            return 'System settings';
        });
        Route::put('/settings', function () {
            return 'Update settings';
        });
    });
});

// ============================================================================
// EXAMPLE 9: API routes with permission checks
// ============================================================================

Route::middleware(['auth:sanctum'])->prefix('api')->group(function () {
    // Public endpoints (authenticated users only)
    Route::get('/packages', function () {
        return response()->json(['packages' => []]);
    })->middleware('permission:packages.view');
    
    // Admin endpoints
    Route::middleware('role:admin,super_admin')->group(function () {
        Route::get('/customers', function () {
            return response()->json(['customers' => []]);
        })->middleware('permission:customers.view');
        
        Route::post('/customers', function () {
            return response()->json(['message' => 'Customer created']);
        })->middleware('permission:customers.create');
    });
});

// ============================================================================
// EXAMPLE 10: Bulk operations (admin only)
// ============================================================================

Route::middleware(['auth', 'role:admin,super_admin'])->prefix('admin/bulk')->group(function () {
    Route::post('/isolate', function () {
        return 'Bulk isolate services';
    })->middleware('permission:bulk.isolate');
    
    Route::post('/restore', function () {
        return 'Bulk restore services';
    })->middleware('permission:bulk.restore');
    
    Route::post('/notify', function () {
        return 'Bulk send notifications';
    })->middleware('permission:bulk.notify');
});

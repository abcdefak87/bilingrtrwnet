<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Role-Based Permissions Configuration
    |--------------------------------------------------------------------------
    |
    | This file defines all permissions for each role in the ISP Billing System.
    | Permissions are organized by resource and action.
    |
    | Roles: super_admin, admin, technician, customer, reseller
    |
    */

    'roles' => [
        'super_admin' => [
            'description' => 'Super Administrator with full system access',
            'permissions' => [
                // User Management
                'users.view',
                'users.create',
                'users.update',
                'users.delete',
                
                // Customer Management
                'customers.view',
                'customers.create',
                'customers.update',
                'customers.delete',
                'customers.assign_technician',
                'customers.approve_installation',
                
                // Package Management
                'packages.view',
                'packages.create',
                'packages.update',
                'packages.delete',
                
                // Service Management
                'services.view',
                'services.create',
                'services.update',
                'services.delete',
                'services.isolate',
                'services.restore',
                'services.terminate',
                
                // Invoice Management
                'invoices.view',
                'invoices.create',
                'invoices.update',
                'invoices.delete',
                
                // Payment Management
                'payments.view',
                'payments.create',
                'payments.update',
                'payments.delete',
                
                // Ticket Management
                'tickets.view',
                'tickets.create',
                'tickets.update',
                'tickets.delete',
                'tickets.assign',
                
                // Router Management
                'routers.view',
                'routers.create',
                'routers.update',
                'routers.delete',
                'routers.test_connection',
                
                // ODP Management
                'odp.view',
                'odp.create',
                'odp.update',
                'odp.delete',
                'odp.assign_port',
                'odp.release_port',
                
                // Monitoring
                'monitoring.view',
                'monitoring.alerts',
                
                // Reports
                'reports.view',
                'reports.export',
                'reports.financial',
                
                // Settings
                'settings.view',
                'settings.update',
                'settings.payment_gateway',
                'settings.whatsapp_gateway',
                
                // Audit Logs
                'audit_logs.view',
                
                // Bulk Operations
                'bulk.isolate',
                'bulk.restore',
                'bulk.notify',
            ],
        ],

        'admin' => [
            'description' => 'Administrator with full operational access',
            'permissions' => [
                // Customer Management (Full CRUD)
                'customers.view',
                'customers.create',
                'customers.update',
                'customers.delete',
                'customers.assign_technician',
                'customers.approve_installation',
                
                // Package Management
                'packages.view',
                'packages.create',
                'packages.update',
                'packages.delete',
                
                // Service Management
                'services.view',
                'services.create',
                'services.update',
                'services.delete',
                'services.isolate',
                'services.restore',
                'services.terminate',
                
                // Invoice Management
                'invoices.view',
                'invoices.create',
                'invoices.update',
                'invoices.delete',
                
                // Payment Management
                'payments.view',
                'payments.create',
                'payments.update',
                
                // Ticket Management
                'tickets.view',
                'tickets.create',
                'tickets.update',
                'tickets.assign',
                
                // Router Management
                'routers.view',
                'routers.create',
                'routers.update',
                'routers.delete',
                'routers.test_connection',
                
                // ODP Management
                'odp.view',
                'odp.create',
                'odp.update',
                'odp.delete',
                'odp.assign_port',
                'odp.release_port',
                
                // Monitoring
                'monitoring.view',
                'monitoring.alerts',
                
                // Reports
                'reports.view',
                'reports.export',
                'reports.financial',
                
                // Bulk Operations
                'bulk.isolate',
                'bulk.restore',
                'bulk.notify',
            ],
        ],

        'technician' => [
            'description' => 'Field technician with limited access to assigned tasks',
            'permissions' => [
                // Customer Management (View only)
                'customers.view',
                
                // Service Management (View only)
                'services.view',
                
                // Ticket Management (Assigned tickets only)
                'tickets.view',
                'tickets.update',
                
                // ODP Management
                'odp.view',
                'odp.assign_port',
                'odp.release_port',
                
                // Installation Tasks
                'installations.view',
                'installations.update',
            ],
        ],

        'customer' => [
            'description' => 'Customer with access to their own data only',
            'permissions' => [
                // Own Profile
                'profile.view',
                'profile.update',
                
                // Own Services
                'services.view_own',
                
                // Own Invoices
                'invoices.view_own',
                
                // Own Payments
                'payments.view_own',
                
                // Own Tickets
                'tickets.view_own',
                'tickets.create_own',
                'tickets.update_own',
                
                // Packages (View available packages)
                'packages.view',
            ],
        ],

        'reseller' => [
            'description' => 'Reseller with access to their tenant data',
            'permissions' => [
                // Customer Management (Tenant only)
                'customers.view',
                'customers.create',
                'customers.update',
                'customers.assign_technician',
                'customers.approve_installation',
                
                // Package Management (View only)
                'packages.view',
                
                // Service Management (Tenant only)
                'services.view',
                'services.create',
                'services.update',
                'services.isolate',
                'services.restore',
                
                // Invoice Management (Tenant only)
                'invoices.view',
                'invoices.create',
                'invoices.update',
                
                // Payment Management (Tenant only)
                'payments.view',
                
                // Ticket Management (Tenant only)
                'tickets.view',
                'tickets.create',
                'tickets.update',
                'tickets.assign',
                
                // ODP Management (Tenant only)
                'odp.view',
                'odp.create',
                'odp.update',
                'odp.assign_port',
                'odp.release_port',
                
                // Reports (Tenant only)
                'reports.view',
                'reports.export',
                'reports.financial',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Groups
    |--------------------------------------------------------------------------
    |
    | Group permissions by resource for easier management
    |
    */

    'groups' => [
        'users' => ['view', 'create', 'update', 'delete'],
        'customers' => ['view', 'create', 'update', 'delete', 'assign_technician', 'approve_installation'],
        'packages' => ['view', 'create', 'update', 'delete'],
        'services' => ['view', 'create', 'update', 'delete', 'isolate', 'restore', 'terminate', 'view_own'],
        'invoices' => ['view', 'create', 'update', 'delete', 'view_own'],
        'payments' => ['view', 'create', 'update', 'delete', 'view_own'],
        'tickets' => ['view', 'create', 'update', 'delete', 'assign', 'view_own', 'create_own', 'update_own'],
        'routers' => ['view', 'create', 'update', 'delete', 'test_connection'],
        'odp' => ['view', 'create', 'update', 'delete', 'assign_port', 'release_port'],
        'monitoring' => ['view', 'alerts'],
        'reports' => ['view', 'export', 'financial'],
        'settings' => ['view', 'update', 'payment_gateway', 'whatsapp_gateway'],
        'audit_logs' => ['view'],
        'bulk' => ['isolate', 'restore', 'notify'],
        'profile' => ['view', 'update'],
        'installations' => ['view', 'update'],
    ],
];

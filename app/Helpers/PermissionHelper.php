<?php

if (!function_exists('can_access')) {
    /**
     * Check if the current user has a specific permission.
     *
     * @param string $permission
     * @return bool
     */
    function can_access(string $permission): bool
    {
        return auth()->check() && auth()->user()->hasPermission($permission);
    }
}

if (!function_exists('has_role')) {
    /**
     * Check if the current user has a specific role.
     *
     * @param string|array $roles
     * @return bool
     */
    function has_role(string|array $roles): bool
    {
        if (!auth()->check()) {
            return false;
        }

        if (is_array($roles)) {
            return auth()->user()->hasAnyRole($roles);
        }

        return auth()->user()->hasRole($roles);
    }
}

if (!function_exists('is_super_admin')) {
    /**
     * Check if the current user is a super admin.
     *
     * @return bool
     */
    function is_super_admin(): bool
    {
        return auth()->check() && auth()->user()->isSuperAdmin();
    }
}

if (!function_exists('is_admin')) {
    /**
     * Check if the current user is an admin (super_admin or admin).
     *
     * @return bool
     */
    function is_admin(): bool
    {
        return auth()->check() && auth()->user()->isAdmin();
    }
}

if (!function_exists('get_user_permissions')) {
    /**
     * Get all permissions for the current user.
     *
     * @return array
     */
    function get_user_permissions(): array
    {
        if (!auth()->check()) {
            return [];
        }

        return auth()->user()->getPermissions();
    }
}

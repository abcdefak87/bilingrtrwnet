<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Global scope for multi-tenancy support.
 * 
 * Automatically filters queries to only show data for the current user's tenant.
 * Super admins can see all data across all tenants.
 * 
 * Requirements: 18.2, 18.6
 */
class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Only apply tenant filtering if user is authenticated
        if (!Auth::check()) {
            return;
        }

        $user = Auth::user();

        // Super admins can see all data across all tenants
        if ($user->isSuperAdmin()) {
            return;
        }

        // For resellers and other roles, filter by tenant_id
        if ($user->tenant_id !== null) {
            $builder->where($model->getTable() . '.tenant_id', $user->tenant_id);
        }
    }
}

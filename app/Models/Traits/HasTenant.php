<?php

namespace App\Models\Traits;

use App\Models\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;

/**
 * Trait for models that support multi-tenancy.
 * 
 * Automatically applies tenant filtering and assignment.
 * 
 * Requirements: 18.1, 18.2, 18.3
 */
trait HasTenant
{
    /**
     * Boot the HasTenant trait for a model.
     */
    protected static function bootHasTenant(): void
    {
        // Apply global scope for tenant filtering
        static::addGlobalScope(new TenantScope());

        // Automatically assign tenant_id when creating new records
        static::creating(function ($model) {
            if (Auth::check() && Auth::user()->tenant_id !== null) {
                // Only set tenant_id if not already set
                if ($model->tenant_id === null) {
                    $model->tenant_id = Auth::user()->tenant_id;
                }
            }
        });
    }

    /**
     * Get the tenant ID for this model.
     */
    public function getTenantId(): ?int
    {
        return $this->tenant_id;
    }

    /**
     * Set the tenant ID for this model.
     */
    public function setTenantId(?int $tenantId): self
    {
        $this->tenant_id = $tenantId;
        return $this;
    }

    /**
     * Check if this model belongs to a specific tenant.
     */
    public function belongsToTenant(int $tenantId): bool
    {
        return $this->tenant_id === $tenantId;
    }

    /**
     * Scope a query to exclude tenant filtering.
     * Use with caution - only for super admin operations.
     */
    public function scopeWithoutTenantScope($query)
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Scope a query to a specific tenant.
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}

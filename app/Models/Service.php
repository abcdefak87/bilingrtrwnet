<?php

namespace App\Models;

use App\Models\Traits\HasTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Crypt;

class Service extends Model
{
    use HasFactory, HasTenant;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
        'package_id',
        'mikrotik_id',
        'tenant_id',
        'username_pppoe',
        'password_encrypted',
        'ip_address',
        'mikrotik_user_id',
        'status',
        'activation_date',
        'expiry_date',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password_encrypted',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'activation_date' => 'date',
        'expiry_date' => 'date',
    ];

    /**
     * Get the customer that owns the service.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the package associated with the service.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Get the Mikrotik router that provisions this service.
     */
    public function mikrotikRouter(): BelongsTo
    {
        return $this->belongsTo(MikrotikRouter::class, 'mikrotik_id');
    }

    /**
     * Get the invoices for the service.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the ODP port assigned to this service.
     */
    public function odpPort(): HasOne
    {
        return $this->hasOne(OdpPort::class);
    }

    /**
     * Interact with the service's PPPoE password.
     */
    protected function passwordEncrypted(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Crypt::decryptString($value) : null,
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }
}

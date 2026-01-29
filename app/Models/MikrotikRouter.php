<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class MikrotikRouter extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mikrotik_routers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'ip_address',
        'username',
        'password_encrypted',
        'api_port',
        'snmp_community',
        'is_active',
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
        'api_port' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the services provisioned on this router.
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'mikrotik_id');
    }

    /**
     * Get the monitoring data for this router.
     */
    public function deviceMonitoring(): HasMany
    {
        return $this->hasMany(DeviceMonitoring::class, 'router_id');
    }

    /**
     * Interact with the router's password.
     */
    protected function passwordEncrypted(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Crypt::decryptString($value) : null,
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdpPort extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'odp_id',
        'port_number',
        'service_id',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'port_number' => 'integer',
    ];

    /**
     * Get the ODP that owns the port.
     */
    public function odp(): BelongsTo
    {
        return $this->belongsTo(Odp::class);
    }

    /**
     * Get the service assigned to this port.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}

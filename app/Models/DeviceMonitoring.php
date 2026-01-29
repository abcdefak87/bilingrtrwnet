<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceMonitoring extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'device_monitoring';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'router_id',
        'cpu_usage',
        'temperature',
        'uptime',
        'traffic_in',
        'traffic_out',
        'recorded_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'cpu_usage' => 'float',
        'temperature' => 'float',
        'uptime' => 'integer',
        'traffic_in' => 'integer',
        'traffic_out' => 'integer',
        'recorded_at' => 'datetime',
    ];

    /**
     * Get the router that owns the monitoring data.
     */
    public function router(): BelongsTo
    {
        return $this->belongsTo(MikrotikRouter::class, 'router_id');
    }
}

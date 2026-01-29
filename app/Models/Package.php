<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'speed',
        'price',
        'type',
        'fup_threshold',
        'fup_speed',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'fup_threshold' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the services using this package.
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}

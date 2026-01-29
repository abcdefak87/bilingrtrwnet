<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('device_monitoring', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('mikrotik_routers')->onDelete('cascade');
            $table->float('cpu_usage')->nullable(); // Percentage
            $table->float('temperature')->nullable(); // Celsius
            $table->unsignedBigInteger('uptime')->nullable(); // Seconds
            $table->unsignedBigInteger('traffic_in')->nullable(); // Bytes
            $table->unsignedBigInteger('traffic_out')->nullable(); // Bytes
            $table->timestamp('recorded_at');
            
            // Indexes
            $table->index('router_id');
            $table->index('recorded_at');
            $table->index(['router_id', 'recorded_at']); // Composite index for time-series queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_monitoring');
    }
};

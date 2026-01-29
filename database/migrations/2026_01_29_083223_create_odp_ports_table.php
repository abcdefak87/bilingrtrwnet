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
        Schema::create('odp_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('odp_id')->constrained('odp')->onDelete('cascade');
            $table->integer('port_number'); // 1-16
            $table->foreignId('service_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('status', ['available', 'occupied', 'damaged', 'reserved'])->default('available');
            $table->timestamps();
            
            // Indexes
            $table->index('odp_id');
            $table->index('service_id');
            $table->index('status');
            $table->unique(['odp_id', 'port_number']); // Ensure unique port numbers per ODP
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('odp_ports');
    }
};

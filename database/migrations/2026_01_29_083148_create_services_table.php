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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('package_id')->constrained()->onDelete('restrict');
            $table->foreignId('mikrotik_id')->constrained('mikrotik_routers')->onDelete('restrict');
            $table->string('username_pppoe')->unique();
            $table->text('password_encrypted'); // Encrypted PPPoE password
            $table->string('ip_address', 45)->nullable();
            $table->string('mikrotik_user_id')->nullable(); // ID from Mikrotik API
            $table->enum('status', [
                'pending',
                'active',
                'isolated',
                'suspended',
                'terminated',
                'provisioning_failed'
            ])->default('pending');
            $table->date('activation_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('customer_id');
            $table->index('package_id');
            $table->index('mikrotik_id');
            $table->index('status');
            $table->index('expiry_date');
            $table->index('username_pppoe');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};

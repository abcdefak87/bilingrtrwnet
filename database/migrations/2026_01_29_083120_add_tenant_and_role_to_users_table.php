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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['super_admin', 'admin', 'technician', 'customer', 'reseller'])
                ->default('customer')
                ->after('password');
            $table->unsignedBigInteger('tenant_id')->nullable()->after('role');
            
            // Add index for tenant_id for multi-tenancy queries
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn(['role', 'tenant_id']);
        });
    }
};

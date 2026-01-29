<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add tenant_id to tables that need multi-tenancy support.
     * Services, invoices, and tickets inherit tenant_id from their parent customer.
     */
    public function up(): void
    {
        // Add tenant_id to services table
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('mikrotik_id');
            $table->index('tenant_id');
        });

        // Add tenant_id to invoices table
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('service_id');
            $table->index('tenant_id');
        });

        // Add tenant_id to tickets table
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('customer_id');
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};

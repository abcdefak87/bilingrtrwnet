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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 20);
            $table->text('address');
            $table->string('ktp_number', 20)->unique();
            $table->string('ktp_path')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->enum('status', [
                'pending_survey',
                'survey_scheduled',
                'survey_complete',
                'approved',
                'active',
                'suspended',
                'terminated'
            ])->default('pending_survey');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('phone');
            $table->index('ktp_number');
            $table->index('tenant_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};

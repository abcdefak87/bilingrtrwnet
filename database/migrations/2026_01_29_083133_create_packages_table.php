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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('speed'); // e.g., "10 Mbps", "50 Mbps"
            $table->decimal('price', 10, 2);
            $table->enum('type', ['unlimited', 'fup', 'quota'])->default('unlimited');
            $table->integer('fup_threshold')->nullable(); // in GB
            $table->string('fup_speed')->nullable(); // speed after FUP, e.g., "1 Mbps"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexes
            $table->index('is_active');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};

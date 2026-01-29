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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->string('payment_gateway'); // midtrans, xendit, tripay
            $table->string('transaction_id')->unique();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'success', 'failed', 'expired'])->default('pending');
            $table->json('metadata')->nullable(); // Store additional payment gateway data
            $table->timestamps();
            
            // Indexes
            $table->index('invoice_id');
            $table->index('transaction_id');
            $table->index('payment_gateway');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

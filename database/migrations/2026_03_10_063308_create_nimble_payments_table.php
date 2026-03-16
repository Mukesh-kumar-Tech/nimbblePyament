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
        Schema::create('nimble_payments', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('mobile_number');
            $table->decimal('amount', 10, 2);
            $table->string('invoice_id')->unique();
            $table->string('description')->nullable();
            $table->string('currency')->default('INR');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nimble_payments');
    }
};

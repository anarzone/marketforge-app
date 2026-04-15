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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sub_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity');
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('subtotal_minor');
            $table->string('currency', 3)->default('EUR');
            $table->timestamps();

            $table->index('seller_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};

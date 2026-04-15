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
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity')->default(1);
            // Price snapshot — frozen at add-to-cart time, not a live reference.
            // If the seller changes the price later, the cart keeps the old price.
            $table->bigInteger('unit_price_minor');
            $table->string('currency', 3)->default('EUR');
            $table->timestamps();

            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->bigInteger('price_minor');
            $table->string('currency', 3)->default('EUR');
            $table->string('status')->default('draft'); // draft, active, archived
            $table->jsonb('attributes')->nullable();
            $table->timestamps();

            // seller_id is NOT a foreign key — it references users.id but
            // crosses module boundaries. Consistency via domain logic, not DB constraints.
            $table->index('seller_id');

            // Composite index: most product queries filter by status first
            // (WHERE status = 'active'), then optionally by seller.
            // Column order matters — put the most selective filter first.
            $table->index(['status', 'seller_id']);

            // GIN index on JSONB for flexible attribute queries like
            // WHERE attributes @> '{"color": "red"}'
            // Laravel's Blueprint doesn't support GIN directly, so raw SQL.
        });

        // GIN index for JSONB attribute queries — Postgres-specific.
        DB::statement('CREATE INDEX products_attributes_gin ON products USING GIN (attributes jsonb_path_ops)');

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

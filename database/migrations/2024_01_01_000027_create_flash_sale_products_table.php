<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flash_sale_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flash_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('discount_type');
            $table->decimal('discount_value', 12, 2);
            $table->unsignedInteger('stock');
            $table->timestamps();

            $table->unique(['flash_sale_id', 'product_id', 'variant_id']);
            $table->index(['flash_sale_id', 'stock']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sale_products');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('shop_name');
            $table->string('shop_slug')->unique();
            $table->string('logo')->nullable();
            $table->string('banner')->nullable();
            $table->text('description')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('district')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(10.00);
            $table->decimal('balance', 14, 2)->default(0);
            $table->decimal('total_sales', 14, 2)->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->boolean('is_verified')->default(false);
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['status', 'is_verified']);
            $table->index('district');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};

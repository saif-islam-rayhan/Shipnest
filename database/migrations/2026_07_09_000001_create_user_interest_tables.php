<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_interest_events', function (Blueprint $table) {
            $table->id();
            $table->string('subject_key')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('search_query')->nullable();
            $table->unsignedTinyInteger('weight')->default(1);
            $table->timestamps();

            $table->index(['subject_key', 'created_at']);
        });

        Schema::create('user_interest_scores', function (Blueprint $table) {
            $table->id();
            $table->string('subject_key')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('interest_type');
            $table->unsignedBigInteger('interest_id');
            $table->unsignedInteger('score')->default(0);
            $table->timestamps();

            $table->unique(['subject_key', 'interest_type', 'interest_id'], 'uis_subject_type_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_interest_scores');
        Schema::dropIfExists('user_interest_events');
    }
};

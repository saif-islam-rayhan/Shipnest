<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('nid_number')->nullable()->after('district');
            $table->string('trade_license')->nullable()->after('nid_number');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('tags');
            $table->text('meta_description')->nullable()->after('meta_title');
        });

        Schema::table('returns', function (Blueprint $table) {
            $table->text('merchant_note')->nullable()->after('status');
        });

        Schema::create('merchant_withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('method');
            $table->string('account_number');
            $table->string('status')->default('pending');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_withdrawal_requests');

        Schema::table('returns', function (Blueprint $table) {
            $table->dropColumn('merchant_note');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description']);
        });

        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn(['nid_number', 'trade_license']);
        });
    }
};

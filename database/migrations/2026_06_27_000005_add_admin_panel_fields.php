<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('google2fa_secret')->nullable()->after('remember_token');
            $table->boolean('google2fa_enabled')->default(false)->after('google2fa_secret');
        });

        Schema::table('merchants', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('status');
            $table->timestamp('rejected_at')->nullable()->after('rejection_reason');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('approval_status')->default('approved')->after('status');
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->timestamp('starts_at')->nullable()->after('sort_order');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
        });

        Schema::create('order_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason');
            $table->text('description')->nullable();
            $table->string('status')->default('open');
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('method')->nullable();
            $table->string('status')->default('pending');
            $table->text('note')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('order_disputes');

        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['starts_at', 'ends_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('approval_status');
        });

        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn(['rejection_reason', 'rejected_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google2fa_secret', 'google2fa_enabled']);
        });
    }
};

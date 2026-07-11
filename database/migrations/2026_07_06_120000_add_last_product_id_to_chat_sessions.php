<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->foreignId('last_product_id')->nullable()->after('draft_product')->constrained('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_product_id');
        });
    }
};

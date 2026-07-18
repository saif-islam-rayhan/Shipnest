<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->string('sentiment', 20)->nullable()->after('status');
            $table->text('agent_summary')->nullable()->after('sentiment');
            $table->timestamp('agent_analyzed_at')->nullable()->after('agent_summary');
        });
    }

    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropColumn(['sentiment', 'agent_summary', 'agent_analyzed_at']);
        });
    }
};

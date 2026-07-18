<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('barcode', 100)->nullable()->after('sku');
            $table->unique('barcode');
        });

        // Backfill: use SKU as barcode when empty so POS scanning works immediately
        DB::table('product_variants')
            ->whereNull('barcode')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $barcode = $row->sku;
                    $exists = DB::table('product_variants')
                        ->where('barcode', $barcode)
                        ->where('id', '!=', $row->id)
                        ->exists();

                    if ($exists) {
                        $barcode = $row->sku.'-'.$row->id;
                    }

                    DB::table('product_variants')
                        ->where('id', $row->id)
                        ->update(['barcode' => $barcode]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique(['barcode']);
            $table->dropColumn('barcode');
        });
    }
};

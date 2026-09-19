<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_orders', function (Blueprint $table) {
            $table->string('type')->default('pre_order');
            $table->foreignId('product_item_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pre_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_item_id');
            $table->dropColumn('type');
        });
    }
};

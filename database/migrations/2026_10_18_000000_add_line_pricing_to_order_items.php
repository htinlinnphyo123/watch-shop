<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('price_source', 10)->nullable();
            $table->decimal('shop_price', 15, 2)->nullable();
            $table->decimal('web_price', 15, 2)->nullable();
            $table->decimal('discount_percentage', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['price_source', 'shop_price', 'web_price', 'discount_percentage']);
        });
    }
};

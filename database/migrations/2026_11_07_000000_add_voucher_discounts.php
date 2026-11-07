<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('overall_discount_percentage', 5, 2)->default(0);
            $table->decimal('overall_discount_amount', 15, 2)->default(0);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('overall_discount_amount', 15, 2)->nullable();
            $table->decimal('rounding_adjustment', 15, 2)->nullable();
            $table->decimal('net_total', 15, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn(['overall_discount_amount', 'rounding_adjustment', 'net_total']));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['overall_discount_percentage', 'overall_discount_amount']));
    }
};

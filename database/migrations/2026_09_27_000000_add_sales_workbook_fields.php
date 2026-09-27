<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('gender', 30)->nullable();
        });
        foreach (['orders', 'pre_orders'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->date('order_date')->nullable();
                $table->string('buying_type', 30)->nullable();
                $table->string('delivery_type')->nullable();
                $table->string('delivery_status')->nullable();
                $table->decimal('delivery_fees', 15, 2)->nullable();
            });
        }
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_type', 30)->nullable();
        });
        Schema::table('pre_orders', function (Blueprint $table) {
            $table->string('paid_by')->nullable();
            $table->string('payment_type', 30)->nullable();
            $table->decimal('deposit_amount', 15, 2)->nullable();
            $table->string('model_number')->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->decimal('discount_amount', 15, 2)->nullable();
            $table->string('delivery_code')->nullable();
            $table->decimal('money_transfer_amount', 15, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('gender'));
        foreach (['orders', 'pre_orders'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['order_date', 'buying_type', 'delivery_type', 'delivery_status', 'delivery_fees']));
        }
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('order_type'));
        Schema::table('pre_orders', fn (Blueprint $table) => $table->dropColumn(['model_number', 'price', 'discount_amount', 'delivery_code', 'money_transfer_amount', 'paid_by', 'payment_type', 'deposit_amount']));
    }
};

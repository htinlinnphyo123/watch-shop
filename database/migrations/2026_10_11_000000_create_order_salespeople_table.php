<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_salespeople', function (Blueprint $table) {
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->primary(['order_id', 'user_id']);
            $table->index(['user_id', 'order_id']);
        });

        // Preserve the salesperson previously represented by the order creator.
        DB::table('orders')->whereNotNull('user_id')->orderBy('id')->chunkById(500, function ($orders) {
            DB::table('order_salespeople')->insert($orders->map(fn ($order) => [
                'order_id' => $order->id, 'user_id' => $order->user_id,
                'created_at' => $order->created_at, 'updated_at' => $order->updated_at,
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_salespeople');
    }
};

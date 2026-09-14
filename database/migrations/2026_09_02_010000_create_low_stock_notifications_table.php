<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('low_stock_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('stock_quantity');
            $table->unsignedTinyInteger('priority_level');
            $table->enum('status', ['pending', 'processing', 'completed'])->default('pending');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'resolved_at']);
        });

        $now = now();
        $lowStockProducts = DB::table('products')
            ->leftJoin('product_items', function ($join) {
                $join->on('product_items.product_id', '=', 'products.id')
                    ->where('product_items.status', 'available')
                    ->whereNull('product_items.deleted_at');
            })
            ->whereNull('products.deleted_at')
            ->whereIn('products.priority_level', [2, 3])
            ->groupBy('products.id', 'products.priority_level')
            ->select([
                'products.id as product_id',
                'products.priority_level',
                DB::raw('COUNT(product_items.id) as stock_quantity'),
            ])
            ->havingRaw('COUNT(product_items.id) < 2')
            ->get();

        foreach ($lowStockProducts as $product) {
            DB::table('low_stock_notifications')->insert([
                'product_id' => $product->product_id,
                'stock_quantity' => $product->stock_quantity,
                'priority_level' => $product->priority_level,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('low_stock_notifications');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_priority_level_check');
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_orders', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending');
        });
    }

    public function down(): void
    {
        Schema::table('pre_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn('status');
        });
    }
};

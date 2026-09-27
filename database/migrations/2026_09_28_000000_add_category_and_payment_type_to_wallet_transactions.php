<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->string('category', 50)->nullable();
            $table->string('payment_type', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', fn (Blueprint $table) => $table->dropColumn(['category', 'payment_type']));
    }
};

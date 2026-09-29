<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('checkout_request_id')->nullable();
            $table->string('checkout_payload_hash', 64)->nullable();
            $table->unique(['user_id', 'checkout_request_id']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'checkout_request_id']);
            $table->dropColumn(['checkout_request_id', 'checkout_payload_hash']);
        });
    }
};

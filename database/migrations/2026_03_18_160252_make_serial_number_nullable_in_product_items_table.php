<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_items', function (Blueprint $table) {
            // Make serial_number optional — system_unique_id is now the primary identifier
            $table->string('serial_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('product_items', function (Blueprint $table) {
            $table->string('serial_number')->nullable(false)->change();
        });
    }
};

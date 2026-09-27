<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['orders', 'pre_orders'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('marketing_channel')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['orders', 'pre_orders'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('marketing_channel'));
        }
    }
};

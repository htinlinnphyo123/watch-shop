<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('watch_type')->nullable();
            $table->string('glass')->nullable();
            $table->string('water_resistant')->nullable();
            $table->string('shape')->nullable();
            $table->string('couple')->nullable();
            $table->string('dial_size')->nullable();
            $table->string('dial_color')->nullable();
            $table->string('band')->nullable();
            $table->string('band_size')->nullable();
            $table->string('band_color')->nullable();
            $table->string('movement')->nullable();
            $table->string('gender')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'watch_type', 'glass', 'water_resistant', 'shape', 'couple',
                'dial_size', 'dial_color', 'band', 'band_size', 'band_color',
                'movement', 'gender'
            ]);
        });
    }
};

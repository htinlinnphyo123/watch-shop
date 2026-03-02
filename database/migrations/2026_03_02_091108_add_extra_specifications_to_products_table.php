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
            $table->string('case_thickness')->nullable();
            $table->string('case_material')->nullable();
            $table->string('case_color')->nullable();
            $table->string('case_finish')->nullable();
            $table->string('dial_markings')->nullable();
            $table->string('lug_width')->nullable();
            $table->string('strap_buckle')->nullable();
            $table->string('battery_type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'case_thickness',
                'case_material',
                'case_color',
                'case_finish',
                'dial_markings',
                'lug_width',
                'strap_buckle',
                'battery_type'
            ]);
        });
    }
};

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
            // Drop unwanted columns
            $table->dropColumn([
                'watch_type',
                'dial_markings',
                'lug_width',
                'case_material',
                'case_color',
                'case_thickness',
                'case_finish',
                'battery_type',
                'couple',
            ]);

            // Rename columns to match client spec
            $table->renameColumn('glass', 'crystal');
            $table->renameColumn('band', 'strap_material');
            $table->renameColumn('band_size', 'strap_size');
            $table->renameColumn('band_color', 'strap_color');
            $table->renameColumn('strap_buckle', 'clasp_type');
            $table->renameColumn('shape', 'case_shape');

            // Add new required columns
            $table->string('strap_style')->nullable();
            $table->string('quick_release')->nullable();
            $table->string('origin')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Reverse new columns
            $table->dropColumn(['strap_style', 'quick_release', 'origin']);

            // Reverse renamed columns
            $table->renameColumn('crystal', 'glass');
            $table->renameColumn('strap_material', 'band');
            $table->renameColumn('strap_size', 'band_size');
            $table->renameColumn('strap_color', 'band_color');
            $table->renameColumn('clasp_type', 'strap_buckle');
            $table->renameColumn('case_shape', 'shape');

            // Restore dropped columns
            $table->string('watch_type')->nullable();
            $table->string('dial_markings')->nullable();
            $table->string('lug_width')->nullable();
            $table->string('case_material')->nullable();
            $table->string('case_color')->nullable();
            $table->string('case_thickness')->nullable();
            $table->string('case_finish')->nullable();
            $table->string('battery_type')->nullable();
            $table->string('couple')->nullable();
        });
    }
};

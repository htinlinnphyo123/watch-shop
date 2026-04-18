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
            $table->boolean('is_latest')->default(false)->after('is_active');
            $table->string('preview_photo')->nullable()->after('is_latest');
            $table->string('preview_bg_photo')->nullable()->after('preview_photo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_latest', 'preview_photo', 'preview_bg_photo']);
        });
    }
};

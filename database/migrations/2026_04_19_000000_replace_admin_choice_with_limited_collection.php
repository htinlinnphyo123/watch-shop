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
            $table->dropColumn(['is_admin_choice', 'special_discount']);
            $table->boolean('is_limited_collection')->default(false)->after('is_banner');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_limited_collection');
            $table->boolean('is_admin_choice')->default(false)->after('is_banner');
            $table->boolean('special_discount')->default(false)->after('is_admin_choice');
        });
    }
};

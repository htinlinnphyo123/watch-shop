<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('caliber_code')->nullable()->after('crystal');
            $table->string('caseback_design')->nullable()->after('caliber_code');
            $table->decimal('web_price', 15, 2)->nullable()->after('price');
            $table->decimal('discount', 5, 2)->nullable()->after('web_price')->comment('Discount percentage 0-100');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['caliber_code', 'caseback_design', 'web_price', 'discount']);
        });
    }
};

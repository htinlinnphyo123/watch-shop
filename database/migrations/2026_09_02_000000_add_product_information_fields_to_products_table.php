<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('youtube_link')->nullable();
            $table->string('case_material')->nullable();
            $table->unsignedInteger('priority_level')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['youtube_link', 'case_material', 'priority_level']);
        });
    }
};

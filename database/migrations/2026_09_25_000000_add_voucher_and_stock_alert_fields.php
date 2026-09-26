<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('description');
            $table->string('attachment_name')->nullable()->after('attachment_path');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('ordered_stock_count')->default(0)->after('priority_level');
            $table->unsignedInteger('low_stock_alert_count')->default(2)->after('ordered_stock_count');
            $table->text('remark')->nullable()->after('low_stock_alert_count');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['ordered_stock_count', 'low_stock_alert_count', 'remark']);
        });
    }
};

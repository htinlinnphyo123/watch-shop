<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Never delete or merge existing customer records automatically.
        $duplicates = DB::table('customers')->whereNotNull('phone')
            ->select('phone')->groupBy('phone')->havingRaw('COUNT(*) > 1')->exists();

        if ($duplicates) {
            throw new RuntimeException('Duplicate customer phone numbers exist (including archived customers). Resolve them before rerunning this migration. No customer records were changed.');
        }

        Schema::table('customers', fn (Blueprint $table) => $table->unique('phone'));
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->dropUnique(['phone']));
    }
};

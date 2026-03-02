<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE product_items DROP CONSTRAINT IF EXISTS product_items_status_check');
            DB::statement("ALTER TABLE product_items ADD CONSTRAINT product_items_status_check CHECK (status::text = ANY (ARRAY['available'::character varying, 'sold'::character varying, 'reserved'::character varying, 'returned'::character varying, 'lost'::character varying, 'damaged'::character varying]::text[]))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE product_items DROP CONSTRAINT IF EXISTS product_items_status_check');
            DB::statement("ALTER TABLE product_items ADD CONSTRAINT product_items_status_check CHECK (status::text = ANY (ARRAY['available'::character varying, 'sold'::character varying, 'reserved'::character varying]::text[]))");
        }
    }
};

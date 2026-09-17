<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function __construct()
    {
        // SQLite must disable foreign keys while rebuilding products to change
        // brand nullability; that pragma cannot take effect in a transaction.
        $this->withinTransaction = DB::connection()->getDriverName() !== 'sqlite';
    }

    public function up(): void
    {
        Schema::create('accessory_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->json('fields');
            $table->timestamps();
        });
        foreach (['Watch straps' => ['Size', 'Material', 'Color', 'Clasp type'], 'Watch boxes' => ['Dimensions', 'Capacity', 'Material', 'Color']] as $name => $fields) {
            DB::table('accessory_types')->insert(['name' => $name, 'fields' => json_encode($fields), 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::table('products', function (Blueprint $table) {
            $table->string('kind')->default('watch')->index();
            $table->foreignId('accessory_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('accessory_attributes')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['accessory_type_id']);
            $table->dropIndex(['kind']);
            $table->dropColumn(['kind', 'accessory_type_id', 'accessory_attributes']);
        });
        Schema::dropIfExists('accessory_types');
        // Keep brand nullable: accessory records may still have no brand.
    }
};

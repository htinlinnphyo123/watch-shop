<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watch_services', function (Blueprint $table) {
            $table->unsignedBigInteger('product_item_id')->nullable()->change();
            $table->string('watch_origin')->default('inventory');
            $table->string('service_type')->default('warranty');
            $table->string('fault_type')->default('unknown');
            $table->text('diagnosis')->nullable();
            $table->string('repair_provider')->default('in_house');
            $table->string('external_shop_name')->nullable();
            $table->string('external_shop_phone', 50)->nullable();
            $table->string('external_reference')->nullable();
            $table->text('provider_notes')->nullable();
            $table->string('billing_status')->default('pending');
            $table->decimal('customer_charge', 14, 2)->nullable();
            $table->text('charge_notes')->nullable();
        });
        Schema::table('watch_service_events', fn (Blueprint $table) => $table->json('repair_details')->nullable());
        Schema::create('watch_service_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('watch_service_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_id')->unique();
            $table->decimal('amount', 14, 2);
            $table->date('expense_date');
            $table->string('description', 1000);
            $table->string('paid_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('watch_services')->whereNull('product_item_id')->exists()) {
            throw new \RuntimeException('External watch repairs exist. Preserve these records before rolling back repair support.');
        }
        Schema::dropIfExists('watch_service_expenses');
        Schema::table('watch_service_events', fn (Blueprint $table) => $table->dropColumn('repair_details'));
        Schema::table('watch_services', function (Blueprint $table) {
            $table->unsignedBigInteger('product_item_id')->nullable(false)->change();
            $table->dropColumn(['watch_origin', 'service_type', 'fault_type', 'diagnosis', 'repair_provider', 'external_shop_name', 'external_shop_phone', 'external_reference', 'provider_notes', 'billing_status', 'customer_charge', 'charge_notes']);
        });
    }
};

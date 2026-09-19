<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watch_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contact_name');
            $table->string('contact_phone', 50)->nullable();
            $table->date('issue_date');
            $table->text('issue_description');
            $table->string('status')->default('received')->index();
            $table->string('coverage_decision')->default('pending');
            $table->text('decision_notes')->nullable();
            $table->json('warranty_snapshot');
            $table->timestamps();
            $table->index(['product_item_id', 'created_at']);
        });
        Schema::create('watch_service_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('watch_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('status');
            $table->string('coverage_decision');
            $table->text('decision_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_service_events');
        Schema::dropIfExists('watch_services');
    }
};

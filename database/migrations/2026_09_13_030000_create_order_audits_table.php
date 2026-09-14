<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('event');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_type');
            $table->string('actor_name');
            $table->json('snapshot');
            $table->json('changes');
            $table->timestamp('created_at');
            $table->unique(['order_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_audits');
    }
};

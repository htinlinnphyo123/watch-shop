<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            // The staff member whose attendance is being recorded
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Who recorded this entry (admin / manager)
            $table->foreignId('recorded_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->date('attendance_date');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();

            $table->enum('status', [
                'present',
                'absent',
                'late',
                'half_day',
                'on_leave',
            ])->default('present');

            $table->text('remarks')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // One record per employee per day
            $table->unique(['user_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};

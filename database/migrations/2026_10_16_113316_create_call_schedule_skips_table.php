<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('call_schedule_skips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('call_schedules')->cascadeOnDelete();
            $table->date('occurrence_date');
            $table->timestamps();

            $table->unique(['schedule_id', 'occurrence_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_schedule_skips');
    }
};

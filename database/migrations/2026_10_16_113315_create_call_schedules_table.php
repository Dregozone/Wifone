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
        Schema::create('call_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('callee_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->string('timezone');
            $table->string('repeat');
            $table->json('weekdays')->nullable();
            $table->date('until')->nullable();
            $table->string('status');
            $table->timestamps();

            $table->index(['callee_id', 'status']);
            $table->index(['caller_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_schedules');
    }
};

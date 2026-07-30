<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('action', 100); // e.g. "production.corrected"
            $table->string('subject_type', 100); // e.g. "DailyProduction"
            $table->unsignedBigInteger('subject_id');
            $table->jsonb('properties')->nullable(); // e.g. { "old": 500, "new": 50 }
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
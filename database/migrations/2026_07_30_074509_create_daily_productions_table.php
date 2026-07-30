<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_productions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bread_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('produced_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->date('production_date');
            $table->integer('quantity_produced');

            $table->timestamps();

            $table->unique(['bread_id', 'production_date']);
            $table->index('production_date');
        });

        // Postgres CHECK constraint — quantity_produced must be positive.
        // Laravel's schema builder has no native checkPositive() helper,
        // so this is raw SQL appended after table creation.
        DB::statement('ALTER TABLE daily_productions ADD CONSTRAINT quantity_produced_positive CHECK (quantity_produced > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_productions');
    }
};
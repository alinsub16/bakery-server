<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bread_opening_balances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bread_id')
                ->unique() // one opening balance per bread, ever
                ->constrained()
                ->restrictOnDelete();

            $table->integer('quantity');
            $table->text('note');

            $table->foreignId('set_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
        });

        DB::statement('ALTER TABLE bread_opening_balances ADD CONSTRAINT opening_balance_quantity_non_negative CHECK (quantity >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('bread_opening_balances');
    }
};
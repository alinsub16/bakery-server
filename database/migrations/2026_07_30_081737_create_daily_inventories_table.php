<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_inventories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bread_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();

            $table->date('inventory_date');
            $table->integer('opening_stock');
            $table->integer('closing_stock');
            $table->integer('sold_quantity');
            $table->decimal('revenue', 10, 2);

            $table->timestamps();

            $table->unique(['bread_id', 'inventory_date']);
            $table->index('inventory_date');
        });

        // Business rule enforced at the DB level: closing stock can never
        // exceed opening stock (which would produce a negative sold_quantity).
        DB::statement('ALTER TABLE daily_inventories ADD CONSTRAINT closing_not_greater_than_opening CHECK (closing_stock <= opening_stock)');
        DB::statement('ALTER TABLE daily_inventories ADD CONSTRAINT quantities_non_negative CHECK (opening_stock >= 0 AND closing_stock >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_inventories');
    }
};
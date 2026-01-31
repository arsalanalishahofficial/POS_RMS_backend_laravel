<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_item_adjustments', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('order_item_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('menu_item_id')
                ->constrained()
                ->cascadeOnDelete();

            // Order reference
            $table->string('receipt_number')->index();

            // Quantity tracking
            $table->integer('old_quantity');
            $table->integer('new_quantity');
            $table->integer('adjusted_quantity'); // cancelled or decreased qty

            // Financial impact (optional but useful)
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('amount_impact', 10, 2)->nullable();

            // Action type
            $table->enum('action', ['cancelled', 'decreased'])->index();

            // Who did it
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Extra info
            $table->text('reason')->nullable();
            $table->ipAddress('ip_address')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_item_adjustments');
    }
};

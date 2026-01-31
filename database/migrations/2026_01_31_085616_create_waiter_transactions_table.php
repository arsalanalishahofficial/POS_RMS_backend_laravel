<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('waiter_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('waiter_id')->constrained('waiters')->onDelete('cascade');
            $table->enum('type', ['deposit', 'return']); // deposit = insert money, return = take money back
            $table->decimal('amount', 10, 2);
            $table->text('note')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('waiter_transactions');
    }
};

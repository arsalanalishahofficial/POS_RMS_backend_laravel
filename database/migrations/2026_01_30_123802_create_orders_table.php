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
        Schema::create('orders', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->enum('type', ['takeaway', 'dinein', 'delivery', 'pakwan', 'udhar']);
            $table->foreignId('waiter_id')->nullable()->constrained('waiters')->nullOnDelete();
            $table->foreignId('table_id')->nullable()->constrained('tables')->nullOnDelete();
            $table->foreignId('floor_id')->nullable()->constrained('floors')->nullOnDelete();
            $table->decimal('grand_total', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('net_total', 10, 2);
            $table->decimal('cash_received', 10, 2)->nullable();
            $table->decimal('change_due', 10, 2)->nullable();
            $table->boolean('is_cancelled')->default(false);
            $table->softDeletes();
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
        Schema::dropIfExists('orders');
    }
};

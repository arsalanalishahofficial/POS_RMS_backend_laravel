<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        \DB::statement("
        ALTER TABLE orders 
        MODIFY COLUMN status ENUM('in_progress','completed','preparing','delivered','cash_collected','cancelled') NOT NULL DEFAULT 'in_progress'
    ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        \DB::statement("
        ALTER TABLE orders 
        MODIFY COLUMN status ENUM('in_progress','completed') NOT NULL DEFAULT 'in_progress'
    ");
    }
};

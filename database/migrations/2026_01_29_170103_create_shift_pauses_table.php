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
        Schema::create('shift_pauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->onDelete('cascade');
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('resumed_at')->nullable();
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
        Schema::dropIfExists('shift_pauses');
    }
};

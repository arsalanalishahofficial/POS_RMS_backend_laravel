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
        Schema::table('user_shifts', function (Blueprint $table) {
            $table->foreignId('terminal_id')->nullable()->constrained('terminals')->after('shift_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_shifts', function (Blueprint $table) {
            $table->dropForeign(['terminal_id']);
            $table->dropColumn('terminal_id');
        });
    }
};

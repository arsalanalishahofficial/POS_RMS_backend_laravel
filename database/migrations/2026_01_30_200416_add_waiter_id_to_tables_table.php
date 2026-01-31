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
        Schema::table('tables', function (Blueprint $table) {
            $table->unsignedBigInteger('waiter_id')->nullable()->after('floor_id');

            // optional: foreign key constraint if you have a waiters table
            $table->foreign('waiter_id')->references('id')->on('waiters')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropForeign(['waiter_id']);
            $table->dropColumn('waiter_id');
        });
    }
};

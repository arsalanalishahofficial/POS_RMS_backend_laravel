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
            $table->unsignedBigInteger('parent_table_id')->nullable()->after('id');
            $table->boolean('is_sub_table')->default(false)->after('parent_table_id');

            $table->foreign('parent_table_id')
                  ->references('id')
                  ->on('tables')
                  ->onDelete('cascade');
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
            $table->dropForeign(['parent_table_id']);
            $table->dropColumn(['parent_table_id', 'is_sub_table']);
        });
    }
};

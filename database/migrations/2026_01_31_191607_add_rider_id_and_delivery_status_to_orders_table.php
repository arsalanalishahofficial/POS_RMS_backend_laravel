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
         Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->after('floor_id');
            $table->unsignedBigInteger('rider_id')->nullable()->after('customer_id');
            $table->enum('delivery_status', ['preparing','delivered','cash_collected','cancelled'])
                  ->nullable()
                  ->after('rider_id');

            // Optional foreign keys if tables exist
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->foreign('rider_id')->references('id')->on('riders')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
       Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['rider_id']);
            $table->dropColumn(['customer_id', 'rider_id', 'delivery_status']);
        });
    }
};

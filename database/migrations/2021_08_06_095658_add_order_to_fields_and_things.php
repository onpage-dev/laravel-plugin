<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrderToFieldsAndThings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('op_fields', function (Blueprint $table) {
            $table->integer('order')->default(0)->index();
            $table->index(['resource_id', 'order']);
        });
        Schema::table('op_things', function (Blueprint $table) {
            $table->integer('order')->default(0)->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
// 
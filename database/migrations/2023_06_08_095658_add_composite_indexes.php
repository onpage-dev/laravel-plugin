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
        Schema::table('op_relations', function (Blueprint $table) {
            $table->index(['field_id', 'thing_to_id']);
        });

        Schema::table('op_values', function (Blueprint $table) {
            $table->index(['thing_id', 'field_id', 'lang']);
        });

        Schema::table('op_things', function (Blueprint $table) {
            $table->index(['id', 'resource_id']);
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
};
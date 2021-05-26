<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOpRelationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('op_relations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('thing_from_id')->index();
            $table->unsignedBigInteger('field_id')->index();
            $table->unsignedBigInteger('thing_to_id')->index();
            $table->foreign('thing_from_id')->references('id')->on('op_things')->onDelete('cascade');
            $table->foreign('field_id')->references('id')->on('op_fields')->onDelete('cascade');
            $table->foreign('thing_to_id')->references('id')->on('op_things')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('op_relations');
    }
}

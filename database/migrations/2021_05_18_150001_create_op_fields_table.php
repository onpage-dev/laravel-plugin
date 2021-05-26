<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOpFieldsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('op_fields', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('resource_id');
            $table->foreign('resource_id')->references('id')->on('op_resources')->onDelete('cascade');
            $table->string('name')->index();
            $table->string('type');
            $table->string('label', 500);
            $table->boolean('is_multiple');
            $table->boolean('is_translatable');
            $table->unsignedBigInteger('rel_res_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('op_fields');
    }
}

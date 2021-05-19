<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateValuesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('thing_id');
            $table->unsignedBigInteger('field_id');
            $table->foreign('thing_id')->references('id')->on('things')->onDelete('cascade');
            $table->foreign('field_id')->references('id')->on('fields')->onDelete('cascade');
            $table->text('lang')->nullable();
            $table->text('value_txt')->nullable();
            $table->text('value_token')->nullable();
            $table->double('value_real0')->nullable();
            $table->double('value_real1')->nullable();
            $table->double('value_real2')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('values');
    }
}

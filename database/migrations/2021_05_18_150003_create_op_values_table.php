<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOpValuesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('op_values', function (Blueprint $table) {
            $table->bigIngrements('id');
            $table->unsignedBigInteger('thing_id')->index();
            $table->unsignedBigInteger('field_id')->index();
            $table->string('lang', 10)->nullable()->index();
            $table->text('value_txt')->nullable();
            $table->string('value_token')->nullable()->index();
            $table->double('value_real0')->nullable()->index();
            $table->double('value_real1')->nullable()->index();
            $table->double('value_real2')->nullable()->index();

            $table->foreign('thing_id')->references('id')->on('op_things')->onDelete('cascade');
            $table->foreign('field_id')->references('id')->on('op_fields')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('op_values');
    }
}

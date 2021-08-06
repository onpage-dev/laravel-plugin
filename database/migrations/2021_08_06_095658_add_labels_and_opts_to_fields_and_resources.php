<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLabelsAndOptsToFieldsAndResources extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('op_resources', function (Blueprint $table) {
            $table->jsonb('labels')->nullable();
        });
        Schema::table('op_fields', function (Blueprint $table) {
            $table->jsonb('labels')->nullable();
            $table->jsonb('opts')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('op_resources', function (Blueprint $table) {
            $table->dropColumn('labels');
        });
        Schema::table('op_fields', function (Blueprint $table) {
            $table->dropColumn('labels');
            $table->dropColumn('opts');
        });
    }
}
// 
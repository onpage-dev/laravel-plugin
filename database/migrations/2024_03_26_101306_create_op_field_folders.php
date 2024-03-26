<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('op_field_folders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('resource_id');
            $table->string('name')->index();
            $table->string('type');
            $table->string('labels', 500);
            $table->foreign('resource_id')->references('id')->on('op_resources')->onDelete('cascade');
        });

        Schema::create('op_field_folder', function (Blueprint $table) {
            $table->unsignedBigInteger('folder_id');
            $table->unsignedBigInteger('field_id');
            $table->string('type');
            $table->foreign('folder_id')->references('id')->on('op_field_folders')->onDelete('cascade');
            $table->foreign('field_id')->references('id')->on('op_fields')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('op_field_folders');
        Schema::dropIfExists('op_field_folder');
    }
};

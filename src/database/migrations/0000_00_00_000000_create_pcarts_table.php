<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePCartsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('pcarts', function (Blueprint $table) {
            $table->string('identifier');
            $table->string('instance');
            $table->json('content');
            $table->integer('user_id');
            $table->nullableTimestamps();

            $table->primary(['identifier', 'instance']);
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::drop('pcarts');
    }
}

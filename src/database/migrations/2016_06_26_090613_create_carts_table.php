<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCartsTable extends Migration
{
	/**
         * Run the migrations.
         *
         * @return void
        */
	public function up()
    	{
		Schema::create('carts', function($table) {
			$table->increments('id');
			$table->json('cart_data');
			$table->timestamp('last_modified');
			$table->string('tracker', 500);
			$table->integer('user_id');
			// We'll need to ensure that MySQL uses the InnoDB engine to
			// support the indexes, other engines aren't affected.
			$table->engine = 'InnoDB';
            	});
	}

	/**
     	 * Reverse the migrations.
     	 *
     	 * @return void
     	 */
    	public function down()
    	{
        	//
    	}
}

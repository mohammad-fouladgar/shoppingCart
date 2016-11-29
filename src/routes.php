<?php

Route::group(['middleware' => ['web']], function () {

	
	Route::group(['middleware' => 'auth'], function() {
		//Route::get('cart', 'Charterhousetech\shoppingCart\CartController@index');
	});

	Route::group(['middleware' => 'customer'], function() {
		Route::get('cart/clear',       'Charterhousetech\shoppingCart\CartController@clear');
		Route::get('cart',	       'Charterhousetech\shoppingCart\CartController@index');
		Route::post('cart/add',        'Charterhousetech\shoppingCart\CartController@add');
		Route::post('cart/update',     'Charterhousetech\shoppingCart\CartController@update');
		Route::get('cart/remove/{id}', 'Charterhousetech\shoppingCart\CartController@remove');
	});
});

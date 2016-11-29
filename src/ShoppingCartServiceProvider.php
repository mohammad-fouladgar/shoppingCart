<?php

namespace Charterhousetech\shoppingCart;

use Illuminate\Support\ServiceProvider;
use Charterhousetech\shoppingCart\Commands;

class ShoppingCartServiceProvider extends ServiceProvider
{
    protected $defer = false;
	/**
     	 * Bootstrap the application services.
    	 *
    	 * @return void
    	 */
	public function boot()
    {
		$this->loadViewsFrom(__DIR__ . '/views', 'cart');

		$this->publishes([__DIR__ . '/views' => base_path('resources/views/vendor/charterhousetech/shoppingCart')]);

        	$this->publishes([__DIR__ . '/../public' => public_path('packages/charterhousetech/shoppingCart')]);

        	$this->publishes([__DIR__ . '/config/cart.php' => config_path('cart.php')]);

        	$this->app['cart::install'] = $this->app->share(function()
        	{
            	return new \Charterhousetech\shoppingCart\Commands\CartCommand();
        	});

		  $this->commands('cart::install');
    	}

    	/**
    	 * Register the application services.
    	 *
    	 * @return void
    	 */
    	public function register()
    	{
            include __DIR__ . '/routes.php';
            $this->app->make('Charterhousetech\shoppingCart\CartController');

            $this->mergeConfigFrom(__DIR__.'/config/cart.php', 'cart');

            $this->app['cart'] = $this->app->share(function($app){
                
                $storage      = $app['session'];
                $instanceName = 'cart';
                $session_key  = '4yTlTDKu3oJOfzDcly';

                return new PCart(
                    $storage,
                    $instanceName,
                    $session_key,
                    config('cart')
                 );
            });

    	}
}

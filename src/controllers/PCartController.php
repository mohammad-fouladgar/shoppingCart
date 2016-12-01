<?php

namespace Charterhousetech\shoppingCart;

use App\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Cart;

class PCartController extends Controller
{
	/**
	 * Product Cart
	 * @var 
	 */
	private $product;

	/**
	 * [$userId description]
	 * @var integer
	 */
	private $userId = 0;

	/**
	 * [$identifire description]
	 * @var [type]
	 */
	private $identifire ;

	function __construct(Product $product)
	{
		$this->product = $product;

		// Set userId
		if (auth()->check()) $this->userId = auth()->id();

		// Set identifire
		if( is_null($this->identifire = Cart::getIdentifier() ) )
		{
			$this->identifire = Cart::setIdentifier();
		}
			
	}
	public function index()
	{
		// check login and identifire....
		$shoppingcart = Cart::getContent();

		if (! $shoppingcart->count()) 
		{
			if ( $this->userId > 0 ) 
			{

				Cart::restoreByUserId($userId);
			}
			else
			{

				Cart::restoreByIdentifier($this->identifire);
			}

			$shoppingcart = Cart::getContent();
		}
		
		return view('cart::cart',compact('shoppingcart'));
	}

	/**
	 * add item to cart
	 * 
	 * @param Request $req [description]
	 */
	public function add(Request $req)
	{
		$product = $this->product->findOrFail($req->id);
		
		Cart::add([
				[
					'id'         => $product->id,
					'name'       => $product->slug,
					'price'      => $product->price,
					'quantity'   => $req->quantity,
					'attributes' => $req->options
				]
			]);

		// Store in pcarts table
		Cart::store($this->identifire,$this->userId);
		
		return  response()->json('Item is added successfully.');
	}
}

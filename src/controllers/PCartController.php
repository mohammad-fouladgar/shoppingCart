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

			Cart::setIdentifier();
			$this->identifire = Cart::getIdentifier();
		}
			
	}
	public function index()
	{
		
		
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

	/**
	 * removes an item on cart by item ID
	 * 
	 * @param  int $itemId 
	 * @return redirect
	 */
	public function remove($itemId)
	{
		Cart::remove($itemId);

		if (Cart::getContent()->count() < 1)
		{
			return $this->clear();	
		}

		Cart::store($this->identifire,$this->userId);

		return redirect()->route('cart.index');
	}

	/**
	 * update item quantity
	 * 
	 * @param  Request $req [description]
	 * @return [type]       [description]
	 */
	public function update(Request $req)
	{
		Cart::update($req->id,['quantity'=>['relative'=>false,'value'=>$req->quantity]]);
		
		Cart::store($this->identifire,$this->userId);
		
		return response()->json(["success" => true, "subtotal" => Cart::getTotal()]);
	}
	/**
	 * clear shopping cart
	 * 
	 * @return [type] [description]
	 */
	public function clear()
	{
		// clear cart and conditions
		Cart::clear();
		// remove row from table
		Cart::dbFree($this->identifire,$this->userId);
		// remove identifier
		Cart::removeIdentifire();

		return redirect()->route('cart.index');
	}
}

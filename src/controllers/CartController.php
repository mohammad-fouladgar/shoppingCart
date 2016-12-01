<?php

namespace Charterhousetech\shoppingCart;

use \Charterhousetech\shoppingCart\Cart;
use App\Product;
use Auth;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Validator;

class CartController extends Controller
{
	private $cartItems = array("id",
				   "quantity",
				   "size",
				   "fabric");

	public function __construct()
	{
	}

	public function getCartData($data)
	{
		$extCartData = array();
		if (!empty($data)) {
			$cartData  = json_decode($data, true);
			
			if (!empty($cartData)) {
				$extCartData = $cartData;
				foreach ($cartData as $key => $eachCartData) {
					$productId = $eachCartData['id'];
					if (!empty($productId)) {
						$productData = Product::where('id', '=', $productId)->first();
						if (!empty($productData)) {
							$metaData	   = $productData->meta_data;
							$extraProductInfo  = array('name'  => $productData->name,
										   'image' => (!empty($metaData[0]->images[0])) ?
									      	      	      $metaData[0]->images[0] : '',
										   'url'   => $productData->path,
										   'price' => $productData->price);
							$extCartData[$key] = array_merge($extCartData[$key], $extraProductInfo);
						}
					}
				}
			}
		}

		return $extCartData;
	}

	public function index(Request $request)
	{

		$cartData = null;
		$subTotal = 0;
		$customer = $request->get('customer');
		if (!empty($customer)) {

			$cart = Cart::where('user_id', '=', $customer->id)->first();
			if (!empty($cart)) {

				$cartData = $this->getCartData($cart->cart_data);

			} else {

				$trackerExists = false;
				if ($request->session()->has('cart_tracker')) {
					$tracker       = $request->session()->get('cart_tracker');
					$trackerExists = true;
				}

				if ($trackerExists == true && !empty($tracker)) {
					$cart = Cart::where('tracker', '=', $tracker)->first();
					if (!empty($cart)) {
						$cartData = $this->getCartData($cart->cart_data);
					}
				}
			}
			
		} else {

			$trackerExists = false;
			if ($request->session()->has('cart_tracker')) {
				$tracker       = $request->session()->get('cart_tracker');
				$trackerExists = true;
			}

			if ($trackerExists == true && !empty($tracker)) {
				$cart = Cart::where('tracker', '=', $tracker)->first();
				if (!empty($cart)) {
					$cartData = $this->getCartData($cart->cart_data);
				}
			}
		}

		if (!empty($cartData)) {
			foreach ($cartData as $eachCartItem) {
				$subTotal += $eachCartItem['quantity'] * $eachCartItem['price'];
			}
		}

		$request->session()->put('cart_subtotal', $subTotal);

		return view('cart::cart', array('items' => $cartData, 'subtotal' => number_format($subTotal, 2)));
	}

	public function insertCartData($cartData, $tracker = '', $userId = 0)
	{
		$jsonCartData = json_encode(array($cartData));

		$cart                = new Cart;
		$cart->tracker       = $tracker;
		$cart->user_id       = $userId;
		$cart->cart_data     = $jsonCartData;
		$cart->last_modified = Carbon::now()->toDateTimeString();
        	$cart->save();
	}

	public function appendCartData($cart, $cartData, $userId = 0)
	{
		$previousCartData = json_decode($cart->cart_data, true);
		$newCartData	  = array();
		$newDataAdded     = false;
		foreach ($previousCartData as $eachItem) {
			if ($cartData['id'] == $eachItem['id']) {
				$newCartData[] = $cartData;
				$newDataAdded  = true;
			} else {
				$newCartData[] = $eachItem;
			}
		}

		if ($newDataAdded == false) {
			$newCartData[] = $cartData;
		}

		$jsonCartData = json_encode($newCartData);

		$cart->user_id = $userId;
		if ($userId != 0) {
			$cart->tracker = '';
		}
		$cart->cart_data     = $jsonCartData;
		$cart->last_modified = Carbon::now()->toDateTimeString();
		$cart->save();
	}

	public function add(Request $request)
	{
		$validator = Validator::make($request->all(), Cart::$addRules);
		if ($validator->passes()) {

			$customer = $request->get('customer');

			$parameters = $request->all();
			$cartData   = array();
			foreach ($parameters as $param => $value) {
				if (in_array($param, $this->cartItems)) {
					$cartData[$param] = $value;
				}
			}

			$jsonCartData = '';
			if (!empty($cartData)) {

				if (!empty($customer)) {

					$cart = Cart::where('user_id', '=', $customer->id)->first();
					if (!empty($cart)) {
						$this->appendCartData($cart, $cartData, $customer->id);
					} else {

						$trackerExists = false;
						if ($request->session()->has('cart_tracker')) {
							$tracker       = $request->session()->get('cart_tracker');
							$trackerExists = true;
						}

						if ($trackerExists == true && !empty($tracker)) {

							$cart = Cart::where('tracker', '=', $tracker)->first();
							if (!empty($cart)) {
								$this->appendCartData($cart, $cartData, $customer->id);
							} else {
								$this->insertCartData($cartData, '', $customer->id);
							}

						} else {
							$this->insertCartData($cartData, '', $customer->id);
						}
					}

				} else {

					// Generate tracker
					$trackerExists = false;
					if ($request->session()->has('cart_tracker')) {
						$tracker       = $request->session()->get('cart_tracker');
						$trackerExists = true;
					} else {
						$tracker = base64_encode(rand(1111111111, 9999999999) . '+' . str_random(20));
						$request->session()->put('cart_tracker', $tracker);
					}

					if ($trackerExists == true) {

						$cart = Cart::where('tracker', '=', $tracker)->first();
						if (!empty($cart)) {
							$this->appendCartData($cart, $cartData);
						} else {
							$this->insertCartData($cartData, $tracker);
						}

					} else {
						$this->insertCartData($cartData, $tracker);
					}
				}
			}
			return  response()->json('Item is added successfully.', 200);

		} else {
			// Invalid cart data (TO Do A)
			return  response()->json($validator->messages(), 405);

		}
	}

	public function update(Request $request)
	{
		$cartId = $this->getCartId($request);
		if ($cartId) {
			$cart = Cart::where('id', '=', $cartId)->first();
			if (!empty($cart)) {
				$cartData    = json_decode($cart->cart_data, true);
				$newCartData = array();
				$itemIds     = array();
				$quantities  = array();
				foreach ($cartData as $eachItem) {
					$itemIds[] = $eachItem['id'];
					if ($request->get('id') != $eachItem['id']) {
						$newCartData[]		     = $eachItem;
						$quantities[$eachItem['id']] = $eachItem['quantity'];
					} else {
						$newQuantity	   	     = $request->get('quantity');
						$newItem       	     	     = $eachItem;
						$newItem['quantity']	     = $newQuantity;
						$quantities[$eachItem['id']] = $newQuantity;
						$newCartData[]	  	     = $newItem;
					}
				}

				if (!empty($newCartData)) {

					$subtotal = 0;
					if (!empty($itemIds)) {
						$ids	  = implode(",", $itemIds);
						$products = Product::whereRaw("id in (" . $ids . ")")->get();
						if (!empty($products)) {
							foreach ($products as $product) {
								$subtotal += $product->price * $quantities[$product->id];
							}
						}
					}					

					$request->session()->put('cart_subtotal', $subtotal);

					$cart->cart_data     = json_encode($newCartData);
					$cart->last_modified = Carbon::now()->toDateTimeString();
					$cart->save();

					return response()->json(array("success" => true, "subtotal" => $subtotal));
				}
			}
		}

		return response()->json(array("success" => false));
	}

	public function getCartId(Request $request)
	{
		$cartId   = null;
		$customer = $request->get('customer');
		if (!empty($customer)) {

			$cart = Cart::where('user_id', '=', $customer->id)->first();
			if (!empty($cart)) {
				$cartId = $cart->id;
			}

		} else if ($request->session()->has('cart_tracker')) {

			$tracker = $request->session()->get('cart_tracker');
			if (!empty($tracker)) {
				$cart = \Charterhousetech\shoppingCart\Cart::where('tracker', '=', $tracker)->first();
				if (!empty($cart)) {
					$cartId = $cart->id;
				}
			}
		}

		return $cartId;
	}

	public function remove(Request $request, $itemId)
	{
		$cartId = $this->getCartId($request);
		if ($cartId) {
			$cart        = Cart::where('id', '=', $cartId)->first();
			$cartData    = json_decode($cart->cart_data, true);
			$newCartData = array();
			$itemDeleted = false;
			foreach ($cartData as $eachItem) {
				if ($itemId !== $eachItem['id']) {
					$newCartData[] = $eachItem;
				} else {
					$itemDeleted = true;
				}
			}

			if ($itemDeleted === true) {
				if (!empty($newCartData)) {
					$cart->cart_data     = json_encode($newCartData);
					$cart->last_modified = Carbon::now()->toDateTimeString();
					$cart->save();
				} else {
					$cart->delete();
				}
			}
		}

		return redirect('cart');
	}

	public function clear(Request $request)
	{
		$cartId = $this->getCartId($request);
		if ($cartId) {
			$cart = Cart::where('id', '=', $cartId)->first();
			$cart->delete();
		}

		return redirect('cart');
	}
}

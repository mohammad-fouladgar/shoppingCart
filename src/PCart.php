<?php 

namespace Charterhousetech\shoppingCart;
use Charterhousetech\shoppingCart\Exceptions\InvalidConditionException;
use Charterhousetech\shoppingCart\Exceptions\InvalidItemException;
use Charterhousetech\shoppingCart\Exceptions\CartAlreadyStoredException;
use Charterhousetech\shoppingCart\Helpers\Helpers;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;

class PCart {

    /**
     * the item storage
     *
     * @var
     */
    protected $session;

    /**
     * the cart session key
     *
     * @var
     */
    protected $instanceName;

    /**
     * the session key use to persist cart items
     *
     * @var
     */
    protected $sessionKeyCartItems;

    /**
     * the session key use to persist cart conditions
     *
     * @var
     */
    protected $sessionKeyCartConditions;
    
    /**
     * the session keu use to persist cart identifier
     * @var 
     */
    protected $sessionKeyCartIdentifier;

    /**
     * Configuration to pass to ItemCollection
     *
     * @var
     */
    protected $config;
    /**
     * Rules for cart items
     * @var 
     */
    protected  $rules = [
            'id'       => 'required',
            'price'    => 'required|numeric',
            'quantity' => 'required|numeric|min:1',
            'name'     => 'required',
        ];

    /**
     * our object constructor
     *
     * @param $session
     * @param $instanceName
     * @param $session_key
     * @param $config
     */
    public function __construct($session, $instanceName, $session_key, $config)
    {
        $this->session = $session;
        $this->instanceName = $instanceName;
        $this->sessionKeyCartItems = $session_key.'_cart_items';
        $this->sessionKeyCartConditions = $session_key.'_cart_conditions';
        $this->sessionKeyCartIdentifier = $session_key.'_cart_identifier';
        $this->config = $config;
    }

    /**
     * get instance name of the cart
     *
     * @return string
     */
    public function getInstanceName()
    {
        return $this->instanceName;
    }

    /**
     * get an item on a cart by item ID
     *
     * @param $itemId
     * @return mixed
     */
    public function get($itemId)
    {
        return $this->getContent()->get($itemId);
    }

    /**
     * check if an item exists by item ID
     *
     * @param $itemId
     * @return bool
     */
    public function has($itemId)
    {
        return $this->getContent()->has($itemId);
    }

    /**
     * add item to the cart, it can be an array or multi dimensional array
     *
     * @param string|array $id
     * @param string $name
     * @param float $price
     * @param int $quantity
     * @param array $attributes
     * @param CartCondition|array $conditions
     * @return $this
     * @throws InvalidItemException
     */
    public function add($id, $name = null, $price = null, $quantity = null, $attributes = [], $conditions = [])
    {
        if( is_array($id) ) {
            if( Helpers::isMultiArray($id) ) {
                foreach($id as $item) {
                    $this->add(
                        $item['id'],
                        $item['name'],
                        $item['price'],
                        $item['quantity'],
                        Helpers::issetAndHasValueOrAssignDefault($item['attributes'], []),
                        Helpers::issetAndHasValueOrAssignDefault($item['conditions'], [])
                    );
                }
            } else {
                $this->add(
                    $id['id'],
                    $id['name'],
                    $id['price'],
                    $id['quantity'],
                    Helpers::issetAndHasValueOrAssignDefault($id['attributes'],[]),
                    Helpers::issetAndHasValueOrAssignDefault($id['conditions'],[])
                );
            }
            return $this;
        }
        // validate data and conditions
        new CartCondition($conditions); // for validate conditions
        $item = $this->validate([
            'id'         => $id,
            'name'       => $name,
            'price'      => Helpers::normalizePrice($price),
            'quantity'   => $quantity,
            'attributes' => new ItemAttributeCollection($attributes),
            'conditions' => new CartConditionCollection($conditions),
        ]);

        // get the cart
        $cart = $this->getContent();
       
        if( $cart->has($id) ) {
            $this->update($id, $item);
        } else {
            $this->addRow($id, $item);
        }

        return $this;
    }

    /**
     * update a cart
     *
     * @param $id
     * @param $data
     */
    public function update($id, $data)
    {

        $cart = $this->getContent();

        $item = $cart->pull($id);
        // dump($item->toJson());
        foreach($data as $key => $value) {

            if( $key == 'quantity' )
            {
                if( is_array($value) )
                {
                    if( isset($value['relative']) )
                    {
                        if( (bool) $value['relative'] )
                        {
                            $item = $this->updateQuantityRelative($item, $key, $value['value']);
                        }
                        else
                        {
                            $item = $this->updateQuantityNotRelative($item, $key, $value['value']);
                        }
                    }
                }
                else
                {
                    $item = $this->updateQuantityRelative($item, $key, $value);
                }
            }
            elseif( $key == 'attributes' )
            {
                $item[$key] = new ItemAttributeCollection($value);
            }
            elseif ( $key == 'conditions' ) 
            {
                // for validate condition
                new CartCondition($value);
                $item[$key] =  new CartConditionCollection($value);
                    
            }
            else
            {
                $item[$key] = $value;
            }
        }

        $cart->put($id, $item);

        $this->save($cart);
    }

    /**
     * add condition on an existing item on the cart
     *
     * @param int|string $productId
     * @param CartCondition $itemCondition
     * @return $this
     */
    public function addItemCondition($productId, $itemCondition)
    {
        if( $product = $this->get($productId) )
        {
            $conditionInstance = "\\Charterhousetech\\shoppingCart\\CartCondition";

            if( $itemCondition instanceof $conditionInstance )
            {
                $itemConditionTempHolder = $product['conditions'];

                if( is_array($itemConditionTempHolder) )
                {
                    array_push($itemConditionTempHolder, $itemCondition);
                }
                else
                {
                    $itemConditionTempHolder = $itemCondition;
                }

                $this->update($productId, [
                    'conditions' => $itemConditionTempHolder 
                ]);
            }
        }
        return $this;
    }

    /**
     * removes an item on cart by item ID
     *
     * @param $id
     */
    public function remove($id)
    {
        $cart = $this->getContent();

        $cart->forget($id);

        $this->save($cart);

    }

    /**
     * clear cart
     */
    public function clear()
    {

        $this->session->put($this->sessionKeyCartItems,[]);
    }

    /**
     * add a condition on the cart
     *
     * @param CartCondition|array $condition
     * @return $this
     * @throws InvalidConditionException
     */
    public function condition($condition)
    {
        if( is_array($condition) )
        {
            foreach($condition as $c)
            {
                $this->condition($c);
            }

            return $this;
        }

        if( ! $condition instanceof CartCondition ) throw new InvalidConditionException('Argument 1 must be an instance of \'Charterhousetech\shoppingCart\CartCondition\'');

        $conditions = $this->getConditions();

        if($condition->getOrder() == 0) {
            $last = $conditions->last();
            $condition->setOrder(!is_null($last) ? $last->getOrder() + 1 : 1);
        }

        $conditions->put($condition->getName(), $condition);

        $conditions = $conditions->sortBy(function ($condition, $key) {
            return $condition->getOrder();
        });

        $this->saveConditions($conditions);

        return $this;
    }

    /**
     * get conditions applied on the cart
     *
     * @return CartConditionCollection
     */
    public function getConditions()
    {
        return new CartConditionCollection($this->session->get($this->sessionKeyCartConditions));
    }

    /**
     * get condition applied on the cart by its name
     *
     * @param $conditionName
     * @return CartCondition
     */
    public function getCondition($conditionName)
    {
        return $this->getConditions()->get($conditionName);
    }

    /**
    * Get all the condition filtered by Type
    * @param $type
    * @return CartConditionCollection
    */
    public function getConditionsByType($type)
    {
        return $this->getConditions()->filter(function(CartCondition $condition) use ($type)
        {
            return $condition->getType() == $type;
        });
    }

    /**
     * Remove all the condition with the $type specified
     * @param $type
     * @return $this
     */
    public function removeConditionsByType($type)
    {
        $this->getConditionsByType($type)->each(function($condition)
        {
            $this->removeCartCondition($condition->getName());
        });
    }
    /**
     * removes a condition on a cart by condition name,
     * @param $conditionName
     * @return void
     */
    public function removeCartCondition($conditionName)
    {
        $conditions = $this->getConditions();

        $conditions->pull($conditionName);

        $this->saveConditions($conditions);
    }

    /**
     * remove a condition that has been applied on an item that is already on the cart
     *
     * @param $itemId
     * @param $conditionName
     * @return bool
     */
    public function removeItemCondition($itemId, $conditionName)
    {
        if( ! $item = $this->getContent()->get($itemId) )
        {
            return false;
        }

        if( $this->itemHasConditions($item) )
        {
            $tempConditionsHolder = $item['conditions'];

            if( is_array($tempConditionsHolder) )
            {
                foreach($tempConditionsHolder as $k => $condition)
                {
                    if( $condition->getName() == $conditionName )
                    {
                        unset($tempConditionsHolder[$k]);
                    }
                }

                $item['conditions'] = $tempConditionsHolder;
            }
            else
            {
                $conditionInstance = "Charterhousetech\\shoppingCart\\CartCondition";

                if ($item['conditions'] instanceof $conditionInstance)
                {
                    if ($tempConditionsHolder->getName() == $conditionName)
                    {
                        $item['conditions'] = [];
                    }
                }
            }
        }

        $this->update($itemId, ['conditions' => $item['conditions'] ]);
        return true;
    }

    /**
     * remove all conditions that has been applied on an item that is already on the cart
     *
     * @param $itemId
     * @return bool
     */
    public function clearItemConditions($itemId)
    {
        if( ! $item = $this->getContent()->get($itemId) )
        {
            return false;
        }

        $this->update($itemId, ['conditions' => [] ]);

        return true;
    }

    /**
     * clears all conditions on a cart,
     *
     * @return void
     */
    public function clearCartConditions()
    {
        $this->session->put( $this->sessionKeyCartConditions,[]);
    }

    /**
     * get cart sub total
     * @param bool $formatted
     * @return float
     */
    public function getSubTotal($formatted = true)
    {
        $cart = $this->getContent();

        $sum = $cart->sum(function($item)
        {
            return $item->getPriceSumWithConditions(false);
        });

        return Helpers::formatValue(floatval($sum), $formatted, $this->config);
    }

    /**
     * the new total in which conditions are already applied
     *
     * @return float
     */
    public function getTotal()
    {
        $subTotal = $this->getSubTotal(false);

        $newTotal = 0.00;

        $process = 0;

        $conditions = $this->getConditions();

        if( ! $conditions->count() ) return $subTotal;

        $conditions->each(function($cond) use ($subTotal, &$newTotal, &$process)
        {
            if( $cond->getTarget() === 'subtotal' )
            {
                ( $process > 0 ) ? $toBeCalculated = $newTotal : $toBeCalculated = $subTotal;

                $newTotal = $cond->applyCondition($toBeCalculated);

                $process++;
            }
        });

        return Helpers::formatValue($newTotal, $this->config['format_numbers'], $this->config);
    }

    /**
     * get total quantity of items in the cart
     *
     * @return int
     */
    public function getTotalQuantity()
    {
        $items = $this->getContent();

        if( $items->isEmpty() ) return 0;

        $count = $items->sum(function($item)
        {
            return $item['quantity'];
        });

        return $count;
    }

    /**
     * get the cart
     *
     * @return CartCollection
     */
    public function getContent()
    {
        return (new CartCollection($this->session->get($this->sessionKeyCartItems)));
    }

    /**
     * check if cart is empty
     *
     * @return bool
     */
    public function isEmpty()
    {
        $cart = new CartCollection($this->session->get($this->sessionKeyCartItems));

        return $cart->isEmpty();
    }

    /**
     * validate Item data
     *
     * @param $item
     * @return array $item;
     * @throws InvalidItemException
     */
    protected function validate($item)
    {

        $validator = \Validator::make($item, $this->rules);

        if( $validator->fails() )
        {
            throw new InvalidItemException($validator->messages()->first());
        }

        return $item;
    }

    /**
     * add row to cart collection
     *
     * @param $id
     * @param $item
     */
    protected function addRow($id, $item)
    {

        $cart = $this->getContent();

        $cart->put($id, new ItemCollection($item, $this->config));

        $this->save($cart);

    }

    /**
     * save the cart
     *
     * @param $cart CartCollection
     */
    protected function save($cart)
    {
        $this->session->put($this->sessionKeyCartItems, $cart);
    }

    /**
     * save the cart conditions
     *
     * @param $conditions
     */
    protected function saveConditions($conditions)
    {
        $this->session->put($this->sessionKeyCartConditions, $conditions);
    }

    /**
     * check if an item has condition
     *
     * @param $item
     * @return bool
     */
    protected function itemHasConditions($item)
    {
        if( ! isset($item['conditions']) ) return false;

        if( is_array($item['conditions']) )
        {
            return count($item['conditions']) > 0;
        }
        
        $conditionInstance = "Charterhousetech\\shoppingCart\\CartCondition";

        if( $item['conditions'] instanceof $conditionInstance ) return true;

        return false;
    }

    /**
     * update a cart item quantity relative
     *
     * @param $item
     * @param $key
     * @param $value
     * @return mixed
     */
    protected function updateQuantityRelative($item, $key, $value)
    {
        if( preg_match('/\-/', $value) == 1 )
        {
            $value = (int) str_replace('-','',$value);

            if( ($item[$key] - $value) > 0 )
            {
                $item[$key] -= $value;
            }
        }
        elseif( preg_match('/\+/', $value) == 1 )
        {
            $item[$key] += (int) str_replace('+','',$value);
        }
        else
        {
            $item[$key] += (int) $value;
        }

        return $item;
    }

    /**
     * update cart item quantity not relative to its current quantity value
     *
     * @param $item
     * @param $key
     * @param $value
     * @return mixed
     */
    protected function updateQuantityNotRelative($item, $key, $value)
    {
        $item[$key] = (int) $value;

        return $item;
    }

    /**
     * Setter for decimals. Change value on demand.
     * @param $decimals
     */
    public function setDecimals($decimals)
    {
        $this->decimals = $decimals;
    }

    /**
     * Setter for decimals point. Change value on demand.
     * @param $dec_point
     */
    public function setDecPoint($dec_point)
    {
        $this->dec_point = $dec_point;
    }

    public function setThousandsSep($thousands_sep)
    {
        $this->thousands_sep = $thousands_sep;
    }

    //----------------- Database-----------------
    /**
     * Store an the current instance of the cart.
     *
     * @param mixed $identifier
     * @return void
     */
    public function store($identifier)
    {
        $content = $this->getContent();
     
        if ($this->storedCartWithIdentifierExists($identifier)) {
            throw new CartAlreadyStoredException("A cart with identifier {$identifier} was already stored.");
        }

        $this->getConnection()->table($this->getTableName())->insert([
            'identifier' => $identifier,
            'instance' => $this->getInstanceName(),
            'content' => json_encode($content)
        ]);
    }

    /**
     * Restore the cart with the given identifier.
     *
     * @param mixed $identifier
     * @return void
     */
    public function restore($identifier)
    {
        if( ! $this->storedCartWithIdentifierExists($identifier)) {
            return;
        }

        $stored = $this->getConnection()->table($this->getTableName())
            ->where('identifier', $identifier)->first();
             
        $storedContent = json_decode($stored->content,true);
 // dump($storedContent);
        $currentInstance = $this->getInstanceName();

         $this->instanceName = $stored->instance;

        $content = $this->getContent();
       dd($storedContent);
        foreach ($storedContent as $cartItem) {
            // dump($cartItem);
            // $content->put($cartItem->id, $cartItem);
        }
         dump($content);

        // $this->session->put($this->instance, $content);
        // $this->instanceName = $currentInstance;

        // $this->getConnection()->table($this->getTableName())
        //     ->where('identifier', $identifier)->delete();
    }

    /**
     * @param $identifier
     * @return bool
     */
    private function storedCartWithIdentifierExists($identifier)
    {
        return $this->getConnection()->table($this->getTableName())->where('identifier', $identifier)->exists();
    }

    /**
     * Get the database connection.
     *
     * @return \Illuminate\Database\Connection
     */
    private function getConnection()
    {
        $connectionName = $this->getConnectionName();

        return app(DatabaseManager::class)->connection($connectionName);
    }

    /**
     * Get the database table name.
     *
     * @return string
     */
    private function getTableName()
    {
        return config('cart.database.table', 'pcarts');
    }

    /**
     * Get the database connection name.
     *
     * @return string
     */
    private function getConnectionName()
    {
        $connection = config('cart.database.connection');

        return is_null($connection) ? config('database.default') : $connection;
    }
    /**
     * generate a new idendifire string
     * 
     * @return string
     */
    public function generateIdentifier()
    {
        return base64_encode(rand(1111111111, 9999999999) . '+' . str_random(20));
    }

    /**
     * set session cart identifier key
     */
    public function setIdentifier()
    {
        $this->session->put($this->sessionKeyCartIdentifier,$this->generateIdentifier());
        
        return $this;
    }
    /**
     * get the identifier session
     * 
     * @return [type] [description]
     */
    public function getIdentifier()
    {
        return $this->session->get($this->sessionKeyCartIdentifier);
    }
}

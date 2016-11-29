<?php 
namespace Charterhousetech\shoppingCart;


use Charterhousetech\shoppingCart\Helpers\Helpers;
use Illuminate\Support\Collection;

class ItemCollection extends Collection {

    /**
     * Sets the config parameters.
     *
     * @var
     */
    protected $config;

    /**
     * ItemCollection constructor.
     * @param array|mixed $items
     * @param $config
     */
    public function __construct($items, $config)
    {
        parent::__construct($items);

        $this->config = $config;
    }

    /**
     * get the sum of price
     *
     * @return mixed|null
     */
    public function getPriceSum()
    {
        return Helpers::formatValue($this->price * $this->quantity, $this->config['format_numbers'], $this->config);

    }

    public function __get($name)
    {
        if( $this->has($name) ) return $this->get($name);
        return null;
    }

    /**
     * check if item has conditions
     *
     * @return bool
     */
    public function hasConditions()
    {
        
        $conditionCollectionInstans = "Charterhousetech\\shoppingCart\\CartConditionCollection";

            if (! $this ['conditions'] instanceof $conditionCollectionInstans) {
                # TODO: set expetion
                throw new \Exception('condition item must be an instance of \Charterhousetech\shoppingCart\CartConditionCollection\'');
            }
           
           return $this['conditions']->count() > 0;
        
        // echo "string";
        // $conditionInstance = "Charterhousetech\\shoppingCart\\CartCondition";
        // if( $this['conditions'] instanceof $conditionInstance ) return true;

        // return false;
    }

    /**
     * get the single price in which conditions are already applied
     * @param bool $formatted
     * @return mixed|null
     */
    public function getPriceWithConditions($formatted = true)
    {
        $originalPrice = $this->price;
        $newPrice = 0.00;
        $processed = 0;

        if( $this->hasConditions() )
        {
           if (Helpers::isMultiArray($this->conditions->toArray()) ) {
             
              foreach ($this->conditions as $key => $cond) 
              {

                $cond = new CartCondition($cond);
               if( $cond->getTarget() === 'item' )
                {
                    ( $processed > 0 ) ? $toBeCalculated = $newPrice : $toBeCalculated = $originalPrice;
                    $newPrice = $cond->applyCondition($toBeCalculated);
                    $processed++;
                }
              }
              
           }
           else
           {
                $cond = new CartCondition($this->conditions->toArray());
               
                if( $cond->getTarget() === 'item' )
                {
                    $newPrice = $cond->applyCondition($originalPrice);
                }
           }
           return Helpers::formatValue($newPrice, $formatted, $this->config);
        }
        return Helpers::formatValue($originalPrice, $formatted, $this->config);
    }

    /**
     * get the sum of price in which conditions are already applied
     * @param bool $formatted
     * @return mixed|null
     */
    public function getPriceSumWithConditions($formatted = true)
    {
        return Helpers::formatValue($this->getPriceWithConditions(false) * $this->quantity, $formatted, $this->config);
    }
}

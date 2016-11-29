<?php 
namespace Charterhousetech\shoppingCart;

use Charterhousetech\shoppingCart\Exceptions\InvalidConditionException;
use Charterhousetech\shoppingCart\Helpers\Helpers;

class CartCondition {

    /**
     * @var array
     */
    private $args;

    /**
     * the parsed raw value of the condition
     *
     * @var
     */
    private $parsedRawValue;
    /**
     * The rules cart conditions
     * @var 
     */
    private $rules = [
            'name'   => 'required',
            'type'   => 'required',
            'target' => 'required',
            'value'  => 'required',
        ];

    /**
     * @param array $args (name, type, target, value)
     * @throws InvalidConditionException
     */
    public function __construct(array $args)
    {
        $this->args = $args;

        if( Helpers::isMultiArray($args) )
        {
            throw new InvalidConditionException('Multi dimensional array is not supported.');
        }
        else
        {
            $this->validate($this->args);
        }
    }

    /**
     * the target of where the condition is applied
     *
     * @return mixed
     */
    public function getTarget()
    {
        return $this->args['target'];
    }

    /**
     * the name of the condition
     *
     * @return mixed
     */
    public function getName()
    {
        return $this->args['name'];
    }

    /**
     * the type of the condition
     *
     * @return mixed
     */
    public function getType()
    {
        return $this->args['type'];
    }

    /**
     * get the additional attributes of a condition
     *
     * @return array
     */
    public function getAttributes()
    {
        return (isset($this->args['attributes'])) ? $this->args['attributes'] : array();
    }

    /**
     * the value of this the condition
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->args['value'];
    }

    /**
     * Set the order to apply this condition. If no argument order is applied we return 0 as
     * indicator that no assignment has been made
     * @param int $order
     * @return Integer
     */
    public function setOrder($order = 1)
    {
        $this->args['order'] = $order;
    }

    /**
     * the order to apply this condition. If no argument order is applied we return 0 as
     * indicator that no assignment has been made
     *
     * @return Integer
     */
    public function getOrder()
    {
        return isset($this->args['order']) && is_numeric($this->args['order']) ? (int)$this->args['order'] : 0;
    }

    /**
     * apply condition to total or subtotal
     *
     * @param $totalOrSubTotalOrPrice
     * @return float
     */
    public function applyCondition($totalOrSubTotalOrPrice)
    {
        return $this->apply($totalOrSubTotalOrPrice, $this->getValue());
    }

    /**
     * get the calculated value of this condition supplied by the subtotal|price
     *
     * @param $totalOrSubTotalOrPrice
     * @return mixed
     */
    public function getCalculatedValue($totalOrSubTotalOrPrice)
    {
        $this->apply($totalOrSubTotalOrPrice, $this->getValue());

        return $this->parsedRawValue;
    }

    /**
     * apply condition
     *
     * @param $totalOrSubTotalOrPrice
     * @param $conditionValue
     * @return float
     */
    protected function apply($totalOrSubTotalOrPrice, $conditionValue)
    {
        if( $this->valueIsPercentage($conditionValue) )
        {
            if( $this->valueIsToBeSubtracted($conditionValue) )
            {
                $value = Helpers::normalizePrice( $this->cleanValue($conditionValue) );

                $this->parsedRawValue = $totalOrSubTotalOrPrice * ($value / 100);

                $result = floatval($totalOrSubTotalOrPrice - $this->parsedRawValue);
            }
            else if ( $this->valueIsToBeAdded($conditionValue) )
            {
                $value = Helpers::normalizePrice( $this->cleanValue($conditionValue) );

                $this->parsedRawValue = $totalOrSubTotalOrPrice * ($value / 100);

                $result = floatval($totalOrSubTotalOrPrice + $this->parsedRawValue);
            }
            else
            {
                $value = Helpers::normalizePrice($conditionValue);

                $this->parsedRawValue = $totalOrSubTotalOrPrice * ($value / 100);

                $result = floatval($totalOrSubTotalOrPrice + $this->parsedRawValue);
            }
        }
        else
        {
            if( $this->valueIsToBeSubtracted($conditionValue) )
            {
                $this->parsedRawValue = Helpers::normalizePrice( $this->cleanValue($conditionValue) );

                $result = floatval($totalOrSubTotalOrPrice - $this->parsedRawValue);
            }
            else if ( $this->valueIsToBeAdded($conditionValue) )
            {
                $this->parsedRawValue = Helpers::normalizePrice( $this->cleanValue($conditionValue) );

                $result = floatval($totalOrSubTotalOrPrice + $this->parsedRawValue);
            }
            else
            {
                $this->parsedRawValue = Helpers::normalizePrice($conditionValue);

                $result = floatval($totalOrSubTotalOrPrice + $this->parsedRawValue);
            }
        }

        return $result < 0 ? 0.00 : $result;
    }

    /**
     * check if value is a percentage
     *
     * @param $value
     * @return bool
     */
    protected function valueIsPercentage($value)
    {
        return (preg_match('/%/', $value) == 1);
    }

    /**
     * check if value is a subtract
     *
     * @param $value
     * @return bool
     */
    protected function valueIsToBeSubtracted($value)
    {
        return (preg_match('/\-/', $value) == 1);
    }

    /**
     * check if value is to be added
     *
     * @param $value
     * @return bool
     */
    protected function valueIsToBeAdded($value)
    {
        return (preg_match('/\+/', $value) == 1);
    }

    /**
     * removes some arithmetic signs (%,+,-) only
     *
     * @param $value
     * @return mixed
     */
    protected function cleanValue($value)
    {
        return str_replace(array('%','-','+'),'',$value);
    }

    /**
     * validates condition arguments
     *
     * @param $args
     * @throws InvalidConditionException
     */
    protected function validate($args)
    {
        $validator = \Validator::make($args,$this->rules );

        if( $validator->fails() )
        {
            throw new InvalidConditionException($validator->messages()->first());
        }
    }
}
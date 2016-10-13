<?php namespace Runnable;

use InvalidArgumentException;
use Symfony\Component\Console\Exception\ExceptionInterface;

class EnvNotFoundException extends InvalidArgumentException implements ExceptionInterface {

    /**
     * Note: The symfony CommandNotFoundException was derived.
     *
     * @param string    $message      Exception message to throw
     * @param int       $code         Exception code
     * @param Exception $previous     previous exception used for the exception chaining
     */
    public function __construct($message, array $alternatives = array(), $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

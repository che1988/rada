<?php
namespace Rada\Exception;

/**
 * Exception类
 * @author 
 *
 */
class RadaException extends \Exception {
    public function __construct($message, $code=10000, $previous=null) {
        parent::__construct($message, $code, $previous);
    }
}
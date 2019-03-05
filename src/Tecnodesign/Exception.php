<?php
/**
 * Tecnodesign Exception
 *
 * @category  UI
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      http://tecnodz.com/
 */
class Tecnodesign_Exception extends Exception
{
    public $error = true;

    public function __construct($message, $code = 0, $previous = null)
    {
        if (is_array($message)) {
            $m = array_shift($message);
            $message = vsprintf($m, $message);
        }
        parent::__construct($message, $code, $previous);
    }
}

<?php
/**
 * Tecnodesign Exception
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.7
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

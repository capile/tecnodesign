<?php
/**
 * Configuration files updater
 *
 * @package     capile/tecnodesign
 * @author      Tecnodesign <ti@tecnodz.com>
 * @license     GNU General Public License v3.0
 * @link        https://tecnodz.com
 * @version     2.5
 */

namespace Studio\Model;

use Studio\Model;
use tdz as S;

class Config extends Model
{
    public static $schema;

    protected $studio, $tecnodesign, $user;

    public function choicesStudioVersion()
    {
        return ["2.5"=>"2.5"];
    }

    public function renderTitle()
    {
        return $this->__uid;
    }
}
<?php
/**
 * OAuth2 Server Tokens
 *
 * @package     capile/tecnodesign
 * @author      Tecnodesign <ti@tecnodz.com>
 * @license     GNU General Public License v3.0
 * @link        https://tecnodz.com
 * @version     2.4
 */

namespace Studio\OAuth2;

use Studio\Model;

class Tokens extends Model
{
    public static $schema;
    protected $id, $type, $token, $user, $options, $created, $updated, $expires;
}
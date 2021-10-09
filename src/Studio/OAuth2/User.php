<?php
/**
 * OAuth2 User authentication
 *
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.6
 */

namespace Studio\OAuth2;

use OAuth2\Request;
use OAuth2\Response;
use Studio\OAuth2\Server;
use Studio\App;
use Tecnodesign_Studio as Studio;
use Tecnodesign_Cache as Cache;
use tdz as S;

class User
{
    public static function authenticate($options=[])
    {
        $auth = null;
        if($h=App::request('headers', 'authorization')) {
            if(($n=Server::config('token_bearer_header_name')) && substr($h, 0, strlen($n)+1)==$n.' ') {
                $auth = substr($h, strlen($n)+1);
            }
        } else if(Server::config('allow_credentials_in_request_body') && ($n=Server::config('token_param_name')) && ($p=App::request('post', $n))) {
            $auth = $n;
        }

        if($auth) {
            $Server = Server::instance();
            $Request = Request::createFromGlobals();
            $token = null;
            if ($Server->verifyResourceRequest($Request)) {
                $token = $Server->getAccessTokenData($Request);

            }
            unset($Request, $Server);
            \tdz::log(__METHOD__, $token);

            return ($token && isset($token['user_id'])) ?$token['user_id'] :null;
        }
    }
}
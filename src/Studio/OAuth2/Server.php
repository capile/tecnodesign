<?php
/**
 * OAuth2 Server implementation using thephpleague/oauth2-server
 *
 * @package     capile/tecnodesign
 * @author      Tecnodesign <ti@tecnodz.com>
 * @license     GNU General Public License v3.0
 * @link        https://tecnodz.com
 * @version     2.4
 */

namespace Studio\OAuth2;

use OAuth2\Request;

class Server extends \OAuth2\Server
{
    protected static $instance;
    public static function instance()
    {
        if(!static::$instance) {
            $storage = new Storage();
            $app = \tdz::getApp()->studio;
            $cfg = ($app && isset($app['oauth2'])) ?$app['oauth2'] :[];
            $grantTypes = [];
            $responseTypes = [];

            $S = [];
            foreach(Storage::$scopes as $type=>$scope) {
                $S[$type] = $storage;
                unset($type, $scope);
            }
            if(isset($cfg['grant_types'])) {
                foreach($cfg['grant_types'] as $i=>$o) {
                    if(class_exists($cn = 'OAuth2\\GrantType\\'.\tdz::camelize($o, true))) {
                        $grantTypes[$o] = new $cn($storage);
                    }
                }
            }
            if(isset($cfg['response_types'])) {
                foreach($cfg['response_types'] as $i=>$o) {
                    if(class_exists($cn = 'OAuth2\\ResponseType\\'.\tdz::camelize($o, true))) {
                        $responseTypes[$o] = new $cn($storage);
                    }
                }
            }

            try {
                $cn = get_called_class();
                static::$instance = new $cn($S, $cfg, $grantTypes, $responseTypes);
            } catch(\Exception $e) {
                \tdz::debug(__METHOD__, var_export($e, true));
            }
        }

        return static::$instance;
    }

    public function executeTokenRequest()
    {
        try {
            $request = Request::createFromGlobals();
            $R = $this->handleTokenRequest($request);
        } catch(\Exception $e) {
            \tdz::debug(__METHOD__, var_export($e, true));
        }
        $R->send();
        exit();
    }

    public function executeAuth()
    {
        // Handle a request to a resource and authenticate the access token
        if (!$this->verifyResourceRequest(Request::createFromGlobals())) {
            $this->getResponse()->send();
            die;
        }

        \tdz::output(array('success' => true, 'message' => 'OK'), 'json');
    }
}
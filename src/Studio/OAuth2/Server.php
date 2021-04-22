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
use OAuth2\Response;
use Studio\App;
use tdz as S;

class Server extends \OAuth2\Server
{
    protected static $instance;
    public static function instance()
    {
        if(!static::$instance) {
            $storage = new Storage();
            $app = S::getApp()->studio;
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
                    if(class_exists($cn = 'OAuth2\\GrantType\\'.S::camelize($o, true))) {
                        $grantTypes[$o] = new $cn($storage);
                    }
                }
            }
            if(isset($cfg['response_types'])) {
                foreach($cfg['response_types'] as $i=>$o) {
                    if(class_exists($cn = 'OAuth2\\ResponseType\\'.S::camelize($o, true))) {
                        $responseTypes[$o] = new $cn($storage);
                    }
                }
            }

            try {
                $cn = get_called_class();
                static::$instance = new $cn($S, $cfg, $grantTypes, $responseTypes);
            } catch(\Exception $e) {
                S::debug(__METHOD__, var_export($e, true));
            }
        }

        return static::$instance;
    }

    public static $routes=[
        'access_token'=>'executeTokenRequest',
        'auth'=>'executeAuth',
        'authorize'=>'executeAuthorize',
    ];

    public static function app()
    {
        if(($route=App::response('route')) && isset($route['url'])) {
            S::scriptName($route['url']);
        }

        if(($p = S::urlParams()) && count($p)==1 && isset(static::$routes[$p[0]])) {
            $m = static::$routes[$p[0]];
            if(is_array($m)) {
                return S::exec($m);
            } else {
                return static::instance()->$m();
            }
        }

        return App::error(404);
    }

    public static function appAccessToken()
    {
        static::instance()->executeTokenRequest();
    }

    public static function appAuth()
    {
        static::instance()->executeAuth();
    }

    public static function appAuthorize()
    {
        static::instance()->executeAuthorize();
    }

    public function executeTokenRequest()
    {
        try {
            $request = Request::createFromGlobals();
            $R = $this->handleTokenRequest($request);
        } catch(\Exception $e) {
            S::debug(__METHOD__, var_export($e, true));
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

        S::output(array('success' => true, 'message' => 'OK'), 'json');
    }

    public function executeAuthorize()
    {
        $request = Request::createFromGlobals();
        $response = new Response();

        // validate the authorize request
        if (!$this->validateAuthorizeRequest($request, $response)) {
            $response->send();
            die;
        }
        // display an authorization form
        if (empty($_POST)) {
          exit('
        <form method="post">
          <label>Do You Authorize TestClient?</label><br />
          <input type="submit" name="authorized" value="yes">
          <input type="submit" name="authorized" value="no">
        </form>');
        }

        // print the authorization code if the user has authorized your client
        $is_authorized = ($_POST['authorized'] === 'yes');
        $this->handleAuthorizeRequest($request, $response, $is_authorized);
        if ($is_authorized) {
          // this is only here so that you get to see your code in the cURL request. Otherwise, we'd redirect back to the client
          $code = substr($response->getHttpHeader('Location'), strpos($response->getHttpHeader('Location'), 'code=')+5, 40);
          exit("SUCCESS! Authorization Code: $code");
        }
        $response->send();

    }
}
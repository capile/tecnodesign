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
use Tecnodesign_Studio as Studio;
use Tecnodesign_Cache as Cache;
use tdz as S;

class Server extends \OAuth2\Server
{
    protected static $instance;
    public static $metadata, $cfg, $cfgDefault=[
        'unique_access_token'               => true,
        'use_jwt_access_tokens'             => false,
        'jwt_extra_payload_callable'        => null,
        'store_encrypted_token_string'      => true,
        'use_openid_connect'                => true,
        'id_lifetime'                       => 3600,
        'access_lifetime'                   => 3600,
        'www_realm'                         => 'Service',
        'token_param_name'                  => 'access_token',
        'token_bearer_header_name'          => 'Bearer',
        'enforce_state'                     => true,
        'require_exact_redirect_uri'        => true,
        'allow_implicit'                    => false,
        'allow_credentials_in_request_body' => false,
        'allow_public_clients'              => false,
        'always_issue_new_refresh_token'    => false,
        'unset_refresh_token_after_use'     => true,
        'default_scope'                     => 'basic openid',
        'supported_scopes'                  => [ 'basic', 'openid' ],
        'grant_types'                       => [ 'authorization_code', 'client_credentials', 'jwt_bearer', 'refresh_token', 'user_credentials' ],
        'response_types'                    => [ 'code', 'code id_token', 'id_token', 'id_token token', 'token' ],
        'storages'                          => [ 'access_token', 'authorization_code', 'client_credentials', 'client', 'refresh_token', 'user_credentials', 'user_claims', 'public_key', 'jwt_bearer', 'scope' ],
    ];

    public static function instance()
    {
        if(!static::$instance) {
            $storage = new Storage();
            $grantTypes = [];
            $responseTypes = [];

            $S = [];
            if($storages = self::config('storages')) {
            foreach($storages as $type) {
                if(isset(Storage::$scopes[$type])) {
                    $S[$type] = $storage;
                }
                unset($type);
            }

            $oidc = null;
            if(self::config('use_openid_connect')) {
                $oidc = true;
                if(!($r=self::config('grant_types'))) self::config('grant_types', ['authorization_code']);
                else if(!in_array('authorization_code', $r)) {
                    $r[] = 'authorization_code';
                    self::config('grant_types', $r);
                }
                // +implicit ?
            }

            $cfg = self::config();

            if($r=self::config('grant_types')) {
                foreach($r as $i=>$o) {
                    if($oidc && $o=='authorization_code' && class_exists($cn = 'OAuth2\\OpenID\\GrantType\\'.S::camelize($o, true))) {
                        $grantTypes[$o] = new $cn($storage);
                    } else if(class_exists($cn = 'OAuth2\\GrantType\\'.S::camelize($o, true))) {
                        if($o=='jwt_bearer') {
                            $grantTypes[$o] = new $cn($storage, static::metadata()['issuer']);
                        } else {
                            $grantTypes[$o] = new $cn($storage);
                        }
                    }
                }
            }
            if($r=self::config('response_types')) {
                foreach($r as $i=>$o) {
                    if(class_exists($cn = 'OAuth2\\ResponseType\\'.S::camelize($o, true))) {
                        $responseTypes[$o] = new $cn($storage);
                    }
                }
            }

            // move this to the storage
            $tokenType=null;

            $Scope = new \OAuth2\Scope($storage);

            try {
                $cn = get_called_class();
                static::$instance = new $cn($S, $cfg, $grantTypes, $responseTypes, $tokenType, $Scope);
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
        '.well-known/openid-configuration'=>'executeMetadata',
        'userinfo'=>'executeUserInfo'
    ];

    public static function app()
    {
        if(($route=App::response('route')) && isset($route['url'])) {
            S::scriptName($route['url']);
        }

        if(($p = implode('/', S::urlParams())) && isset(static::$routes[$p])) {
            $m = static::$routes[$p];
            if(is_array($m)) {
                return S::exec($m);
            } else {
                return static::instance()->$m();
            }
        }

        return Studio::error(404);
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

    public static function config($key=null, $value=false)
    {
        if(is_null(static::$cfg)) {
            static::$cfg = (($app=S::getApp()->studio) && isset($app['oauth2'])) ?$app['oauth2'] :[];
            static::$cfg += static::$cfgDefault;
            unset($app);
        }

        if($key) {
            if($value!==false) self::$cfg[$key] = $value;

            return (isset(static::$cfg[$key])) ?static::$cfg[$key] :null;
        }

        return static::$cfg;
    }

    public static function metadata($useCache=true, $uri=null)
    {
        if(!$uri) $uri = S::buildUrl(S::scriptName());
        if(isset(static::$metadata) && static::$metadata['issuer']!=$uri) {
            static::$metadata = null;
        }
        if(!static::$metadata && $useCache) {
            $M = Cache::get('oauth2/metadata/'.$uri);
        }
        if(!static::$metadata) {
            $M = [
                'issuer'=>$uri,
            ];
            if($k=array_search('executeAuthorize', static::$routes)) {
                if(!preg_match('#^(https?:|/)#', $k)) $k = $uri.'/'.urlencode($k);
                $M['authorization_endpoint']=$k;
            }
            if($k=array_search('executeTokenRequest', static::$routes)) {
                if(!preg_match('#^(https?:|/)#', $k)) $k = $uri.'/'.urlencode($k);
                $M['token_endpoint']=$k;
            }
            if($k=array_search('executeUserInfo', static::$routes)) {
                if(!preg_match('#^(https?:|/)#', $k)) $k = $uri.'/'.urlencode($k);
                $M['userinfo_endpoint']=$k;
            }

            if($r=self::config('response_types')) {
                $M['response_types_supported'] = array_values($r);
                asort($M['response_types_supported']);
            } else if(self::config('use_openid_connect')) {
                $M['response_types_supported'] = ['code', 'id_token', 'token id_token'];
            }

            if($r=self::config('grant_types')) {
                $M['grant_types_supported'] = array_values($r);
                asort($M['grant_types_supported']);
            } else if(self::config('use_openid_connect')) {
                $M['grant_types_supported'] = ['authorization_code', 'implicit'];
            }

            $M['scopes_supported'] = ['openid'];
          
            $M['subject_types_supported'] = ['public'];
            $M['id_token_signing_alg_values_supported']=['RS256'];

            Cache::set('oauth2/metadata/'.$uri, $M);
            static::$metadata = $M;
            unset($M);
        }

        return static::$metadata;
    }


    public function executeMetadata()
    {
        S::output(json_encode(static::metadata(false),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), 'json');
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

    public function executeUserInfo()
    {
        $request = Request::createFromGlobals();
        $this->handleUserInfoRequest($request)->send();
        exit();
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
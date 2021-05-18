<?php
/**
 * OAuth2 Client authentication
 *
 * @package     capile/tecnodesign
 * @author      Tecnodesign <ti@tecnodz.com>
 * @license     GNU General Public License v3.0
 * @link        https://tecnodz.com
 * @version     2.5
 */

namespace Studio\OAuth2;

use OAuth2\Request;
use OAuth2\Response;
use Studio\OAuth2\Server;
use Studio\OAuth2\Storage;
use Studio\App;
use Studio\User;
use Tecnodesign_Query_Api as Api;
use Tecnodesign_Studio as Studio;
use Tecnodesign_Cache as Cache;
use Tecnodesign_PublicObject as PublicObject;
use tdz as S;

class Client extends PublicObject
{
    public static 
        $meta,
        $signInRoute = '/signin/oauth2',
        $requestHeaders = ['accept: application/json'],
        $tokenHeaders = [],
        $userinfoHeaders = [];
    protected static $cfg;

    protected $id, $issuer, $client_id, $client_secret, $grant_type, $scope, $user_create, $user_update, $user_key, $user_map, $authorization_endpoint, $authorization_params, $token_endpoint, $token_params, $userinfo_endpoint;

    public static function config($prop=null)
    {
        if(is_null(static::$cfg)) {
            static::$cfg = [];
            $U = S::getUser();
            $ns = $U::config('ns');
            unset($U);
            if($ns) {
                $cn = get_called_class();
                foreach($ns as $i=>$o) {
                    if(isset($o['class']) && $o['class']===$cn) {
                        static::$cfg = $o;
                        break;
                    }
                    unset($i, $o);
                }
                unset($ns);
            }
        }

        if(!isset(static::$cfg['servers'])) {
            static::$cfg['servers'] = [];
            $T = Storage::find('server_credentials');
            if($T) {
                foreach($T as $i=>$o) {
                    $n = null;
                    if(isset($o['id'])) {
                        $n = $o['id'];
                    } else if(isset($o['name'])) {
                        $n = $o['name'];
                    } else if(!is_int($i)) {
                        $n = $i;
                    } else if(isset($o['issuer'])) {
                        $n = preg_replace('#^https?://|/.*$#', '', $o['issuer']);
                    } else {
                        continue;
                    }
                    if(!isset($o['button'])) $o['button'] = S::xml(sprintf(S::t('Sign in with %s', 'user'), $n));

                    if(isset($o['issuer']) && preg_match('#^https?://#', $o['issuer'])) {
                        if(!($d=Cache::get('oauth2-metadata/'.$o['issuer']))) {
                            $d = S::unserialize(file_get_contents($o['issuer'].'/.well-known/openid-configuration'));
                            if(!$d) $d = ['issuer'=>$o['issuer']];
                            Cache::set('oauth2-metadata/'.$o['issuer'], $d);
                        }
                        $o += $d;
                        unset($d);
                    }
                    static::$cfg['servers'][$n] = $o;
                }
            }
        }

        if($prop) {
            return (isset(static::$cfg[$prop])) ?static::$cfg[$prop] :null;
        }

        return static::$cfg;
    }

    public static function authenticate($options=[])
    {
    }

    public static function signIn($options=[])
    {
        if($options) {
            static::$cfg = $options; 
        }

        $s = null;
        if($S=static::config('servers')) {
            foreach($S as $n=>$o) {
                $s .= '<a class="z-i-button" href="'.S::xml(static::$signInRoute.'/'.$n).'?ref=1">'.S::xml($o['button']).'</a>';
            }
        }

        if($s) {
            $s = '<div class="ui-buttons">'.$s.'</div>';
        }

        return $s;
    }

    public function currentClient($q=[])
    {
        $U = S::getUser();
        $Client = null;
        if($L = Storage::find(['type'=>'authorization::'.$this->issuer,'id'=>$U->getSessionId()], false)) {
            foreach($L as $i => $Client) {
                if($q) {
                    $valid = true;
                    foreach($q as $k=>$v) {
                        if($v===true) {
                            if($Client[$k]) {
                                continue;
                            }
                        } else if($v==$Client[$k]) {
                            continue;
                        }
                        $valid = false;
                        break;
                    }
                    if(!$valid) {
                        unset($L[$i], $i);
                        $Client = null;
                        continue;
                    }
                }

                break;
            }
        }

        return $Client;
    }

    public static function authorize($options=[])
    {
        $S = static::config('servers');

        if(!($p=S::urlParams()) && ($route = App::response('route'))) {
            S::scriptName($route['url']);
            $p = S::urlParams();
        }

        if(App::request('get', 'ref') && (($ref=App::request('headers', 'referer')) && substr($ref, 0, strlen(S::scriptName()))!=S::scriptName())) {
            $U = S::getUser();
            $U->setAttribute('authorize-source', $ref);
        }

        if($p && ($p=implode('/', $p)) && isset($S[$p])) {
            $Server = new Client($S[$p]);

            $Client = $Server->currentClient(['options.access_token'=>true, 'scope'=>$Server->scope]);
            $User = null;

            if($Client && ($User=$Server->requestUserinfo($Client))) {
            } else if($code=App::request('get', 'code')) {
                $Client = $Server->requestToken($code);
            } else {
                $Client = $Server->requestAuthorization();
            }

            if(!$User && $Client && $Client['options.access_token']) {
                $User = $Server->requestUserinfo($Client);
            }

            if($User) {
                if(!isset($U)) $U = S::getUser();
                if($nss = $U::config('ns')) {
                    $ns = null;
                    foreach($nss as $ns=>$nso) {
                        if(isset($nso['class']) && $nso['class']===get_called_class()) {
                            break;
                        }
                        $ns = null;
                        unset($nso);
                    }
                }

                $U->setObject($ns, $User);
                if($ref=$U->getAttribute('authorize-source')) {
                    $U->setAttribute('authorize-source', null);
                } else if(isset($nso) && isset($nso['redirect-success'])) {
                    $ref = $nso['redirect-success'];
                } else {
                    $ref = S::scriptName();
                }
                $U->store();

                return S::redirect($ref);
            }
        }

        return Studio::error(404);
    }

    public function requestUserinfo($Client=null)
    {
        if($this->userinfo_endpoint) {
            if(!$Client) {
                $Client = $this->currentClient(['options.access_token'=>true]);
            }

            if($Client) {
                $H = static::$requestHeaders;
                if(static::$tokenHeaders) {
                    $H = array_merge($H, static::$tokenHeaders);
                }

                $o = $Client->options;
                if(!is_array($o)) $o = S::unserialize($o, 'json');
                if(!$o) $o = [];

                if(isset($o['access_token'])) {
                    $tt = (isset($o['token_type'])) ?ucfirst($o['token_type']) :'Bearer';
                    $H[] = 'authorization: '.$tt.' '.$o['access_token'];
                }
                $R = Api::runStatic($this->userinfo_endpoint, $this->issuer, null, 'GET', $H, 'json', true);

                $User = null;
                if($R && $this->user_key) {
                    $pks = (!is_array($this->user_key)) ?[$this->user_key] :$this->user_key;
                    $valid = true;
                    $q = [];
                    foreach($pks as $k) {
                        $key = ($this->user_map && isset($this->user_map[$k])) ?$this->user_map[$k] :$k;
                        if(is_null($val=S::extractValue($R, $key))) {
                            $valid = false;
                            break;
                        }
                        $q[$k] = $val;
                    }

                    if(!$valid || !$q) {
                        S::log('[INFO] Userinfo from '.$this->issuer.' does not contain '.implode(', ', $pks));
                        $R = null;
                    }

                    if($R) {
                        // fetch user to authenticate
                        if(S::$log) S::log('[DEBUG] find user '.S::serialize($q, 'json'));
                        if($User = S::user($q)) {
                            $User = $User->getObject();
                        }

                        if(!$User && $this->user_create) {
                            // create user
                            if($this->user_map) {
                                foreach($this->user_map as $k=>$p) {
                                    if(!is_null($v = S::extractValue($R, $p))) {
                                        $q[$k] = $v;
                                    }
                                }
                            }

                            if(S::$log) S::log('[DEBUG] Creating user '.S::serialize($q, 'json'));
                            $User = User::create($q);
                        } else if($User && $this->user_update  && $this->user_map) {
                            $save = false;
                            foreach($this->user_map as $k=>$p) {
                                if(!is_null($v = S::extractValue($R, $p)) && $User->$k!=$v) {
                                    $User->$k = $v;
                                    $save = true;
                                }
                            }

                            if($save) {
                                $User->save();
                            }
                        }

                        if($User) {
                            \tdz::log(__METHOD__, var_export($User, true));
                            $Client->user = $User->getPk();
                        }
                    }
                }

                if($R) {
                    $o = $Client->options;
                    if(!is_array($o)) $o = S::unserialize($o, 'json');
                    if(!$o) $o = [];
                    else if(isset($o['state'])) unset($o['state']);
                    $o['userinfo'] = $R;
                    $Client->options = $o;
                    $Client->save();
                }

                return $User;
            }
        }

    }

    public function requestToken($code=null, $User=null)
    {
        $Client = null;
        if($this->token_endpoint) {

            $U = S::getUser();
            $state = S::salt(10);
            $url = S::buildUrl(S::scriptName(true));
            $q = [
                'type'=>'authorization::'.$this->issuer,
                'id'=>$U->getSessionId(),
            ];
            $state = App::request('get', 'state');
            $Client = $this->currentClient(['options.state'=>$state]);

            if($Client) {
                $data = [
                    'code'=>$code,
                    'client_id'=>$this->client_id,
                    'client_secret'=>$this->client_secret,
                    'state'=>$state,
                    'redirect_uri'=>$url,
                ];

                $H = static::$requestHeaders;
                if(static::$tokenHeaders) {
                    $H = array_merge($H, static::$tokenHeaders);
                }
                $R = Api::runStatic($this->token_endpoint, $this->issuer, $data, 'POST', $H, 'json', true);

                if($R) {
                    $o = $Client->options;
                    if($this->scope) $o['scope'] = $this->scope;
                    if(!is_array($o)) $o = S::unserialize($o, 'json');
                    if(!$o) $o = [];
                    else if(isset($o['state'])) unset($o['state']);
                    $o = $R + $o;
                    $Client->options = $o;
                    $Client->save();
                }
            }
        }

        return $Client;
    }

    public function requestAuthorization()
    {
        $Client = null;
        if($this->authorization_endpoint) {
            $U = S::getUser();
            $state = S::salt(10);
            $url = S::buildUrl(S::scriptName(true));
            $Client = Storage::replace([
                'type'=>'authorization::'.$this->issuer,
                'id'=>$U->getSessionId(),
                'token'=>$this->issuer,
                'user'=>($U->isAuthenticated()) ?$U->uid() :null,
                'options'=>[
                    'state'=>$state,
                    'redirect_uri'=>$url,
                    'scope'=>$this->scope,
                ],
            ]);

            $args = ['client_id'=>$this->client_id, 'state'=>$state, 'redirect_uri'=>$url];
            if($this->scope) $args['scope'] = $this->scope;
            if($this->authorization_params) {
                $p = (is_string($this->authorization_params)) ?S::unserialize($this->authorization_params, 'json') :$this->authorization_params;
                if($p) $args += $p;
            }

            if(isset($this->issuer)) {
                // oidc requires scope
                if(!$this->scope) $args['scope'] = 'openid';
            }

            if(isset($this->response_types_supported)) {
                $args['response_type'] = 'code';
            }

            S::redirect(S::buildUrl($this->authorization_endpoint, [], $args));
        }

        return $Client;
    }
}
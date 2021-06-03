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

namespace Studio\Model;

use Studio\Model;
use Studio\OAuth2\Client as Client;
use Studio\OAuth2\Storage as Storage;
use Tecnodesign_Studio as Studio;
use Tecnodesign_Cache as Cache;
use Tecnodesign_App as App;
use tdz as S;

class Tokens extends Model
{
    public static $schema;
    protected $id, $type, $token, $user, $options, $created, $updated, $expires;

    public static $types=[
        'server'=>'OAuth2 Connector',
    ];


    public function __toString()
    {
        if(!$this->type || !$this->id) $this->refresh(['type', 'id']);

        $s = (isset(static::$types[$this->type])) ?Studio::t(static::$types[$this->type]) :$this->type;
        $s .= (($s) ?': ' :'').$this->id;

        return $s;
    }

    public function executeConnect($Interface=null)
    {
        if(!($p=S::urlParams()) && ($route = App::response('route'))) {
            S::scriptName($route['url']);
            $p = S::urlParams();
        }

        $base = S::scriptName();
        if($p && $Interface) {
            if($Interface['action']==$p[0]) $base .= '/'.array_shift($p);
            if($Interface['id']==$p[0]) $base .= '/'.array_shift($p);
        }

        $ref = null;
        if(App::request('get', 'ref') && (($url=App::request('headers', 'referer')) && substr($url, 0, strlen(S::scriptName()))!=S::scriptName())) {
            $ref = $url;
        } else if(($url=App::request('get', 'url')) && ($url=base64_decode($url))) {
            $ref = $url;
            // validate!
        }

        $this->refresh();
        $o = $this->asArray(Storage::$scopes['server']);
        $n = $this->id;
        if(!isset($o['button'])) $o['button'] = S::xml(sprintf(S::t('Sign in with %s', 'user'), $n));

        if(!isset($o['metadata']) && isset($o['issuer']) && preg_match('#^https?://#', $o['issuer'])) {
            $o['metadata'] = $o['issuer'].'/.well-known/openid-configuration';
        }
        if(isset($o['metadata']) && preg_match('#^https?://#', $o['metadata'])) {
            if(!($d=Cache::get($ckey='oauth2-meta/'.md5($o['metadata'])))) {
                $d = S::unserialize(file_get_contents($o['metadata']));
                if(!$d) $d = ['metadata'=>$o['metadata']];
                Cache::set($ckey, $d);
            }
            $o += $d;
            unset($d);
        }
        $Server = new Client($o);
        $Client = $Server->currentClient(['options.access_token'=>true, 'scope'=>$Server->scope]);

        if($code=App::request('get', 'code')) {
            $Client = $Server->requestToken($code);

            $U = S::getUser();
            if($ref=$U->getAttribute('authorize-source')) {
                $U->setAttribute('authorize-source', null);
            } else if(isset($nso) && isset($nso['redirect-success'])) {
                $ref = $nso['redirect-success'];
            } else {
                $ref = preg_replace('#/connect$#', '', S::scriptName());
            }

            $U->setMessage('<div class="z-i-msg z-i-success">'.sprintf(S::t('Successfully connected to <em>%s</em>.', 'interface'), S::xml($this->id)).'</div>');
            S::redirect($ref);
        } else if($ref) {
            $U = S::getUser();
            $U->setAttribute('authorize-source', $ref);
            $Client = $Server->requestAuthorization();
        } else {
            $msg = '<a data-action="redirect" data-url="'.S::xml($base.'?url={surl}').'"></a>';
            S::output($msg, 'text/html; charset=utf8', true);
        }
    }

    public function previewOptionsClientSecret()
    {
        if($s=$this['options.client_secret']) {
            return '****'.((strlen($s)>10) ?substr($s, -4) :'');
        }
    }

    public function validateOptionsClientSecret($v)
    {
        if(!$v && ($s=$this['options.client_secret'])) {
            return $s;
        }

        return $v;
    }

}
<?php
/**
 * Tecnodesign Facebook Authentication
 *
 * This package enables authentication & authorization for apps.
 *
 * PHP version 5.2
 *
 * @category  User
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @version   SVN: $Id: User.php 924 2011-10-19 13:31:49Z capile $
 * @link      http://tecnodz.com/
 */

/**
 * Tecnodesign Facebook Authentication
 *
 * This package enables authentication & authorization for apps.
 *
 * @category  User
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      http://tecnodz.com/
 */
class Tecnodesign_User_Facebook
{
    protected static $fb=null, $options=null;
    public $user=null;
    /**
     * Checks if there's any valid authentication method opened for current session
     * 
     * @param type $def 
     */
    public function __construct($o=null, $app=true, $env='prod')
    {
        if ($app && !is_object($app)) {
            $app = tdz::getApp();
        }
        self::$options=$o;
        try {
            $user = self::fb()->getUser();
            if($user) {
                $this->user = self::fb()->api('/me');
                if($this->user) {
                    $this->id = 'fb:'.$this->user['id'];
                }
            }
        } catch(Exception $e) {
            tdz::log($e->getMessage());
            $this->user = false;
        }
    }
    
    public static function options()
    {
        if(is_null(self::$options)) {
            $app = tdz::getApp();
            if(isset($app->user['ns']['fb']['options'])) {
                self::$options = $app->user['ns']['fb']['options'];
            }
        }
        return self::$options;
    }
    
    
    public function fb()
    {
        if(is_null(self::$fb)) {
            $o = self::options();
            if(!class_exists('Facebook')) {
                require_once TDZ_ROOT.'/src/facebook/facebook.php';
            }
            if(session_status()==2) {
                session_destroy();
            }
            $sn = (isset($o['cookie']))?($o['cookie']):('tdzid');
            if($sn!=session_name()) {
                @session_name($sn);
            }
            unset($sn);
            self::$fb = new Facebook(array(
                'appId'  => $o['app_id'],
                'secret' => $o['app_secret'],
                //'cookie' => true,
            ));
        }
        return self::$fb;
        
    }
    
    public function asArray()
    {
        if($this->isAuthenticated()) {
            return $this->user;
        } else {
            return array();
        }
    }

    public function __toString()
    {
        $s = '';
        if($this->isAuthenticated()) {
            $s = $this->user['name'];
        }
        return $s;
    }
    
    public function preview()
    {
        $s = '';
        if(isset($_GET['fb-action']) && $_GET['fb-action']=='signout') {
            $user = tdz::getUser();
            if($user) {
                $user->destroy();
            }
            $_SESSION=array();
            session_destroy();
            $url = (isset($_GET['next']))?($_GET['next']):(tdz::scriptName());
            tdz::redirect($url);
        } else if(isset($_GET['state']) && isset($_GET['code'])) {
            tdz::redirect(tdz::scriptName(true));
        }
        if($this->isAuthenticated()) {
            $s = '<div id="button-fb" class="tdz-profile">'
                . '<span class="tdz-profile-picture fb" style="background:url(//graph.facebook.com/'.$this->user['id'].'/picture) no-repeat;"><span class="tdz-icon fb-overlay"></span></span>'
                . '<span class="tdz-profile-name">'.tdz::xmlEscape($this->name).'</span>'
                . '<a href="'.tdz::xmlEscape($this->getLogoutUrl()).'" class="tdz-profile-sign-out fb">'.tdz::t('Sign out', 'user').'</a>'
                . '</div>';
        } else {
            $s = '<div id="button-fb" class="tdz-sign-in">'
                . '<a href="'.tdz::xmlEscape($this->getLoginUrl()).'" class="tdz-profile-sign-in fb"><span class="tdz-icon fb-overlay"></span>'.tdz::t('Sign in', 'user').'</a>'
                . '</div>';
        }
        return $s;
    }
    
    public function getLoginUrl()
    {
        return $this->fb()->getLoginUrl();
    }

    public function getLogoutUrl()
    {
        return tdz::scriptName(true).'?next='.urlencode(tdz::requestUri()).'&fb-action=signout';
        //return $this->fb()->getLogoutUrl();
    }
    
    public function signIn()
    {
        return $this->preview();
    }
    
    public function isAuthenticated()
    {
        if(is_null($this->user)) {
            $this->user=self::fb()->getUser();
            if($this->user) {
                $this->id = 'fb:'.$this->user['id'];
            }

        }
        
        return (bool)$this->user;
    }

    public function __get($name)
    {
        if (!$this->isAuthenticated()) {
            return false;
        }
        /*
        if (!is_null($this->_map) && isset($this->_map[$name])) {
            $name = $this->_map[$name];
        }
        */
        if($name=='id') {
            return 'fb:'.$this->user[$name];
        }
        $value = false;
        if(isset($this->user[$name])) {
            $value = $this->user[$name];
        }
        return $value;
    }

}
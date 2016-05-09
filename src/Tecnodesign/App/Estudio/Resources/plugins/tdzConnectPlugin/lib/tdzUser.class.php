<?php

class tdzUser extends sfBasicSecurityUser
{
    private static $_current = null;
  protected $initialized=false;
  protected $super_admin=null;
  protected $ns=array();
  protected $loaded_credentials=false;
  public $authenticated=false;

  public static function getAllCredentials()
  {
    $cs=sfConfig::get('app_connect_credentials');
    foreach($cs as $k=>$v)
    {
      if(substr($k,0,1)=='-' && isset($cs[substr($k,1)]))
      {
        unset($cs[$k]);
        unset($cs[substr($k,1)]);
      }
      else if($v=='') $cs[$k]=$k;
    }
    return $cs;
  }

  public function isAuthenticated()
  {
    return $this->authenticated;
  }
 
    /**
     * Connect initializer
     *
     * This method should loop all app_connect_ns, check whether it's being used or not
     * and set
     *
     * $this->authenticated
     * $this->credentials
     * $this->id
     *
     * @param sfEventDispatcher $dispatcher
     * @param sfStorage $storage
     * @param <type> $options
     */
    public function initialize(sfEventDispatcher $dispatcher, sfStorage $storage, $options = array()) {
        // initialize parent
        if ($this->initialized) {
            return true;
        }
        $this->initialized = true;
        parent::initialize($dispatcher, $storage, $options);
        if (!array_key_exists('timeout', $this->options)) {
            $this->options['timeout'] = 1800;
        }

        // force the max lifetime for session garbage collector to be greater than timeout
        if (ini_get('session.gc_maxlifetime') < $this->options['timeout']) {
            ini_set('session.gc_maxlifetime', $this->options['timeout']);
        }

        $ns = sfConfig::get('app_connect_ns');
        if (is_array($ns)) {
            $this->authenticated = false;
            foreach ($ns as $m => $o) {
                if (!isset($o['enabled']) || !$o['enabled']
                    )continue;
                $me = $m . 'Initialize';
                $opt = (isset($o['options'])) ? (array_merge($options, $o['options'])) : ($options);
                if (method_exists($this, $me) && $this->$me($dispatcher, $storage, $opt)) {
                    $this->ns[$m]['options'] = $opt;
                    if ($this->authenticated) {
                        break;
                    }
                }
            }
        }
        if (null === $this->authenticated) {
            $this->authenticated = false;
            //$this->credentials   = array();
        } else if ($this->authenticated) {
            // Automatic logout logged in user if no request within timeout parameter seconds
            $timeout = $this->options['timeout'];
            if (false !== $timeout && null !== $this->lastRequest && time() - $this->lastRequest >= $timeout) {
                if ($this->options['logging']) {
                    $this->dispatcher->notify(new sfEvent($this, 'application.log', array('Automatic user logout due to timeout')));
                }

                $this->setTimedOut();
                $this->setAuthenticated(false);
            }
        }
        // record access to database
        /*
          // store in database
          if($this->authenticated && sfConfig::get('app_connect_use_database'))
          {
            $conn=array(
             'username'=>$session['auth-user'],
             'ns'=>'tupinamba',
             'name'=>$session['name'],
             'hash'=>$sessionid,
             'details'=>false,
            );
            $this->updateAccess($conn);
          }
         */
        //tdz::debug('auth: '.$this->authenticated,$this->getAttribute('name'),$this->getCredentials(), false);

        $this->lastRequest = time();
        $this->setCurrent();
    }

    public static function getCurrent($class='tdzUser')
    {

        $user = false;
        if (!is_null(tdzUser::$_current)) {
            $user = tdzUser::$_current;
        }
        if (!$user) {
            $user = new $class();
        }
        return $user;
    }
    public function setCurrent()
    {
        if (!is_null(tdzUser::$_current)) {
            unset(tdzUser::$_current);
        }
        tdzUser::$_current =& $this;
    }


  public function tupinambaInitialize(sfEventDispatcher $dispatcher, sfStorage $storage, &$o)
  {
    if(!isset($o['session-save-path']) || $o['session-save-path']=='') $o['session-save-path']=session_save_path();
    if(!isset($o['storage']) || $o['storage']=='') $o['storage']='cookie';
    $sessionkey=(isset($o['session-password']))?($o['session-password']):(sfConfig::get('sf_csrf_secret'));
    $session=false;
    $sessionid=false;
    if(!isset($o['cookie-name']) || $o['cookie-name']=='') $o['cookie-name']=session_name();
    $cookies=array();
    if(isset($_SERVER['HTTP_COOKIE']))
    {
      $rawcookies=preg_split('/\;\s*/', $_SERVER['HTTP_COOKIE'], null, PREG_SPLIT_NO_EMPTY);
      foreach($rawcookies as $cookie)
      {
        list($cname, $cvalue)=explode('=', $cookie, 2);
        if(trim($cname)==$o['cookie-name'])
          $cookies[]=$cvalue;
      }
    }
    if(count($cookies)==0 && isset($_COOKIE[$o['cookie-name']]))$cookies[]=$_COOKIE[$o['cookie-name']];
    //tdz::debug($o, $_SERVER['HTTP_COOKIE'], false);
    foreach($cookies as $sessionid)
    {
      //$sessionid = (isset($_COOKIE[$o['cookie-name']]))?($_COOKIE[$o['cookie-name']]):('');
      if($o['storage'] == 'cookie'){
        if(isset($_COOKIE[$o['cookie-name'].'_data'])) $session=tdz::decrypt($_COOKIE[$o['cookie-name'].'_data'], $sessionkey.$sessionid);
      } else if($sessionid != '' && is_readable($o['session-save-path'].'/'.$sessionid)) {
        if(is_file($o['session-save-path'].'/'.$sessionid)){
          $data=file_get_contents($o['session-save-path'].'/'.$sessionid);
          $unserialization_mode=sfConfig::get('app_tdz_unserialization_mode');
          if($unserialization_mode!='' && function_exists($unserialization_mode))$session=$unserialization_mode($data);
          else $session=unserialize($data);
          $swrite=$session;
          $redirect=false;
          if(isset($session['redirect']))
          {
            $redirect=$session['redirect'];
            unset($session['redirect']);
          }
          $swrite['last-access']=time();
          $serialization_mode=sfConfig::get('app_tdz_serialization_mode');
          if($serialization_mode!='' && function_exists($serialization_mode))$swrite=$serialization_mode($swrite);
          else $swrite=serialize($swrite);
          tdz::save($o['session-save-path'].'/'.$sessionid, $swrite);
          //if($redirect && count($_POST)>0)
        }
      }
      if($session)
      {
        if(isset($_COOKIE[$o['cookie-name']]) && $_COOKIE[$o['cookie-name']]!=$sessionid)
          $_COOKIE[$o['cookie-name']]=$sessionid;
        break;
      }
    }
    /*
    if(!$session && $this->hasAttribute('session-object'))
    {
      $session=$this->getAttribute('session-object');
      if($this->options['timeout'] > time() - $this->lastRequest)
      {
        $session['last-access']=$this->lastRequest;
      }
    }
    */

    if($sessionid!='' && isset($o['timeout']) && $o['timeout']>0)
    {
      // reissue cookie
      $timeout=$o['timeout']+time();
      $domain=(isset($o['cookie-domain']) && $o['cookie-domain']!='')?($o['cookie-domain']):(null);
      @setcookie ($o['cookie-name'], $sessionid, $timeout, '/', $domain);
    }
    $prefix = (isset($o['prefix']))?($o['prefix']):('tupi:');
    if(is_array($session))
    {
      $userid=(isset($session['auth-user']))?($prefix.$session['auth-user']):(null);
      if($this->hasAttribute('id') && $userid!=$this->getAttribute('id'))
        $this->clearAttributes();
      if(isset($session['auth-user']) && $session['auth-user']!='')
      {
        $this->authenticated=true;
        $this->setAttribute('id',$prefix.$session['auth-user']);
        $this->setAttribute('name',tdz::encode($session['name']));
        $this->setAttribute('session-object',$session);
        $this->setAttribute('session-id',$sessionid);
        $this->lastRequest=$session['last-access'];
        $this->loaded_credentials=false;
      }
      else
      {
        $this->authenticated=false;
        $this->setAttribute('id',null);
        $this->setAttribute('name',null);
        $this->setAttribute('session-object',$session);
        $this->lastRequest=$session['last-access'];
        $this->loaded_credentials=false;
      }

      return true;
    }
  }
  
  public function clearAttributes()
  {
    $this->getAttributeHolder()->clear();
  }

  public function hostInitialize(sfEventDispatcher $dispatcher, sfStorage $storage, &$o)
  {
    $uf=false;
    $u=array();
    if(!isset($_SERVER['REMOTE_ADDR'])) return false;
    $id='host:'.$_SERVER['REMOTE_ADDR'];
    if(isset($o['user']) && file_exists($o['user'])) $uf=$o['user'];
    else if(isset($o['user']) && file_exists(sfConfig::get('sf_app_config_dir').'/'.$o['user'])) $uf=sfConfig::get('sf_app_config_dir').'/'.$o['user'];
    else if(isset($o['user']) && file_exists(sfConfig::get('sf_plugins_dir').'/tdzConnectPlugin/config/'.$o['user'])) $uf=sfConfig::get('sf_plugins_dir').'/tdzConnectPlugin/config/'.$o['user'];
    if($uf)
      $u=sfYaml::load($uf);
    if(!$uf)return false;
    $this->loaded_credentials=true;
    $this->clearCredentials();
    $this->setAttribute('id',$id);
    if(!isset($u[$id]))
    {
      $this->authenticated=false;
    }
    else
    {
      $this->authenticated=true;
      $c=$u[$id]['group'];
      if(!is_array($c))$c=array($c);
      $this->credentials=$c;
      $gf=false;
      if(isset($o['group']) && file_exists($o['group'])) $gf=$o['group'];
      else if(isset($o['group']) && file_exists(sfConfig::get('sf_app_config_dir').'/'.$o['group'])) $gf=sfConfig::get('sf_app_config_dir').'/'.$o['group'];
      else if(isset($o['group']) && file_exists(sfConfig::get('sf_plugins_dir').'/tdzConnectPlugin/config/'.$o['group'])) $gf=sfConfig::get('sf_plugins_dir').'/tdzConnectPlugin/config/'.$o['group'];
      if($gf)
      {
        $g=sfYaml::load($gf);
        if(is_null($this->super_admin)) $this->super_admin=array();
        foreach($g as $gk=>$gv)
          if(isset($gv['isSuperAdmin']) && $gv['isSuperAdmin'])
          {
            $this->super_admin[]=$gk;
            break;
          }
      }
    }
    return true;
  }

  /**
   * Checks if user is SuperAdmin
   */
  public function isSuperAdmin()
  {
    if(!$this->authenticated) return false;
    if(is_null($this->super_admin))
    {
      $this->super_admin=sfConfig::get('app_connect_super_admin');
      if(!is_array($this->super_admin) && $this->super_admin)$this->super_admin=array($this->super_admin);
      else if(!is_array($this->super_admin) || count($this->super_admin)==0) $this->super_admin=false;
    }
    if(!$this->loaded_credentials) $this->getCredentials();
    if(!is_array($this->super_admin)) return false;
    foreach($this->super_admin as $credential)
    {
      if(in_array($credential, $this->credentials)) return true;
    }
    return false;
  }

  /**
   * Credential lazy loading: if !$this->loaded_credentials, loads $this->getCredentials
   *
   * @param  mixed $credentials
   * @param  bool  $useAnd       specify the mode, either AND or OR
   * @return bool
   */
  public function  hasCredential($credentials, $useAnd=true)
  {
    if(!$this->loaded_credentials) $this->getCredentials();
    if(null === $this->credentials || count($this->credentials)==0)
      return false;

    if($this->isSuperAdmin()) return true;
    if (!is_array($credentials))
    {
      return in_array($credentials, $this->credentials);
    }
    // now we assume that $credentials is an array
    $test = false;
    foreach ($credentials as $credential)
    {
      // recursively check the credential with a switched AND/OR mode
      $test = in_array($credential, $this->credentials);
      if($useAnd && !$test)
        return false;
      else if(!$useAnd && $test)
        return true;

    }

    return $test;
  }

  /**
   * Credential loading: must check the current connection and browse for credentials
   *
   * @return array
   */
  public function getCredentials()
  {
    if(!$this->loaded_credentials)
    {
      $this->loaded_credentials=true;
      $this->clearCredentials();
      $ns = false;
      $uid = false;
      $id = false;

      if($this->authenticated && $this->hasAttribute('id'))
      {
          $uid = $this->getAttribute('id');
          // $this->id should be $ns:$id
          list($ns,$id)=preg_split('/\:/',$uid,2);
          $method = "{$ns}GetCredentials";
          if(method_exists($this, $method)) {
              return $this->$method();
          }
      }
      $uf=false;
      if ($uid && file_exists(sfConfig::get('sf_app_config_dir').'/'.$ns.'-user.yml')) {
          $uf=sfConfig::get('sf_app_config_dir').'/'.$ns.'-user.yml';
      } else if ($uid && file_exists(sfConfig::get('sf_app_config_dir').'/user.yml')) {
          $uf=sfConfig::get('sf_app_config_dir').'/user.yml';
      }
      if($uf) {
          $this->credentials=array();
          $u=sfYaml::load($uf);
          if (is_array($u) && isset($u[$uid]['group'])) {
              $this->credentials = $u[$uid]['group'];
          }
      } else {
          parent::getCredentials();
      }
    }
    return $this->credentials;
  }

  /**
   * Tupinamba credential loading
   *
   * This method uses specific configuration. For more information, please check app.yml
   */
  public function tupinambaGetCredentials()
  {
    $o=array();
    if(isset($this->ns['tupinamba']['options']))$o=$this->ns['tupinamba']['options'];
    $uprefix = (isset($o['prefix']))?($o['prefix']):('tupinamba:');
    if(!$this->authenticated || substr($this->getAttribute('id'),0,strlen($uprefix))!=$uprefix) return false;
    $id=substr($this->getAttribute('id'),10);
    $this->credentials = array();
    try {
      $conn = Doctrine_Manager::getInstance()->getConnection($o['database-connection']);
      $sql = "select u.{$o['table-users-group']} as g from {$o['database']}.{$o['table-users']} as u where u.{$o['table-users-id']}=:id";
      $q = $conn->prepare($sql);
      $q->bindValue('id',$id);
      $q->execute();
      $credentials=$q->fetchAll(Doctrine_Core::FETCH_ASSOC);
      if(!is_array($this->credentials))
        $this->credentials=array();
      $prefix = (isset($o['credentials_prefix']))?($o['credentials_prefix']):('t');
      foreach($credentials as $c)
      {
        $g=$prefix.$c['g'];
        if(isset($o['credential-groups'][$g]))
        {
          if(!is_array($o['credential-groups'][$g]))$o['credential-groups'][$g]=array($o['credential-groups'][$g]);
          foreach($o['credential-groups'][$g] as $nc)
            if($nc!='' && !in_array($nc,$this->credentials)) $this->credentials[]=$nc;
        }
        else if(!in_array($g,$this->credentials))
          $this->credentials[]=$g;
      }
    } catch(Exception $e) {
      tdz::log('[ERROR] '.__METHOD__.': '.$e);
    }

    return $this->credentials;
  }


  public static function signIn($o=array())
  {
    $w=sfConfig::get('app_connect_widgets');
    $ws=$w['signin'];
    $tpldir=sfConfig::get('sf_app_template_dir');
    if($ws['template'] && (file_exists($ws['template'].'.php') || file_exists($tpldir.'/'.$ws['template'].'.php')))
      $tpl=(file_exists($ws['template']))?($ws['template']):($tpldir.'/'.$ws['template']);
    else
      $tpl=sfConfig::get('sf_plugins_dir').'/tdzConnectPlugin/modules/tdz_connect/templates/signIn';

    $o=array('script'=>$tpl.'.php','variables'=>$ws);
    $o['variables']['signin']=sfConfig::get('app_connect_signin_route');
    $o['variables']['signout']=sfConfig::get('app_connect_signout_route');
    $ns=sfConfig::get('app_connect_ns');
    foreach($ns as $n=>$v)
      if(!$v['enabled']) unset($ns[$n]);
    $o['variables']['user']=sfContext::getInstance()->getUser();
    $o['variables']['ns']=$ns;
    $o['variables']['icon']=sfConfig::get('app_connect_icons');
    return tdz::exec($o);
  }

  public function fbInit($o=array())
  {
    $fb=$this->getAttribute('fb');
    if($fb && !is_object($fb)) return $fb;
    if(!isset($o['app_id']))
    {
      $ns=sfConfig::get('app_connect_ns');
      $o=$ns['fb']['options'];
    }
    if(!class_exists('Facebook'))
      require_once sfConfig::get('sf_plugins_dir').'tdzConnectPlugin/lib/facebook/facebook.php';
    $fb = new Facebook(array(
      'appId'  => $o['app_id'],
      'secret' => $o['app_secret'],
      'cookie' => true,
    ));
    $this->getAttribute('fb', $fb);
    return $fb;
  }

  public function fbSignIn($o=array(), $request=null)
  {
    $fb=$this->fbInit($o);
    $session = $fb->getSession();
    if($request->getGetParameter('type')=='js')
    {
      $opts=(isset($o['js-options']))?($o['js-options']):(array());
      $opts['appId']=$o['app_id'];
      $opts['session']=$session;
      $opts=json_encode($opts,JSON_FORCE_OBJECT);
      $perms=(isset($o['extended-permissions']))?($o['extended-permissions']):('');
      if($perms!='')$perms=", {\"perms\": \"{$perms}\"}";
      $s ="FB.init($opts);\nFB.getLoginStatus(tdz.fbConnectResponse);\n";
      $s.= "\$('#button-fb').unbind('click').bind('click',function(){FB.login(tdz.fbConnectResponse{$perms});return false;});";
      header('Content-Type: text/javascript; charset=UTF-8');
      header('Content-Length: '.strlen($s));
      exit($s);
    }
    $fbo=(isset($o['api_options']))?($o['api_options']):(array());
    if(false && $session)
      $url = $fb->getLogoutUrl($fbo);
    else
      $url = $fb->getLoginUrl($fbo);
    tdz::redirect($url);
  }
  public function fbSignOut($o=array(), $request=null)
  {
    $fb=$this->fbInit($o);
    $session = $fb->getSession();
    if ($request->getGetParameter('type')=='js') {
      $opts=(isset($o['js-options']))?($o['js-options']):(array());
      $opts['appId']=$o['app_id'];
      $opts['session']=$session;
      $opts=json_encode($opts,JSON_FORCE_OBJECT);
      $perms=(isset($o['extended-permissions']))?($o['extended-permissions']):('');
      if($perms!='')$perms=", {\"perms\": \"{$perms}\"}";
      $s ="FB.init($opts);\nFB.getLoginStatus(tdz.fbConnectResponse);\n";
      //$s.= "\$('#signout').unbind('click').bind('click',function(){FB.logout(tdz.fbConnectResponse{$perms});return false;});";
      header('Content-Type: text/javascript; charset=UTF-8');
      header('Content-Length: '.strlen($s));
      exit($s);
    }
    $fbo=(isset($o['api_options']))?($o['api_options']):(array());
    if(false && $session)
      $url = $fb->getLogoutUrl($fbo);
    else
      $url = $fb->getLoginUrl($fbo);
    tdz::redirect($url);
  }

    public function fbInitialize(sfEventDispatcher $dispatcher, sfStorage $storage, &$o)
    {
        $prefix='fb:';
        $fb=$this->fbInit($o);
        $session = $fb->getSession();
        $me = null;
        // Session based API call.
        if ($session) {
            $this->authenticated=true;
            $this->setAttribute('id',$prefix.$session['uid']);
            $this->setAttribute('session-object',$session);
            //$this->lastRequest=$session['last-access'];
            $this->loaded_credentials=false;
            $me=$this->getAttribute('me');
            if (!$me) {
                try {
                    $uid = $fb->getUser();
                    $me = $fb->api('/me');
                } catch (FacebookApiException $e) {
                    error_log($e);
                }
                $this->setAttribute('me',$me);
            }
            $this->setAttribute('name',$me['name']);
            //tdz::debug('ok!', $email, $session, $me, $fb);
        }
    }

  /**
   * Display small public profile
   *
   * @return array
   */
  public function preview()
  {
    if(!$this->authenticated || !$this->hasAttribute('id'))
      return false;
    list($ns,$id)=preg_split('/\:/',$this->getAttribute('id'),2);
    $method = "{$ns}Preview";
    if(method_exists($this, $method))
      return $this->$method();
    else
      return '<p>'.$this->getAttribute('name').'</p>';
  }

  public function fbPreview()
  {
    $fb=$this->fbInit($o);
    $uid=$fb->getUser();
    $s = '';
    $s .= '<div id="button-fb" class="profile-picture" data-signout="'.sfConfig::get('app_connect_signout_route').'/fb" style="background:url(//graph.facebook.com/'.$uid.'/picture) no-repeat;"><img src="'.sfConfig::get('app_connect_icons').'" alt="Facebook" title="Facebook" /></div>';
    $s .= '<p>'.$this->getAttribute('name').'</p>';
    return $s;
  }

  /**
   *
   * @param <type> $o array(
   *      'person'=>$session['auth-user'],
   *      'ns'=>'tupinamba',
   *      'username'=>$session['name'],
   *      'hash'=>$sessionid,
   *      'details'=>false,
   *     );
   *
   */
  public function updateAccess($o=array())
  {
    $connect=tdzConnect::getConnection($o);
    $access=$connect->updateAccess();
    if($connect->hash!=$o['hash'])
    {
      $connect->hash=$o['hash'];
      $connect->save();
    }
  }

}

<?php
/**
 * Tecnodesign Toolkit
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: tdzToolKit.class.php 682 2011-02-19 20:00:41Z capile $
 */

class tdzToolKit
{


  /**
   * Text to Slug
   * @param string $str   Text to convert to slug
   * @return string slug
   */
  public static function textToSlug($str)
  {
    $table = array(
      'Š'=>'S', 'š'=>'s', 'Đ'=>'Dj', 'đ'=>'dj', 'Ž'=>'Z', 'ž'=>'z', 'Č'=>'C', 'č'=>'c', 'Ć'=>'C', 'ć'=>'c',
      'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
      'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
      'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss',
      'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e',
      'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o',
      'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b',
      'ÿ'=>'y', 'Ŕ'=>'R', 'ŕ'=>'r',
    );
    $str = strtr($str, $table);
    $str = strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9-_]+/', '-', $str);
    $str = preg_replace('/-+/', '-', $str);
    $str = preg_replace('/^-|-$/', '', $str);
    return $str;
  }


  /**
   * Format bytes to human read
   *
   * @param float $bytes
   * @param integer $precision
   * @return string
   */
  public static function formatBytes($bytes, $precision = 2)
  {
      $units = array('B', 'Kb', 'Mb', 'Gb', 'Tb');

      $bytes = max($bytes, 0);
      $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
      $pow = min($pow, count($units) - 1);

      $bytes /= pow(1024, $pow);

      return round($bytes, $precision) . ' ' . $units[$pow];
  }


  public static function formatTable($arr, $arg=array())
  {
    $class = (isset($arg['class']))?(" class=\"{$arg['class']}\""):('');
    $s = '<table cellpadding="0" cellspacing="0" border="0"'.$class.'><tbody>';
    $class = 'odd';
    $empty=(isset($arg['hide_empty']))?($arg['hide_empty']):(false);
    $ll=false;
    foreach($arr as $label=>$value)
    {
      if($value===false)
      {
        if($ll!==false)
          $s = str_replace($ll, '', $s);

        $ll = '<tr><th colspan="2" class="legend">'.$label.'</th></tr>';
        $s .= $ll;
        $class = 'odd';
      }
      else if($empty && trim(strip_tags($value))=='')
      {
      }
      else
      {
        $ll = false;
        $s .= '<tr class="'.$class.'"><th>'.$label.'</th><td>'.$value.'</td></tr>';
        $class = ($class=='even')?('odd'):('even');
      }
    }
    if($ll!==false)
      $s = str_replace($ll, '', $s);
    $s .= '</tbody></table>';
    return $s;
  }

  public static function downloadFile($response, $params)
  {
    $response->clearHttpHeaders();
    $response->setHttpHeader('Pragma: public', true);
    $response->setContentType($params['mimetype']);
    $response->setHttpHeader('Content-Disposition', 'attachment; filename="'.$params['filename'].'"');
    $response->sendHttpHeaders();
    $response->setContent(readfile($params['file']));

    return true;
  }

  public static function addIssue($model, $module, $action, $data, $firstuser, $info = '', $message = '')
  {
    //convert data to yaml
    $yml = sfYaml::dump(array('action' => $action, 'model' => $model, 'module' => $module, 'info' => $info, 'data' => $data ),1);
    //exit(var_dump($yml));
    $issue = New Issues();
    $issue['issue'] = ($message == '') ? ( ($action == 'edit') ? ('Update request for: '.$model) : ('New: '.$model) ) : ($message);
    $issue['status'] = 1;
    $issue['date_start'] = date('Y-m-d H:i:s');
    $issue['issue_data'] = $yml;
    $issue['team'] = $firstuser->getTeam();
    $issue['request_by'] = $firstuser->getId();

    if($issue->isValid())
    {
      $issue->save();
      return true;
    }
    return false;
  }

  public static function formatForm($module, $form, $customfields = '')
  {
    $schema = $form->getFormFieldSchema();
    $config = sfYaml::load(sfConfig::get('sf_root_dir').'/apps/internal/modules/'.$module.'/config/generator.yml');
    $formfields = $config['generator']['param']['config']['form']['display'];

    if ($customfields != '')
      $formfields = array_merge($formfields,$customfields);

    foreach($formfields as $fk => $fv)
    {
      foreach($fv as $k => $v)
      {
        if ($schema->offsetExists($v))
        {
          $field[$v] = $schema->offsetGet($v)->getWidget();

          $attold = $field[$v]->getAttributes();
          $attfld = (isset($config['generator']['param']['config']['fields'][$v]['attributes'])) ? ($config['generator']['param']['config']['fields'][$v]['attributes']) : (array());
          $attform = (isset($config['generator']['param']['config']['form']['fields'][$v]['attributes'])) ? ($config['generator']['param']['config']['form']['fields'][$v]['attributes']) : (array());
          $att = array_merge($attold,$attfld,$attform);
          if (is_array($att) && count($att) > 0)
            $field[$v]->setAttributes($att);

          $optold = $field[$v]->getOptions();
          $optfld = (isset($config['generator']['param']['config']['fields'][$v])) ? ($config['generator']['param']['config']['fields'][$v]) : (array());
          $optform = (isset($config['generator']['param']['config']['form']['fields'][$v])) ? ($config['generator']['param']['config']['form']['fields'][$v]) : (array());
          unset($optfld['attributes'], $optform['attributes'], $optfld['order_by'], $optform['order_by']);
          $opt = array_merge($optold,$optfld,$optform);
          if (is_array($opt) && count($opt) > 0)
            $field[$v]->setOptions($opt);
        }
        else
        {
          unset($formfields[$fk][$k]);
        }
      }
    }

    foreach($formfields as $k => $v)
    {
      //var_dump($k, $v);
      if (!is_array($v) || count($v) <= 0)
        unset($formfields[$k]);
    }
    
    $form->useFields(array_keys($field),true);

    $ret['form'] = $form;
    $ret['formfields'] = $formfields;
    $ret['field-schema'] = $field;

    return $ret;
  }

  public static function buildUrl($url, $parts=array())
  {
    if(!is_array($url))$url=parse_url($url);
    $url += $parts;
    $url += array(
      'scheme'=>($_SERVER['SERVER_PORT']=='443')?('https'):('http'),
      'host'=>(sfConfig::get('hostname'))?(sfConfig::get('hostname')):($_SERVER['HTTP_HOST']),
      'path'=>'/',
    );
    $s='';
    $s = $url['scheme'].'://';
    if(isset($url['user'])||isset($url['pass']))
    {
      $s .= urlencode($url['user']);
      if(isset($url['pass']))$s .= ':'.urlencode($url['pass']);
        $s .='@';
    }
    $s .= $url['host'];
    if(isset($url['port']))$s .= ':'.$url['port'];
    $s .= $url['path'];
    if(isset($url['query']))$s .= '?'.$url['query'];
    if(isset($url['fragment']))$s .= '#'.$url['fragment'];
    return $s;
  }


  public static function formatUrl($url, $hostname='', $http='')
  {
    $s = '';
    if($http=='')$http = ($_SERVER['SERVER_PORT']=='443')?('https://'):('http://');
    if($hostname=='')$hostname = (sfConfig::get('hostname'))?(sfConfig::get('hostname')):($_SERVER['HTTP_HOST']);
    $url = trim($url);
    if(preg_match('/[\,\n]/', $url))
    {
      $urls = preg_split("/([\s\]]*[\,\n][\[\s]*)|[\[\]]/", $url, -1, PREG_SPLIT_NO_EMPTY);
      foreach($urls as $k=>$v)
      {
        $v = tdzToolKit::formatUrl($v);
        if($v=='')unset($urls[$k]);
      }
      return implode(', ',$urls);
    }
    if($url=='')
      $s = '';
    else if(preg_match('/^mailto\:\/*(.*)/', $url, $m))// email
      $s = '<a href="'.htmlentities($url).'">'.$host.htmlentities($m[1]).'</a>';
    else if(preg_match('/^[a-z0-9\.\-\_]+@/i', $url))// email
      $s = '<a href="mailto:'.htmlentities($url).'">'.htmlentities($url).'</a>';
    else if(!preg_match('/^[a-z]+\:\/\//', $url))// absolute
      if(!preg_match('/^[^\.]+\.[^\.]+/', $url)) // without host
        $s = '<a href="'.htmlentities($url).'">'.$http.$host.htmlentities($url).'</a>';
      else
        $s = '<a href="'.$http.htmlentities($url).'">'.$http.htmlentities($url).'</a>';
    else 
      $s = '<a href="'.htmlentities($url).'">'.htmlentities($url).'</a>';
    return $s;
  }

  public function getCountryNames($str, $flag = true)
  {
    $values = preg_split("/([\s\]]*\,[\[\s]*)|[\[\]]/", $str, -1, PREG_SPLIT_NO_EMPTY);

    $keys = array();
    foreach($values as $value)
      if($value!='' && $value!='-1')$keys[]=$value;

    $s = '';
    $sep = ($flag)?(' '):(', ');
    if(count($keys)>0)
    {
      $countries = Doctrine::getTable('Countries')->createQuery('c')->whereIn('c.id', $keys)->orderBy('c.country asc')->fetchArray();
      $cs=array();
      $cns=array();
      foreach($countries as $cn)
      {
        if($flag)
        {
          $cs[] = '<img src="/_images/countries/'.strtolower($cn['id']).'.gif" alt="'.$cn['country'].'" title="'.$cn['country'].'" class="flag" />';
          $cns[] = $cn['country'];
        }
        else
          $cs[] = $cn['country'];

      }
      $s = implode($sep, $cs);
      if($flag > 1)
        $s = implode(', ', $cns).$s;
    }
    return $s;
  }

  public function getMultiple($str, $table, $display='', $pk='id',$order='',$tpl='')
  {
    $values = preg_split("/([\s\]]*\,[\[\s]*)|[\[\]]/", $str, -1, PREG_SPLIT_NO_EMPTY);

    $keys = array();
    foreach($values as $value)
      if($value!='' && $value!='-1')$keys[]=$value;

    $s = '';
    if(count($keys)>0)
    {
      if($order=='')$order=$display;
      if($order=='')$order=$pk;
      $items = Doctrine::getTable($table)->createQuery('c')->whereIn('c.'.$pk, $keys)->orderBy('c.'.$order.' asc')->fetchArray();
      $cs=array();
      foreach($items as $cn)
      {
        if($display)
          $cs[] = $cn[$display];
        else
          $cs[]=array_pop($cn);
      }
      if(count($cs) > 0)
      {
        if($tpl=='list')
          $s = '<ul><li>'.implode('</li><li>', $cs).'</li></ul>';
        else
          $s = implode(', ', $cs);
      }
    }
    return $s;
  }
  public static function formWidget($fc,$form=false)
  {
    $type=(isset($fc['type']))?(array_flip(preg_split('/\s+/',$fc['type'],false,PREG_SPLIT_NO_EMPTY))):(array());
    // field not yet created
    $w = $fc;
    $a=array();
    if(isset($w['type']))$a['class']=$w['type'];
    unset($w['type']);
    if(isset($type['multiple']))$w['multiple']=(bool)$type['multiple'];
    if(isset($fc['query']) && !isset($type['text']))
    {
      if(isset($w['model']))
      {
        $model = $w['model'];
        unset($w['model']);
      }
      unset($w['query']);
      $w['choices'] = array();
      $wd=false;
      if(!isset($connection))
        $connection = Doctrine::getConnectionByTableName($model);
      $tbl = $connection->prepare($fc['query']);
      $tbl->execute();
      $data = $tbl->fetchAll();
      foreach($data as $k=>$v)
      {
        if(isset($v['id']))
          $w['choices'][$v['id']]=$v[1];
        else
          $w['choices'][$v[0]]=$v[1];
      }
      //$config[$fn][$fc]['choices']=$w['choices'];
      $wd = new sfWidgetFormChoice($w, $a);
    }
    else if(isset($fc['model']) && isset($fc['method']))
    {
      $model = $w['model'];
      unset($w['model']);

      $method = $w['method'];
      unset($w['method']);
      $fn="{$model}::{$method}";
      $w['choices'] = $fn();
      if(is_object($w['choices']))
      {
        $c=array();
        foreach($w['choices'] as $option)
          $c[$option->getId()]=(string)$option;
      
        $w['choices']=$c;
      }
      $wd = new sfWidgetFormChoice($w, $a);
    }
    else if(isset($fc['model']) && !isset($type['text']))
      $wd = new sfWidgetFormDoctrineChoice($w, $a);
    else if(isset($fc['choices']) && !isset($type['text']))
      $wd = new sfWidgetFormChoice($w, $a);
    else if(isset($type['bool']) && isset($w['multiple']) && $w['multiple'])
      $wd = new sfWidgetFormChoice(array_merge($w,array('choices'=>array(1=>'Yes',0=>'No'),'expanded'=>true,'multiple'=>true)),$a);
    else if(isset($type['bool']))
      $wd = new sfWidgetFormSelectRadio(array_merge(array('choices'=>array(1=>'Yes',0=>'No')),$w),$a);
    else if(false && isset($type['html']))
      $wd = new sfWidgetFormTextareaTinyMCE($w, $a);
    else if(isset($type['textarea']))
      $wd = new sfWidgetFormTextarea($w, $a);
    else
      $wd = new sfWidgetFormInputText($w, $a);

    return $wd;
  }


  public static function formValidator($fc, $form=false)
  {
    $string = true;
    $validators = array();
    $type=(isset($fc['type']))?(array_flip(preg_split('/\s+/',$fc['type'],false,PREG_SPLIT_NO_EMPTY))):(array());
    if(isset($type['double-list']))
      return false;
    $w = $fc;
    $a=array();
    if(isset($w['type']))$a['class']=$w['type'];
      unset($w['type']);
    if(isset($type['email']))
      $validators[]=new sfValidatorEmail();
    else if(isset($fc['model']) && !isset($type['text']))
    {
      $string = false;
      $validators[]=new sfValidatorDoctrineChoice(array('model'=>$fc['model'],'multiple'=>isset($type['multiple']),'min'=>(isset($type['multiple']) && isset($type['required'])), 'required'=>isset($type['required'])));
    }
    if(isset($type['richdate']) || isset($type['date']) || isset($type['datetime']))
    {
      if(isset($type['datetime']))
        $validators[]=new sfValidatorDateTime(array('required'=>isset($type['required'])));
      else
        $validators[]=new sfValidatorDate(array('required'=>isset($type['required'])));
    }
    elseif(isset($fc['model']) && isset($type['text']) && isset($type['multiple']))
    {
      $options=array();
      if(isset($fc['order_by']))
      {
        $options['order_by'] = preg_split('/\s+/',$fc['order_by']);
        $options['order_by'] += array('','asc');
      }
      $string = false;
      $validators[]=new sfValidatorCallback(array('required'=>isset($type['required']),'callback'=>array('BaseForm','validateMultipleChoice')));
    }
    if($string)
      $validators[]=new sfValidatorString(array('required'=>isset($type['required']),'trim'=>true));

    $validator = false;
    if(count($validators)> 1)
      $validator = new sfValidatorAnd($validators);
    else if(count($validators)> 0)
      $validator = $validators[0];
    
    return $validator;
  }


  /**
   * Debugging method
   *
   * Simple method to debug values - just outputs the value as text. The script
   * should end unless $end = FALSE is passed as param
   *
   * @param   mixed    $var   value to be displayed
   * @param   bool     $end   should be FALSE to avoid the script termination
   * @return  string          text output of the $var definition
   */
  public static function debug()
  {
    $arg=func_get_args();
    if(!headers_sent())
      @header("Content-Type: text/plain;charset=UTF-8");
    foreach($arg as $k=>$v)
    {
      if($v===false) return false;
      print_r($v);
      echo "\n";
    }
    exit();
  }

  /**
   * Data encryption function
   *
   * Encrypts any data and returns a base64 encoded string with its information
   *
   * @param   mixed  $data      data to be encrypted
   * @param   string $ekey      (optional) the key to encrypt the data
   * @param   string $cipher    (optional) the cipher to use
   * @param   string $mode      (optional) the mode to use
   * @return  string            the encoded string
   */
  public static function encrypt($data, $ekey='', $cipher='', $mode='')
  {
    if($ekey == '')$ekey=sfConfig::get('sf_csrf_secret');
    if($cipher == '')$cipher='tripledes';
    if($mode == '')$mode='cfb';
    $cipher_dir=sfConfig::get('app_tdz_cipher_dir');
    $mode_dir=sfConfig::get('app_tdz_mode_dir');
    $serialization_mode=sfConfig::get('app_tdz_serialization_mode');

    if(!function_exists('mcrypt_module_open')){
        return false;
    }
    /* Open the cipher */
    $td = mcrypt_module_open($cipher, $cipher_dir, $mode, $mode_dir);
    /* Create the IV and determine the keysize length */
    $iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
    $ks = mcrypt_enc_get_key_size($td);
    /* Create key */
    $key = substr($ekey, 0, $ks);
    /* Intialize encryption */
    mcrypt_generic_init ($td, $key, $iv);

    /* Serialize data */
    if($serialization_mode!='' && function_exists($serialization_mode))$data=$serialization_mode($data);
    else $data=serialize($data);

    /* Encrypt data */
    $encrypted = mcrypt_generic($td, $data);
    /* Terminate encryption handler */
    mcrypt_generic_deinit ($td);
    return base64_encode($iv.$encrypted);
  }
  /**
   * Data decryption function
   *
   * Decrypts data encrypted with encrypt
   *
   * @param   mixed $data       data to be decrypted
   * @param   string $ekey      (optional) the key to encrypt the data
   * @param   string $cipher    (optional) the cipher to use
   * @param   string $mode      (optional) the mode to use
   * @return  mixed             the encoded information
   */
  public static function decrypt($data, $ekey='', $cipher='', $mode='')
  {
    if($ekey == '')$ekey=sfConfig::get('sf_csrf_secret');
    if($cipher == '')$cipher='tripledes';
    if($mode == '')$mode='cfb';
    $cipher_dir=sfConfig::get('app_tdz_cipher_dir');
    $mode_dir=sfConfig::get('app_tdz_mode_dir');
    $unserialization_mode=sfConfig::get('app_tdz_unserialization_mode');

    if(!function_exists('mcrypt_module_open')){
        return false;
    }
    /* Open the cipher */
    $td = mcrypt_module_open($cipher, $cipher_dir, $mode, $mode_dir);
    /* Create the IV and determine the keysize length */
    //$iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
    $data = base64_decode($data);
    $iv = substr($data, 0, mcrypt_enc_get_iv_size($td));
    $data = substr($data, mcrypt_enc_get_iv_size($td));
    $ks = mcrypt_enc_get_key_size ($td);
    /* Create key */
    $key = substr($ekey, 0, $ks);
    /* Initialize encryption module for decryption */
    mcrypt_generic_init ($td, $key, $iv);
    /* Decrypt encrypted string */
    $decrypted = mdecrypt_generic ($td, $data);
    /* Terminate decryption handle and close module */
    mcrypt_generic_deinit ($td);
    mcrypt_module_close ($td);

    /* Show string: deserialize */
    if($unserialization_mode!='' && function_exists($unserialization_mode))$decrypted=$unserialization_mode($decrypted);
    else $decrypted=unserialize($decrypted);
    return $decrypted;
  }

};

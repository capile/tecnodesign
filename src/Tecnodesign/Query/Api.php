<?php
/**
 * Database abstraction
 *
 * PHP version 5.3
 *
 * @category  Database
 * @package   Model
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2016 Tecnodesign
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Query_Api
{
    const TYPE='api';
    public static 
        $microseconds=6, 
        $envelope,
        $search='q',
        $fieldnames='fieldnames',
        $limit='limit',
        $offset='offset',
        $sort='sort',
        $scope='scope',
        $insertPath='/new',
        $insertQuery,
        $insertMethod='POST',
        $updatePath='/update/%s',
        $updateQuery,
        $updateMethod='POST',
        $deletePath='/delete/%s',
        $deleteQuery,
        $deleteMethod='POST',
        $postFormat='json',
        $curlOptions=array(
            CURLOPT_HEADER=>1,
            CURLOPT_VERBOSE        => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FAILONERROR    => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => 'API browser',
            CURLOPT_AUTOREFERER    => true,         // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => 120,          // timeout on connect
            CURLOPT_TIMEOUT        => 120,          // timeout on response
            CURLOPT_MAXREDIRS      => 2,           // stop after 10 redirects
            CURLOPT_POST           => false,
            CURLOPT_HTTPHEADER     => array('Accept: application/json'),
        ),
        $successPattern='/HTTP\/[0-9\.]+ +20[0-4] /i',
        $errorPattern='/HTTP\/[0-9\.]+ +[45][0-9]{2} /i',
        $headerCount='X-Total-Count',
        $headerModified='Last-Modified';
    protected static $options, $conn=array();
    protected $_schema, $_url, $_scope, $_select, $_where, $_orderBy, $_limit, $_offset, $_options, $_last, $headers, $response;

    public function __construct($s=null)
    {
        if($s) {
            if(is_object($s)) {
                $this->_schema = get_class($s);
            } else {
                $this->_schema = $s;
            }
            if(property_exists($s, 'schema') && isset($s::$schema['database'])) {
                $db = Tecnodesign_Query::database($s::$schema['database']);
                if($db && $db['dsn']) {
                    $url = $db['dsn'];
                    $qs = '';
                    if($p=strpos($url, '?')) {
                        $qs = substr($url, $p);
                        $url = substr($url, 0, $p);
                    }
                    if(isset($s::$schema['tableName'])) {
                        $url .= '/'.$s::$schema['tableName'];
                    }
                    $this->_url = $url.$qs;
                    unset($url);
                }
                if(isset($db['options'])) {
                    $this->_options = $db['options'];
                }
                unset($db);
            }
        }
        // should throw an exception if no schema is found?
    }

    public function __toString()
    {
        return (string) $this->buildQuery();
    }

    // move this to curl requests?
    public static function disconnect($n='')
    {
        if(isset(self::$C[$n])) {
            curl_close(self::$C[$n]);
            unset(self::$C[$n]);
        }
    }

    protected static $C=array();
    public static function connect($n='', $exception=true, $tries=3)
    {
        if(!isset(self::$C[$n])) {
            self::$C[$n] = curl_init();
            $O = static::$curlOptions;
            curl_setopt_array(self::$C[$n], $O);
        }
        return self::$C[$n];
    }

    public function schema($prop=null)
    {
        $cn = $this->_schema;
        if($prop) {
            if(isset($cn::$schema[$prop])) return $cn::$schema[$prop];
            return null;
        }
        return $cn::$schema;
    }

    /*
    public function getSchema($tn, $schema=array())
    {
        $cn = 'Birds\\Data\\'.ucfirst($this->engine).'Schema';
        return $cn::load($this, $tn, $schema);
    }
    */

    public static function getTables($n='')
    {
    }


    public function find($options=array(), $asArray=false)
    {
        $this->_select = $this->_scope = $this->_where = $this->_orderBy = $this->_limit = $this->_offset = $this->response = $this->headers = null;
        $sc = $this->schema();
        if(isset($sc['defaults']['find'])) $this->filter($sc['defaults']['find']);
        unset($sc);
        $this->filter($options);
        return $this;
    }

    public function filter($options=array())
    {
        if(!$this->_schema) return $this;

        if(!is_array($options)) {
            $options = ($options)?(array('where'=>$options)):(array());
        }
        foreach($options as $p=>$o) {
            if(method_exists($this, ($m='add'.ucfirst($p)))) {
                $this->$m($o);
            }
            unset($m, $p, $o);
        }
        return $this;
    }

    public function buildQuery($count=false)
    {
        $url = $this->_url;
        $qs = '';
        if($p=strpos($url, '?')) {
            $qs = substr($url, $p);
            $url = substr($url, 0, $p);
        }
        if($this->_where) {
            $qs .= (($qs)?('&'):('?'))
                 . http_build_query($this->_where);
        }
        if($this->_scope) {
            $k = (isset($this->_options['scope']))?($this->_options['scope']):(static::$scope);
            $qs .= (($qs)?('&'):('?'))
                 . $k.'='.urlencode($this->_scope);
            unset($k);
            if($this->_select && ($cn=$this->_schema) && implode(',',$this->_select)===implode(',',$cn::columns($this->_scope))) {
                $this->_select=null;
            }
        }
        if($this->_select) {
            $k = (isset($this->_options['fieldnames']))?($this->_options['fieldnames']):(static::$fieldnames);
            $qs .= (($qs)?('&'):('?'))
                 . $k.'='.urlencode(implode(',', $this->_select));
            unset($k);
        }
        if($count) {
            $k = (isset($this->_options['limit']))?($this->_options['limit']):(static::$limit);
            $qs .= (($qs)?('&'):('?'))
                 . $k.'=0';
        } else {
            if(!is_null($this->_limit)) {
                $k = (isset($this->_options['limit']))?($this->_options['limit']):(static::$limit);
                $qs .= (($qs)?('&'):('?'))
                     . $k.'='.((int)$this->_limit);
                unset($k);
            }
            if(!is_null($this->_offset)) {
                $k = (isset($this->_options['offset']))?($this->_options['offset']):(static::$offset);
                $qs .= (($qs)?('&'):('?'))
                     . $k.'='.((int)$this->_offset);
                unset($k);
            }
            if($this->_orderBy) {
                $k = (isset($this->_options['sort']))?($this->_options['sort']):(static::$sort);
                $order = '';
                foreach($this->_orderBy as $fn=>$asc) {
                    if(!$asc || $asc=='desc') $fn = '-'.$fn;
                    $order .= ($order)?(','.$fn):($fn);
                    unset($fn, $asc);
                }
                $qs .= (($qs)?('&'):('?'))
                     . $k.'='.urlencode($order);
                unset($k);
            }

        }
        $url .= $qs;
        unset($qs);
        return $url;
    }

    public function scope($s=null)
    {
        if(is_string($s) && ($cn=$this->_schema) && isset($cn::$schema['scope'][$s])) {
            $this->_scope = $s;
        }
        return $this->_scope;
    }


    public function fetch($o=null, $l=null, $scope=null)
    {
        if(!$this->_schema) return false;
        $prop = array('_new'=>false);
        if($this->_scope) $prop['_scope'] = $this->_scope;
        if($o || $l) {
            $this->_offset = $o;
            $this->_limit = $l;
        }
        return $this->query($this->buildQuery(), 'class', $this->schema('className'), array($prop));
    }

    public function fetchArray($i=null)
    {
        if($o || $l) {
            $this->_offset = $o;
            $this->_limit = $l;
        }
        return $this->query($this->buildQuery(), 'array');
    }

    public function fetchItem($i)
    {
        if(!$this->_schema) return false;
        $prop = array('_new'=>false);
        if($this->_scope) $prop['_scope'] = $this->_scope;
        $url = $this->_url;
        $qs = '';
        if($p=strpos($url, '?')) {
            $qs = substr($url, $p);
            $url = substr($url, 0, $p);
        }
        $url .= '/'.urlencode($i).$qs;
        $r = $this->query($url);
        if($r) {
            $cn = $this->schema('className');
            return $cn::__set_state($r, true);
        }
    }

    public function count($column='1')
    {
        if(!$this->_schema) return false;
        if(is_null($this->response)) {
            $this->query($this->buildQuery(true));
        }
        if(!is_null($r=$this->header('headerCount'))) {
            return (int) $r;
        } else if($this->response) {
            return count($this->response);
        }

        return 0;
    }

    public function header($n=null)
    {
        if($n && $this->headers) {
            if(isset($this->_options[$n]) && $this->_options[$n]) {
                $h = $this->_options[$n];
            } else if(property_exists($this, $n)) {
                $h = static::$$n;
            } else {
                $h = $n;
            }
            $h .= ':';
            if($p=strpos($this->headers, $h)) {
                $p += strlen($h);
                return trim(substr($this->headers, $p, strpos($this->headers, "\n", $p)-$p));
            }
            return null;
        } else if(!$n) {
            return $this->headers;
        }
    }

    public function select($o)
    {
        $this->_select = null;
        return $this->addSelect($o);
    }

    public function addSelect($o)
    {
        if(is_array($o)) {
            foreach($o as $s) {
                $this->addSelect($s);
                unset($s);
            }
        } else {
            if(is_null($this->_select)) $this->_select = array();
            $fn = $this->getAlias($o);
            $this->_select[$fn]=$o;
            unset($fn);
        }
        return $this;
    }

    public function addScope($o)
    {
        if(is_array($o)) {
            foreach($o as $s) {
                $this->addScope($s);
                unset($s);
            }
        } else {
            //$this->addSelect($this->schema()->getScope($o));
            $this->_scope = $o;
        }
        return $this;
    }

    public function where($w)
    {
        $this->_where = $this->getWhere($w);
        return $this;
    }

    public function addWhere($w)
    {
        if(is_null($this->_where)) $this->_where = $this->getWhere($w);
        else $this->_where += $this->getWhere($w);
        return $this;
    }

    private function getWhere($w)
    {
        if(is_array($w)) {
            return $w;
        } else if($w) {
            $r = array();
            $cn = $this->_schema;
            $pk = $cn::pk();
            if(!$pk) return $r;
            else if(count($pk)==1) $r = array( $pk[0] => $w );
            else {
                $v = preg_split('/\s*[\,\;]\s*/', $w, count($pk));
                unset($w);
                foreach($v as $i=>$k) {
                    $r[$pk[$i]] = $k;
                    unset($i, $k);
                }
                unset($v);
            }
            return $r;
        }
        return array();
   }

    public function addOrderBy($o)
    {
        if(is_array($o)) {
            foreach($o as $s) {
                $this->addOrderBy($s);
                unset($s);
            }
        } else {
            if(is_null($this->_orderBy)) $this->_orderBy = array();
            $this->_orderBy[$o]='asc';
            unset($fn);
        }
        return $this;
    }

    public function addGroupBy($o)
    {
        if(is_array($o)) {
            foreach($o as $s) {
                $this->addGroupBy($s);
                unset($s);
            }
        } else {
            if(is_null($this->_groupBy)) $this->_groupBy = array();
            $this->_groupBy[]=$o;
        }
        return $this;
    }

    public function limit($o)
    {
        $this->_limit = (int) $o;
        return $this;
    }

    public function addLimit($o)
    {
        $this->_limit = (int) $o;
        return $this;
    }

    public function offset($o)
    {
        $this->_offset = (int) $o;
        return $this;
    }

    public function addOffset($o)
    {
        $this->_offset = (int) $o;
        return $this;
    }

    private function getFunctionNext($fn)
    {
    }

    private function getAlias($f)
    {
        return $f;
    }

    public static function runStatic($n, $q)
    {
    }

    public function run($q, $conn=null)
    {
        if(!$conn) $conn = self::connect($this->schema('database'));
        curl_setopt($conn, CURLOPT_URL, $q);
        if(isset($this->_options['certificate']) && $this->_options['certificate']) {
            if(strpos($this->_options['certificate'], ':')>1) {
                list($cert, $cpass) = explode(':', $this->_options['certificate'], 2);
                curl_setopt($conn, CURLOPT_SSLCERT, TDZ_APP_ROOT.'/'.$cert);
                curl_setopt($conn, CURLOPT_SSLCERTPASSWD,$cpass);
            } else {
                curl_setopt($conn, CURLOPT_SSLCERT, TDZ_APP_ROOT.'/'.$this->_options['certificate']);
            }
        }
        $this->_last = $q;
        $r = curl_exec($conn);
        $msg = '';
        if(!$r) {
            $msg = curl_error($conn);

        } else {
            if(isset(static::$curlOptions[CURLOPT_HEADER]) && static::$curlOptions[CURLOPT_HEADER]) {
                list($this->headers, $body) = preg_split('/\r?\n\r?\n/', $r, 2);
                $this->response = json_decode($body, true);
                unset($body);
            } else {
                $this->headers = null;
                $this->response = json_decode($body, true);
            }
        }

        if($msg || preg_match(static::$errorPattern, $r)) {
            if(isset($this->response['error'])) {
                $msg = '<div class="tdz-i-msg tdz-i-error">'.$this->response['error'].'</div>';
                if(isset($this->response['message'])) {
                    $msg .= $this->response['message'];
                }
            }
            $cn = get_class($this);
            throw new Tecnodesign_Exception($msg);
        } else if(!preg_match(static::$successPattern, $r)) {
            $this->headers = $r;
            $this->response = false;
            //tdz::log("[ERROR] API:\n", curl_error($conn), "\n{$r}");
        }
        unset($r);
        return $this;
    }

    public function query($q, $p=null)
    {
        try {
            $this->run($q);
            if (is_null($p)) {
                return $this->response;
            } else {
                $arg = func_get_args();
                array_shift($arg);
                if(isset($arg[1])) {
                    return $this->fetchAll($arg[0], $arg[1]);
                } else {
                    return $this->response;
                }
            }
        } catch(Exception $e) {
            tdz::log('Error in '.__METHOD__.":\n  ".$e->getMessage()."\n ".$e);
            return false;
        }
    }

    public function fetchAll($as='class', $className=null)
    {
        if($as=='array' || !$this->response || !($cn = ($className)?($className):($this->schema('className')))) {
            return $this->response;
        }
        $R = array();
        foreach($this->response as $i=>$o) {
            $R[$i] = $cn::__set_state($o, true);
            unset($i, $o);
        }
        return $R;
    }

    public function queryColumn($q, $i=0)
    {
        return $this->query($q, 'column', $i);
    }

    /*
    public function lastInsertId($fn=null)
    {
        $id = self::connect($this->schema('connection'))->lastInsertId($fn);
        return $id;
    }
    */


    public static function escape($str, $enclose=true)
    {
        if(is_array($str)) {
            foreach($str as $k=>$v){
                $str[$k]=self::escape($v, $enclose);
                unset($k, $v);
            }
            return $str;
        }
        $str = str_replace(array('\\', "'"), array('\\\\', "''"), $str);
        $str = ($enclose) ? ("'{$str}'") : ($str);
        return $str;
    }

    public static function sql($v, $d) {
        return self::escape($v);
    }

    /*
    public function transaction($id=null)
    {
    }
    
    public function commit($id=null)
    {
    }

    public function rollback($id=null)
    {
    }
    */

    public function runAction($action, $data=null, $method=null, $conn=null)
    {
        $url = $this->_url;
        $aqs = $qs = '';
        if(strpos($url, '?')!==false) {
            list($url, $qs) = explode('?', $url, 2);
        }
        if(strpos($action, '?')!==false) {
            list($action, $aqs) = explode('?', $action, 2);
        }
        if($action) $url .= $action;
        if($aqs) $qs .= ($qs)?('&'.$aqs):($aqs);
        if($qs) $url .= '?'.$qs;

        if($data && is_object($data)) {
            $data = $data->asArray('save');
        }
        if(!$conn) $conn = self::connect($this->schema('database'));

        $H = static::$curlOptions[CURLOPT_HTTPHEADER];
        if(!is_array($H)) $H = array();
        if(!is_string($data)) {
            if(static::$postFormat && static::$postFormat=='json') {
                $data = json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                $H[] = 'Content-Type: application/json';
            } else {
                $data = http_build_query($data);
            }
        }

        if($data) {
            curl_setopt($conn, CURLOPT_POST, true);
            curl_setopt($conn, CURLOPT_POSTFIELDS, $data);
            curl_setopt($conn, CURLOPT_HTTPHEADER, $H);
        }
        if($method) {
            curl_setopt($conn, CURLOPT_CUSTOMREQUEST, $method);
        }
        $R = $this->run($url, $conn);
        return $R;
    }

    public function insert($M, $conn=null)
    {
        $action = static::$insertPath;
        if(static::$insertQuery) $action .= '?'.sprintf(static::$insertQuery, $pk);
        try {
            return $this->runAction($action, $M->asArray('save'), static::$insertMethod);
        } catch(Exception $e) {
            throw new Tecnodesign_Exception(array(tdz::t('Could not save %s.', 'exception'), $M::label()));
        }
    }

    public function update($M, $conn=null)
    {
        $pk = $M->pk;
        if(is_array($pk)) $pk = array_shift($pk);
        $action = (static::$updatePath)?(sprintf(static::$updatePath, $pk)):('');
        if(static::$updateQuery) $action .= '?'.sprintf(static::$updateQuery, $pk);
        try {
            return $this->runAction($action, $M->asArray('save'), static::$updateMethod);
        } catch(Exception $e) {
            throw new Tecnodesign_Exception(array(tdz::t('Could not save %s.', 'exception'), $M::label()));
        }
    }

    public function delete($M, $conn=null)
    {
        $pk = $M->pk;
        if(is_array($pk)) $pk = array_shift($pk);
        $action = (static::$deletePath)?(sprintf(static::$deletePath, $pk)):('');
        if(static::$deleteQuery) $action .= '?'.sprintf(static::$deleteQuery, $pk);
        try {
            return $this->runAction($action, $M->getPk(null, true), static::$deleteMethod);
        } catch(Exception $e) {
            throw new Tecnodesign_Exception(array(tdz::t('Could not save %s.', 'exception'), $M::label()));
        }
    }

    public function lastQuery()
    {
        return $this->_last;
    }

    /**
     * Gets the timestampable last update
     */
    public function timestamp()
    {
        if(!$this->_schema) return false;
        $cn = $this->schema('className');
        if(!isset(tdz::$variables['timestamp']))tdz::$variables['timestamp']=array();
        if(isset(tdz::$variables['timestamp'][$cn])) {
            return tdz::$variables['timestamp'][$cn];
        }
        tdz::$variables['timestamp'][$cn] = false;
        if(is_null($this->headers)) {
            $this->_limit = 0;
            $this->query($this->buildQuery(true));
            $this->_limit = null;
        }
        if(($r=$this->header('headerModified')) && ($t=strtotime($r))) {
            tdz::$variables['timestamp'][$cn] = $t;
        }
        if(tdz::$perfmon>0) tdz::log(__METHOD__.': '.tdz::formatNumber(microtime(true)-tdz::$perfmon).'s '.tdz::formatBytes(memory_get_peak_usage()).' mem: '.tdz::$variables['timestamp'][$cn]);
        return tdz::$variables['timestamp'][$cn];
    }

}
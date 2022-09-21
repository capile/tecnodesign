<?php
/**
 * Database abstraction
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.6
 */
use Studio as S;
use Tecnodesign_Cache as Cache;
use Tecnodesign_Exception as AppException;

class Tecnodesign_Query_Api
{
    const TYPE='api', DRIVER='curl';
    public static
        $microseconds=6,
        $envelope,
        $search='q',
        $fieldnames='fieldnames',
        $limit='limit',
        $limitCount='0',
        $limitAbsolute=false,
        $offset='offset',
        $pageOffset,
        $startPage=0,
        $sort='sort',
        $scope='scope',
        $queryMethod='GET',
        $queryPath='/%s',
        $queryTableName, // deprecated, use $queryPath instead
        $insertPath='/%s/new',
        $insertQuery,
        $insertMethod='POST',
        $previewPath='/%s/preview/%s',
        $previewQuery,
        $previewMethod='GET',
        $updatePath='/%s/update/%s',
        $updateQuery,
        $updateMethod='POST',
        $deletePath='/%s/delete/%s',
        $deleteQuery,
        $deleteMethod='POST',
        $saveToModel,
        $postFormat='json',
        $postCharset,
        $curlOptions=array(
            CURLOPT_HEADER=>1,
            CURLOPT_VERBOSE        => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FAILONERROR    => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => 'API browser',
            CURLOPT_AUTOREFERER    => true,         // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => 120,          // timeout on connect
            CURLOPT_TIMEOUT        => 120,          // timeout on response
            CURLOPT_MAXREDIRS      => 2,            // stop after 10 redirects
            CURLOPT_POST           => false,
        ),
        $requestHeaders = array('accept: application/json'),
        $successPattern='/HTTP\/[0-9\.]+ +20[0-4] /i',
        $errorPattern='/HTTP\/[0-9\.]+ +[45][0-9]{2} .*/i',
        $decode,
        $errorAttribute='error.message|error',
        $countable=true,
        $headerCount='x-total-count',
        $headerModified='last-modified',
        $dataAttribute,
        $countAttribute,
        $enableOffset=true,
        $pagingAttribute,
        $nextPage,
        $cookieJar,
        $connectionCallback;
    protected static $options, $conn=array();
    protected $_schema, $_method, $_url, $_reqBody, $_scope, $_select, $_where, $_orderBy, $_groupBy, $_limit, $_offset, $_options, $_last, $_next, $_count, $_unique, $headers, $response;

    public function __construct($s=null)
    {
        $db = null;
        if($s) {
            if(is_object($s)) {
                $this->_schema = get_class($s);
            } else if(is_a($s, 'Tecnodesign_Model', true)) {
                $this->_schema = $s;
            } else {
                $db = Tecnodesign_Query::database($s);
            }
            if($this->_schema && property_exists($s, 'schema') && $s::$schema->database) {
                $db = Tecnodesign_Query::database($s::$schema->database);
            }
        }
        if($db) {
            if($db && $db['dsn']) {
                $url = $db['dsn'];
                $qs = '';
                if($p=strpos($url, '?')) {
                    $qs = substr($url, $p);
                    $url = substr($url, 0, $p);
                }
                if(static::$queryTableName && $this->_schema && isset($s::$schema->tableName)) {
                    $url .= '/'.$s::$schema->tableName;
                }
                $this->_url = $url.$qs;
                unset($url);
                unset($db['dsn']);
            }
            if(isset($db['options'])) {
                $this->_options = $db['options'];
            }
            unset($db);
        }
    }

    public function __toString()
    {
        return (string) $this->buildQuery();
    }

    public function config($n=null, $newValue=null)
    {
        static $options=[
            'microseconds',
            'envelope',
            'search',
            'fieldnames',
            'limit',
            'limitCount',
            'limitAbsolute',
            'offset',
            'pageOffset',
            'startPage',
            'sort',
            'scope',
            'queryMethod',
            'queryPath',
            'insertPath',
            'insertQuery',
            'insertMethod',
            'previewPath',
            'previewQuery',
            'previewMethod',
            'updatePath',
            'updateQuery',
            'updateMethod',
            'deletePath',
            'deleteQuery',
            'deleteMethod',
            'saveToModel',
            'postFormat',
            'postCharset',
            'curlOptions',
            'requestHeaders',
            'successPattern',
            'errorPattern',
            'errorAttribute',
            'headerCount',
            'headerModified',
            'dataAttribute',
            'countAttribute',
            'enableOffset',
            'pagingAttribute',
            'nextPage',
            'countable',
            'cookieJar',
            'connectionCallback',
            'decode',
        ];
        if($n) {
            if(isset($this->_options[$n])) {
                if(!is_null($newValue)) $this->_options[$n] = $newValue;
                return $this->_options[$n];
            } else if(in_array($n, $options)) {
                if(!is_null($newValue)) static::$$n = $newValue;
                return static::$$n;
            } else {
                return null;
            }
        }

        return $this->_options;
    }

    public function disconnect($n='')
    {
        if($c=$this->config('cookieJar') && is_string($c)) {
            if(file_exists($c)) @unlink($c);
            $this->config('cookieJar', true);
        }
        if(isset(self::$C[$n])) {
            curl_close(self::$C[$n]);
            unset(self::$C[$n]);
        }
    }

    protected static $C=array();
    public function connect($n='', $exception=true, $tries=3)
    {
        $req = false;
        if(!isset(self::$C[$n])) {
            self::$C[$n] = curl_init();
            if($c = $this->config('curlOptions')) {
                curl_setopt_array(self::$C[$n], $c);
                unset($c);
            }
            $req = true;
        }
        if($c=$this->config('cookieJar')) {
            if(!is_string($c)) {
                $c = tempnam(Cache::cacheDir(), 'cookie');
            }
            curl_setopt(self::$C[$n], CURLOPT_COOKIEFILE, $c);
            curl_setopt(self::$C[$n], CURLOPT_COOKIEJAR, $c);
            $this->config('cookieJar', $c);
        }
        if($this->config('token_endpoint')) {
            $this->requestToken($n, $exception);
        } else if($c=$this->config('grant_type')) {
            $this->authorizationHeader($c);
        }
        if($c = $this->config('connectionCallback')) {
            self::$C[$n] = call_user_func($c, self::$C[$n], $n);
            unset($c);
        }
        if($req && ($c=$this->config('requestHeaders'))) {
            curl_setopt(self::$C[$n], CURLOPT_HTTPHEADER, $c);
            unset($c);
        }

        if(!self::$C[$n]) {
            S::log('[INFO] Failed connection to '.$n);
            if($exception) throw new AppException(array(S::t('Could not connect to %s.', 'exception'), $n));
        }
        return self::$C[$n];
    }

    public function requestToken($n='', $exception=true)
    {
        $ckey = $n.'/req-token';
        if(!($url=$this->config('token_endpoint')) || !isset(self::$C[$n]) || !($conn = curl_copy_handle(self::$C[$n]))) return false;

        if(!(($R=Cache::get($ckey, 0, 'file')) && isset($R['access_token']) && isset($R['expires']) && $R['expires']>time())) {
            // try to fetch a new access_token based on the refresh token
            $d = [
                'grant_type' => ($c=$this->config('grant_type')) ?$c :'client_credentials',
                'scope' => ($c=$this->config('scope')) ?$c :'openid',
            ];
            if(($ct = (string)$this->config('contentType')) && substr($c, -4)==='json') {
                $data = S::serialize($d, 'json');
            } else {
                $data = http_build_query($d);
                if(!$ct) $ct = 'application/x-www-form-urlencoded';

            }
            $method = 'POST';
            $headers = array(
                'accept: application/json',
                'content-type: '.$ct,
                'authorization: Basic '.base64_encode($this->config('client_id').':'.$this->config('client_secret')),
            );
            curl_setopt($conn, CURLOPT_HEADER, false);
            curl_setopt($conn, CURLOPT_URL, $url);
            curl_setopt($conn, CURLOPT_POST, true);
            curl_setopt($conn, CURLOPT_POSTFIELDS, $data);
            curl_setopt($conn, CURLOPT_HTTPHEADER, $headers);
            $R = S::unserialize(curl_exec($conn), 'json');
            curl_close($conn);
            if($R && isset($R['access_token'])) {
                $expires = 100;
                if(isset($R['expires_in'])) $expires = $R['expires_in'] -5;
                $R['expires'] = time()+$expires;
                Cache::set($ckey, $R, 0);
            } else {
                S::log('[WARNING] Could not retrieve '.$n.' tokens!');
                return false;
            }
        }
        $this->authorizationHeader('Bearer',  $R['access_token']);
    }

    public function authorizationHeader($type, $credentials=null)
    {
        $add = 'authorization: '.$type;
        if($credentials) {
            $add .= ' '.$credentials;
        } else if(is_null($credentials)) {
            if(!($pw=$this->config('password'))) {
                $pw=$this->config('client_secret');
            }
            if($pw && strtolower($type)=='bearer') {
                $add .= ' '.$pw;
            } else {
                if(!($un=$this->config('username'))) {
                    $un=$this->config('client_id');
                }
                if($un) {
                    $add .= ' '.base64_encode(urlencode($un).':'.urlencode($pw));
                }
            }
        }
        $H = $this->config('requestHeaders');
        foreach($H as $i=>$h) {
            if($h==$add) {
                $add = null;
                break;
            } else if(strtolower(substr($h, 0, 14))=='authorization:') {
                unset($H[$i]);
            }
            unset($i, $o);
        }
        if($add) $H[] = $add;
        $this->config('requestHeaders', $H);
        unset($add, $H);
    }

    public function schema($prop=null)
    {
        $cn = $this->_schema;
        if($prop) {
            $base = $cn::$schema;
            while(strpos($prop, '/')!==false) {
                $p = strpos($prop, '/');
                $n = substr($prop, 0, $p);
                if(!isset($base[$n])) return null;
                $base = $base[$n];
                $prop = substr($prop, $p+1);
            }
            if(isset($base[$prop])) {
                return $base[$prop];
            }
            return null;
        }
        return ($cn) ?$cn::$schema :null;
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

    public function cleanup()
    {
        if($this->response) {
            if(is_array($this->response)) {
                foreach($this->response as $i=>$o) {
                    unset($this->response[$i], $i, $o);
                }
            }
            $this->response = null;
        }
    }

    public function reset()
    {
        $this->_select = null;
        $this->_scope = null;
        $this->_where = null;
        $this->_orderBy = null;
        $this->_limit = null;
        $this->_offset = null;
        $this->response = null;
        $this->headers = null;
        $this->_count = null;
        $this->_unique = null;
    }


    public function find($options=array(), $asArray=false)
    {
        $this->reset();
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

    public function buildQueryWhere($qs='')
    {
        return $qs.(($qs)?('&'):('?')).http_build_query($this->_where);
    }


    public function buildQueryCount($qs='')
    {
        $k = $this->config('limit');
        if($k) {
            $limit = (isset($this->_limit)) ?$this->_limit :$this->config('limitCount');
            $qs = static::urlParam($qs, [$k=>$limit]);
        }
        return $qs;
    }

    public function buildQueryOrder($qs='')
    {
        $k = $this->config('sort');
        if($k) {
            $order = '';
            foreach($this->_orderBy as $fn=>$asc) {
                if(!$asc || $asc=='desc') $fn = '-'.$fn;
                $order .= ($order)?(','.$fn):($fn);
                unset($fn, $asc);
            }
            if($order) {
                $qs = static::urlParam($qs, [$k=>$order]);
            }
        }
        unset($k);
        return $qs;
    }


    public function buildQuery($count=false)
    {
        $url = $this->_url;
        $qs = '';
        if($p=strpos($url, '?')) {
            $qs = substr($url, $p);
            $url = substr($url, 0, $p);
        }

        if($k=$this->config('queryPath')) {
            $qp = sprintf($this->_expand($k), $this->schema('tableName'), null);
            if($p=strpos($qp, '?')) {
                $qs = static::urlParam($qs, substr($qp, $p+1));
                $url .= substr($qp, 0, $p);
            } else {
                $url .= $qp;
            }
        }

        if($this->_where) {
            $qs = $this->buildQueryWhere($qs);
        }
        if($this->_scope) {
            $k = $this->config('scope');
            if($k) {
                $qs = static::urlParam($qs, [$k=>$this->_scope]);
                unset($k);
            }
            if($this->_select && ($cn=$this->_schema) && implode(',',$this->_select)===implode(',',$cn::columns($this->_scope, null, 0, true))) {
                $this->_select=null;
            }
        }
        if($this->_select && ($k = $this->config('fieldnames'))) {
            $qs = static::urlParam($qs, [$k=>implode(',', $this->_select)]);
            unset($k);
        }
        if($count) {
            $qs = $this->buildQueryCount($qs, $count);
        } else if(!$this->_unique) {
            if(!is_null($this->_limit)) {
                $k = $this->config('limit');
                if($k) {
                    $limit = (int)$this->_limit;
                    if($this->config('limitAbsolute') && !is_null($this->_offset)) {
                        $limit += (int) $this->_offset -1;
                    } else if(!$limit && ($c=$this->config('limitCount'))) {
                        $limit = (int)$c;
                    }
                    $qs = static::urlParam($qs, [$k=>$limit]);
                }
                unset($k);
            }
            if(!is_null($this->_offset) && $this->_offset>0) {
                if(($k=$this->config('pageOffset')) && ($c=$this->config('limitCount'))) {
                    $page = $this->_offset / $c;
                    if(!is_int($page)) $page = ceil($page);
                    if($c=$this->config('startPage')) $page += (int)$c; 
                    $qs = static::urlParam($qs, [$k=>$page]);
                } else if($k = $this->config('offset')) {
                    $qs = static::urlParam($qs, [$k=>(int)$this->_offset]);
                }
                unset($k);
            }
            if($this->_orderBy) {
                $qs = $this->buildQueryOrder($qs);
            }

        }
        $url .= $qs;
        unset($qs);

        return $url;
    }


    public function scope($s=null)
    {
        if(is_string($s) && ($cn=$this->_schema) && isset($cn::$schema->scope[$s])) {
            $this->_scope = $s;
        }
        return $this->_scope;
    }

    public function fetch($o=null, $l=null, $scope=null, $callback=null, $args=null)
    {
        if(!$this->_schema) return false;
        $prop = array('_new'=>false);
        if($this->_scope) $prop['_scope'] = $this->_scope;
        $this->_offset = $o;
        $this->_limit = $l;
        if($c=$this->config('limitCount')) {
            if($l > $c) {
                $this->_limit = (int)$c;
            }
        }
        if($this->_offset > 0) {
            if($this->_next) {
                $q = $this->nextPage();
            } else if($this->config('offset')) {
                $q = $this->buildQuery();
            } else {
                return null;
            }
        } else {
            $q = $this->buildQuery();
        }
        return $this->query($q, 'class', $this->schema('className'), $prop, $callback, $args);
    }

    public function fetchArray($i=null)
    {
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
            return $this->getObject($r, $cn, $prop);
        }
    }

    public function getObject($o, $cn=null, $prop=null, $callback=null, $args=null)
    {
        if(!$cn) $cn = $this->schema('className');
        if(is_array($o)) {
            if($prop && is_array($prop)) {
                $o += $prop;
            }
            $R = $cn::__set_state($o, true);
        } else {
            if(is_object($o) && ($o instanceof $cn)) {
                $R = $o;
            }
        }
        if(isset($R)) {
            if($callback) {
                if(!is_null($args) && (!is_array($args) || !$args)) $args=null;
                if(is_string($callback) && method_exists($R, $callback)) {
                    if(is_null($args) || count($args)==1) {
                        if(!is_null($args)) $args=array_shift($args);
                        return $R->$callback($args);
                    } else {
                        return call_user_func_array(array($R, $callback), $args);
                    }
                } else {
                    if(!is_array($args)) $args=array();
                    array_unshift($args, $R);
                    return call_user_func_array($callback, $args);
                }

            }
            return $R;
        }

    }

    public function count($column='1')
    {
        if(is_null($this->_count)) {
            if(!$this->_schema) return false;
            if(is_null($this->response)) {
                $this->query($this->buildQuery((bool)$this->config('countable')));
            }
        }
        if(is_null($this->_count)) {
            if(!is_null($r=$this->header('headerCount'))) {
                $this->_count = (int) $r;
            } else if($this->response) {
                $this->_count = @count($this->response);
            }
        }

        return $this->_count;
    }

    public function header($n=null)
    {
        if($n && $this->headers) {
            if(!($h = $this->config($n))) {
                $h = $n;
            }
            $h .= ':';
            if($p=stripos($this->headers, $h)) {
                $p += strlen($h);
                $r = trim(substr($this->headers, $p));
                if($p=strpos($r, "\n")) {
                    $r = substr($r, 0, $p);
                }
                unset($p);
                return trim($r);
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
        if(is_null($this->_orderBy)) $this->_orderBy = array();
        if(is_array($o)) {
            foreach($o as $fn=>$s) {
                if(is_string($fn) && ($s=='asc' || $s=='desc')) {
                    $this->_orderBy[$fn]=$s;
                } else {
                    $this->addOrderBy($s);
                }
                unset($s, $fn);
            }
        } else {
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

    public static function runStatic($q, $n='', $data=null, $method=null, $headers=null, $callback='json', $disconnect=false)
    {
        $qcn = get_called_class();
        $Q = new $qcn($n);
        unset($qcn);
        $conn = $Q->connect($n);

        $url = $q;
        if($Q->_url && strpos(substr($url, 0, 10), '://')===false) {
            $qs = null;
            $b = $Q->_url;
            if($p=strpos($b, '?')) {
                $qs = substr($b, $p+1);
                $b = substr($b, 0, $p);
            }
            if(substr($url, 0, 1)!=='/' && substr($b, -1)!=='/') $b .= '/';
            $url = $b.$url;

            if($qs && strpos($url, '?')===false) $url .= '?'.$qs;
        }
        curl_setopt($conn, CURLOPT_URL, $url);

        if(!is_array($headers)) $headers = array();

        if($data && !is_string($data)) {
            if($Q->config('postFormat')==='json') {
                $data = json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                $headers[] = 'content-type: application/json'.(($c=$Q->config('postCharset')) ?';charset='.$c :'');
                unset($c);
            } else {
                $data = http_build_query($data);
            }
        }

        if($data) {
            curl_setopt($conn, CURLOPT_POST, true);
            curl_setopt($conn, CURLOPT_POSTFIELDS, $data);
        }

        if($method && $method!='GET') {
            curl_setopt($conn, CURLOPT_CUSTOMREQUEST, $method);
        }

        if($headers) {
            curl_setopt($conn, CURLOPT_HTTPHEADER, $headers);
        }
        $r = curl_exec($conn);
        if($disconnect) $Q->disconnect($n);
        $msg = '';
        if(!$r) {
            tdz::log('[ERROR] Curl error: '.curl_error($conn));
            return false;
        }
        if($callback && $callback=='json') {
            if(($c=$Q->config('curlOptions')) && isset($c[CURLOPT_HEADER]) && $c[CURLOPT_HEADER]) {
                list($headers, $body) = preg_split('/\r?\n\r?\n/', $r, 2);
                while(preg_match('#^HTTP/1.[0-9]+ [0-9]+ #', $body)) {
                    list($headers, $body) = preg_split('/\r?\n\r?\n/', $body, 2);
                }
                $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);
                $r = json_decode($body, true);
                if($r===null) {
                    $err = json_last_error();
                    if($err) {
                        $errs = array (
                          0 => 'JSON_ERROR_NONE',
                          1 => 'JSON_ERROR_DEPTH',
                          2 => 'JSON_ERROR_STATE_MISMATCH',
                          3 => 'JSON_ERROR_CTRL_CHAR',
                          4 => 'JSON_ERROR_SYNTAX',
                          5 => 'JSON_ERROR_UTF8',
                          6 => 'JSON_ERROR_RECURSION',
                          7 => 'JSON_ERROR_INF_OR_NAN',
                          8 => 'JSON_ERROR_UNSUPPORTED_TYPE',
                          9 => 'JSON_ERROR_INVALID_PROPERTY_NAME',
                          10 => 'JSON_ERROR_UTF16',
                        );
                        if(isset($errs[$err])) {
                            tdz::log('[ERROR] JSON decoding error: '.$errs[$err]);
                        } else {
                            tdz::log('[ERROR] JSON unknown error: '.$err);
                        }
                    }
                }
                unset($body, $c);
            } else {
                $r = json_decode($r, true);
            }
        } else if($callback) {
            return call_user_func($callback, $r);
        }
        return $r;
    }

    public function run($q, $conn=null, $enablePaging=true, $keepAlive=null, $cn=null, $defaults=null, $callback=null, $args=array())
    {
        if(!$conn) $conn = $this->connect($this->schema('database'));
        curl_setopt($conn, CURLOPT_URL, $q);
        if($c=$this->config('certificate')) {
            if(strpos($c, ':')>1) {
                list($cert, $cpass) = explode(':', $c, 2);
                curl_setopt($conn, CURLOPT_SSLCERT, TDZ_APP_ROOT.'/'.$cert);
                curl_setopt($conn, CURLOPT_SSLCERTPASSWD,$cpass);
            } else {
                curl_setopt($conn, CURLOPT_SSLCERT, TDZ_APP_ROOT.'/'.$c);
            }
            unset($c);
        }
        if(($c=$this->config('username')) && ($p=$this->config('password'))) {
            curl_setopt($conn, CURLOPT_USERPWD, $c.':'.$p);
            curl_setopt($conn, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            unset($c, $p);
        }

        $this->_last = $q;
        if(!$this->_method) {
            $this->_method = ($this->_reqBody) ?'POST' :$this->config('queryMethod');
        } else if($this->_method!='GET' && $this->_method!='POST') {
            curl_setopt($conn, CURLOPT_CUSTOMREQUEST, $this->_method);
        }

        if($this->_reqBody && $this->_method!='GET') {
            $data = $this->_reqBody;
            if(!is_string($data)) $data = tdz::serialize($data, $this->config('postFormat'));
            curl_setopt($conn, CURLOPT_POST, true);
            curl_setopt($conn, CURLOPT_POSTFIELDS, $data);
        }

        if(tdz::$log) tdz::log("[INFO] {$this->_method} call to $q (".ceil(memory_get_peak_usage() * 0.000001).'M, '.substr((microtime(true) - TDZ_TIME), 0, 5).'s)');
        $this->cleanup();
        $r = curl_exec($conn);

        $msg = '';
        $body = null;
        if(!$r) {
            $msg = curl_error($conn);
        } else {
            if(($c=$this->config('curlOptions')) && isset($c[CURLOPT_HEADER]) && $c[CURLOPT_HEADER]) {
                list($this->headers, $body) = preg_split('/\r?\n\r?\n/', $r, 2);
                while(preg_match('#^HTTP/1.[0-9]+ [0-9]+ #', $body)) {
                    list($this->headers, $body) = preg_split('/\r?\n\r?\n/', $body, 2);
                }
            } else {
                $this->headers = null;
                $body = $r;
            }
            $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);
            if($decode=$this->config('decode')) {
                if(is_string($decode)) {
                    $decode = preg_split('/\s*[\,\|]+\s*/', $decode, -1, PREG_SPLIT_NO_EMPTY);
                }
            } else if($this->headers && ($ct=$this->header('content-type'))) {
                $decode = [];
                if(strpos($ct, 'gzip')) $decode[] = 'gzip';
                if(strpos($ct, 'json')) $decode[] = 'json';
            }

            if($decode) {
                foreach($decode as $dn) {
                    if(method_exists($this, $dm = 'decode'.tdz::camelize($dn, true))) {
                        $body = $this->$dm($body);
                        if(is_null($body) || $body===false) break;
                        unset($dm, $dn);
                    }
                }
            }
            $this->response = $body;
            unset($body);
        }
        unset($r);

        if($this->response && is_array($this->response) && $this->_unique) {
            $this->response = array($this->response);
        }

        $m = null;
        if($msg || preg_match($this->config('errorPattern'), $this->headers, $m)) {
            if(!$msg && (!($c=$this->config('errorAttribute')) || !($msg=$this->_getResponseAttribute($c)))) {
                $msg=$this->header('x-message');
            }
            if(is_array($msg)) {
                $msg = tdz::xmlImplode($msg);
            }
            $msg = '<div class="tdz-i-msg tdz-i-error">'
                 . $msg
                 . '</div>';
            if(isset($this->response['message'])) {
                $msg .= $this->response['message'];
            }
            if($m) {
                tdz::log("[INFO] Bad response for {$q}: \n{$this->headers}\n  ".strip_tags($msg));
                if(tdz::$log>2) tdz::log($body);
            }
            throw new Tecnodesign_Exception($msg);
        } else if(!preg_match($this->config('successPattern'), $this->headers)) {
            $this->response = false;
        }

        if($enablePaging && $this->response && ($c=$this->config('pagingAttribute')) && ($this->_next=$this->_getResponseAttribute($c))) {
            $page = $q;
            $R = array();
            $dataAttribute = null;
            if($this->response && ($d=$this->config('dataAttribute')) && $this->_expand($d)) {
                $dataAttribute = $d;
                unset($d);
                $this->config('dataAttribute', false);
                $R = $this->_getResponseAttribute($dataAttribute);
                if(!$R && !is_array($R)) $R=array();
            } else {
                $R = $this->response;
                unset($R[$c]);
            }
            if($cn && $R) {
                foreach($R as $i=>$o) {
                    $O = $this->getObject($o, $cn, $defaults, $callback, $args);
                    unset($R[$i]);
                    if(!is_null($O)) {
                        $R[$i] = $O;
                    }
                    unset($i, $o, $O);
                }
            }

            $count = count($R);
            $max = ($this->_limit) ?$this->_limit :(int)$this->config('limitCount');
            while(($nextPage=$this->nextPage($q)) && $page!=$nextPage && ($max==0 || $count < $max)) {
                // check if cursor is an URL or a parameter
                $page = $nextPage;
                $this->response = null;
                $this->run($nextPage, $conn, false, true);

                if($this->response) {
                    if($dataAttribute) {
                        $M = $this->_getResponseAttribute($dataAttribute);
                        $count += count($M);
                    } else {
                        $count += count($this->response);
                        $M = $this->response;
                        if(isset($M[$c])) unset($M[$c]);
                    }
                    if($cn && $M) {
                        foreach($M as $i=>$o) {
                            $O = $this->getObject($o, $cn, $defaults, $callback, $args);
                            unset($M[$i]);
                            if(!is_null($O)) {
                                $R[] = $O;
                            }
                            unset($i, $o, $O);
                        }
                    } else {
                        $R = array_merge($R, $M);
                    }
                }
            }
            if($dataAttribute) {
                $this->config('dataAttribute', $dataAttribute);
                $dataAttribute = null;
            }
            if($cn) $cn=null;
            $this->response = $R;
            $this->_count = $count;
            unset($R);
        } else {
            if(($d=$this->config('dataAttribute')) && ($dataAttribute=$this->_expand($d))) {
                if($this->config('countAttribute')) {
                    $this->_count = $this->_getResponseAttribute($this->config('countAttribute'));
                }
                $R=$this->_getResponseAttribute($dataAttribute);
                $this->response = $R;
                unset($R);
                unset($dataAttribute);
            }
        }

        if(!$keepAlive) {
            unset($conn);
            $this->disconnect($this->schema('database'));
        }

        unset($body);

        if($cn && $this->response) {
            foreach($this->response as $i=>$o) {
                $O = $this->getObject($o, $cn, $defaults, $callback, $args);
                unset($this->response[$i]);
                if(!is_null($O)) {
                    $this->response[$i] = $O;
                }
                unset($i, $o, $O);
            }
        }

        unset($r);
        return $this;
    }

    public function decodeJson($s)
    {
        $r = json_decode($s, true, 512, JSON_INVALID_UTF8_IGNORE|JSON_BIGINT_AS_STRING);
        if($r===null) {
            $err = json_last_error();
            if($err) {
                $errs = array (
                  0 => 'JSON_ERROR_NONE',
                  1 => 'JSON_ERROR_DEPTH',
                  2 => 'JSON_ERROR_STATE_MISMATCH',
                  3 => 'JSON_ERROR_CTRL_CHAR',
                  4 => 'JSON_ERROR_SYNTAX',
                  5 => 'JSON_ERROR_UTF8',
                  6 => 'JSON_ERROR_RECURSION',
                  7 => 'JSON_ERROR_INF_OR_NAN',
                  8 => 'JSON_ERROR_UNSUPPORTED_TYPE',
                  9 => 'JSON_ERROR_INVALID_PROPERTY_NAME',
                  10 => 'JSON_ERROR_UTF16',
                );
                if(isset($errs[$err])) {
                    tdz::log('[ERROR] JSON decoding error: '.$errs[$err]);
                } else {
                    tdz::log('[ERROR] JSON unknown error: '.$err);
                }
            }
        }

        return $r;
    }

    public function decodeGzip($s)
    {
        // Perform GZIP decompression:
        $ctx = inflate_init(ZLIB_ENCODING_GZIP);
        $len = strlen($s);
        $cur = 0;
        $r = null;
        $i = 1000;
        while($cur < $len && $i--) {
            if($cur) {
                $s = substr($s, $cur);
                $len = strlen($s);
            }
            $r .= inflate_add($ctx, $s, ZLIB_FINISH);
            $cur = inflate_get_read_len($ctx);
            if(!$cur) break;
        }
        return $r;
    }

    public function decodeBase64($s)
    {
        return base64_decode($s);
    }

    public function decodeCsv($s)
    {
        $t0 = microtime(true);
        $sep = $this->config('csvSeparator');
        if(!$sep) $sep = ',';
        $enc = $this->config('csvEnclosure');
        if(!$enc) $enc = '"';
        $esc = $this->config('csvEscape');
        if(!$esc) $esc = '\\';

        $d = str_getcsv($s, "\n", $enc, $esc);
        $r = [];
        $keys = null;
        while($line=array_shift($d)) {
            if(substr(trim($line), 0, 1)==='#') continue; // comment

            if(!$keys) {
                $keys = str_getcsv($line, $sep, $enc, $esc);
            } else {
                $r[] = array_combine($keys, str_getcsv($line, $sep, $enc, $esc));
            }
        }

        return $r;
    }


    public function nextPage($q=null)
    {
        if(is_null($q)) $q = $this->buildQuery();
        if(!$this->_next) return;

        if($c=$this->config('nextPage')) {
            $url = static::urlParam($q, [$c=>$this->_next]);
        } else {
            $url = $this->_next;
        }

        return $url;
    }

    public static function urlParam($url, $s)
    {
        if(!is_array($s)) {
            $a = [];
            parse_str($s, $a);
        } else {
            $a = $s;
        }
        foreach($a as $n=>$v) {
            if(preg_match('#(\?|\&)'.preg_quote($n, '#').'=([^\&]*)(&|$)#', $url, $m)) {
                $url = str_replace($m[0], $m[1].$n.'='.urlencode($v).$m[3], $url);
            } else if(strpos($url, '?')!==false) {
                $url .= ((substr($url, -1)!='&')?'&' :'').$n.'='.urlencode($v);
            } else {
                $url .= '?'.$n.'='.urlencode($v);
            }
            unset($a[$n], $n, $v);
        }

        return $url;
    }

    protected function _expand($s)
    {
        if(preg_match_all('/\{\$([a-zA-Z0-9\-\_]+)\}/', $s, $m)) {
            $r = [];
            foreach($m[0] as $k=>$v) {
                $r[$v] = $this->schema($m[1][$k]);
            }
            if($r) $s = strtr($s, $r);
        }
        return $s;

    }

    protected function _getResponseAttribute($dataAttribute=null)
    {
        if(is_null($dataAttribute)) $dataAttribute = $this->config('dataAttribute');
        if(!$dataAttribute) return $this->response;
        if(strpos($dataAttribute, '|')!==false) {
            $da = explode('|', $dataAttribute);
            foreach($da as $i=>$o) {
                if($R=$this->_getResponseAttribute($o)) {
                    return $R;
                }
                unset($da[$i], $i, $o);
            }
            return null;
        }

        $dataAttribute = $this->_expand($dataAttribute);
        if(!$dataAttribute) return;

        $R = null;
        if($this->response && is_array($this->response)) {
            if(isset($this->response[$dataAttribute])) {
                $R = $this->response[$dataAttribute];
            } else if(strpos($dataAttribute, '.')!==false) {
                $p = explode('.', $dataAttribute);
                $R = $this->response;
                while($n=array_shift($p)) {
                    if(isset($R[$n])) {
                        $R = $R[$n];
                    } else {
                        $R = null;
                        break;
                    }
                }
            }
        } else {
            return $this->response;
        }
        return $R;
    }

    public function query($q, $as='array', $cn=null, $prop=null, $callback=null, $args=null)
    {
        try {
            if ($as=='array') {
                $this->run($q);
                return $this->response;
            } else {
                $this->run($q, null, true, null, $cn, $prop, $callback, $args);
                return $this->response;
            }
        } catch(Exception $e) {
            return false;
        }
    }

    public function fetchAll($as='class', $className=null, $prop=null)
    {
        if($as=='array' || !$this->response || !($cn = ($className)?($className):($this->schema('className')))) {
            return $this->response;
        }
        $R = array();
        foreach($this->response as $i=>$o) {
            $R[$i] = $this->getObject($o, $cn, $prop);
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
        $id = static::connect($this->schema('connection'))->lastInsertId($fn);
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
            $data = $data->asArray('save', null, false, false);
        }
        if(!$conn) $conn = $this->connect($this->schema('database'));
        $H = $this->config('requestHeaders');
        if(!is_array($H)) $H = array();
        if($data && !is_string($data)) {
            if($this->config('postFormat')==='json') {
                $data = json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                $H[] = 'content-type: application/json'.(($c=$this->config('postCharset')) ?';charset='.$c :'');
            } else {
                $data = http_build_query($data);
            }
        }

        if($data) {
            curl_setopt($conn, CURLOPT_POST, true);
            curl_setopt($conn, CURLOPT_POSTFIELDS, $data);
            curl_setopt($conn, CURLOPT_HTTPHEADER, $H);
            $this->_method = 'POST';
        }
        if($method) {
            $this->_method = $method;
            curl_setopt($conn, CURLOPT_CUSTOMREQUEST, $method);
        }
        $R = $this->run($url, $conn);
        return $R;
    }

    public function insert($M, $conn=null)
    {
        $pk = $M->pk;
        if(is_array($pk)) $pk = array_shift($pk);
        $pk = urlencode($pk);
        $tn = urlencode($this->schema('tableName'));
        $action = sprintf($this->config('insertPath'), $tn, $pk);
        if($c=$this->config('insertQuery')) $action .= '?'.sprintf($c, $tn, $pk);
        try {
            $R = $this->runAction($action, $M, $this->config('insertMethod'));
            if($this->config('saveToModel') && $R && is_object($R) && $R->response) {
                foreach($R->response as $fn=>$v) {
                    if(is_int($fn)) {
                        break;
                    }
                    if($v && $v!=$M->$fn) {
                        $M->$fn = $v;
                    }
                }
            } else {
                $pk = $M::pk(null, true);
                if($R && $pk && is_array($d=$R->response)) {
                    foreach($pk as $fn) {
                        if(isset($d[$fn])) {
                            $M->$fn = $d[$fn];
                        }
                    }
                }
            }
            if($M::$schema->audit) {
                $M->auditLog('insert', $M->getPk(), $M->asArray('save', null, false, false));
            }
            return $R;
        } catch(Exception $e) {
            $msg = $e->getMessage();
            if(!(substr($msg, 0, 1)=='<' && strpos(substr($msg, 0, 100), 'tdz-i-msg'))) {
                $msg = array(tdz::t('Could not save %s.', 'exception'), $M::label());
            }
            throw new Tecnodesign_Exception($msg);
        }
    }

    public function preview($pk, $conn=null)
    {
        if(is_array($pk)) $pk = array_shift($pk);
        $pk = urlencode($pk);
        $tn = urlencode($this->schema('tableName'));
        $action = sprintf($this->config('previewPath'), $tn, $pk);
        if($c=$this->config('previewQuery')) $action .= '?'.sprintf($this->config('previewQuery'), $tn, $pk);
        try {
            $R = $this->runAction($action, null, $this->config('previewMethod'));
            if($this->config('saveToModel') && $R && is_object($R) && $R->response) {
                $cn = $this->schema('className');
                return new $cn($R->response, false, false);
            }
            return $R;
        } catch(Exception $e) {
            $msg = $e->getMessage();
            if(!(substr($msg, 0, 1)=='<' && strpos(substr($msg, 0, 100), 'tdz-i-msg'))) {
                $msg = array(tdz::t('Could not fetch %s.', 'exception'), $M::label());
            }
            throw new Tecnodesign_Exception($msg);
        }
    }

    public function update($M, $conn=null)
    {
        $pk = $M->getPk();
        $pk = urlencode($pk);
        $tn = urlencode($this->schema('tableName'));
        $action = sprintf($this->config('updatePath'), $tn, $pk);
        if($c=$this->config('updateQuery')) $action .= '?'.sprintf($c, $tn, $pk);
        try {
            $R = $this->runAction($action, $M, $this->config('updateMethod'));
            if($this->config('saveToModel') && $R && is_object($R) && $R->response) {
                foreach($R->response as $fn=>$v) {
                    if(is_int($fn)) {
                        break;
                    }
                    if($v && $v!=$M->$fn) {
                        $M->$fn = $v;
                    }
                }
            }
            if($M::$schema->audit) {
                $M->auditLog('update', $M->getPk(), $M->asArray('save', null, false, false));
            }
            return $R;
        } catch(Exception $e) {
            $msg = $e->getMessage();
            if(!(substr($msg, 0, 1)=='<' && strpos(substr($msg, 0, 100), 'tdz-i-msg'))) {
                $msg = array(tdz::t('Could not save %s.', 'exception'), $M::label());
            }
            throw new Tecnodesign_Exception($msg);
        }
    }

    public function delete($M, $conn=null)
    {
        $pk = $M->getPk();
        $pk = urlencode($pk);
        $tn = urlencode($this->schema('tableName'));
        $action = sprintf($this->config('deletePath'), $tn, $pk);
        if($c=$this->config('deleteQuery')) $action .= '?'.sprintf($c, $tn, $pk);
        try {
            $R = $this->runAction($action, $M->getPk(null, true), $this->config('deleteMethod'));
            if($this->config('saveToModel') && $R && is_object($R) && $R->response) {
                foreach($R->response as $fn=>$v) {
                    if(is_int($fn)) {
                        break;
                    }
                    if($v && $v!=$M->$fn) {
                        $M->$fn = $v;
                    }
                }
            }
            if($M::$schema->audit) {
                $M->auditLog('delete', $M->getPk(), $M->asArray('save', null, false, false));
            }
            return $R;
        } catch(Exception $e) {
            $msg = $e->getMessage();
            if(!(substr($msg, 0, 1)=='<' && strpos(substr($msg, 0, 100), 'tdz-i-msg'))) {
                $msg = array(tdz::t('Could not save %s.', 'exception'), $M::label());
            }
            throw new Tecnodesign_Exception($msg);
        }
    }

    public function lastQuery()
    {
        return $this->_last;
    }

    public function response($attr=null)
    {
        if($attr) {
            return $this->_getResponseAttribute($attr);
        }

        return $this->response;
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
        if(tdz::$perfmon>0) tdz::log('[INFO] '.tdz::formatNumber(microtime(true)-tdz::$perfmon).'s '.tdz::formatBytes(memory_get_peak_usage()).' mem: '.tdz::$variables['timestamp'][$cn]);
        return tdz::$variables['timestamp'][$cn];
    }

}
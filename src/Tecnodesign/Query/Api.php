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
        $limitCount='0',
        $limitAbsolute=false,
        $offset='offset',
        $sort='sort',
        $scope='scope',
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
        $errorAttribute='error.message|error',
        $headerCount='x-total-count',
        $headerModified='last-modified',
        $dataAttribute,
        $countAttribute,
        $enableOffset=true,
        $pagingAttribute,
        $cookieJar,
        $connectionCallback;
    protected static $options, $conn=array();
    protected $_schema, $_method, $_url, $_scope, $_select, $_where, $_orderBy, $_limit, $_offset, $_options, $_last, $_count, $_unique, $headers, $response;

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
                    if(static::$queryTableName && isset($s::$schema['tableName'])) {
                        $url .= '/'.$s::$schema['tableName'];
                    }
                    $this->_url = $url.$qs;
                    unset($url);
                    unset($db['dsn']);
                }
                if(isset($db['options'])) {
                    $this->_options = $db['options'];
                } else if($db) {
                    $this->_options = $db;
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
        if(static::$cookieJar && is_string(static::$cookieJar)) {
            if(file_exists(static::$cookieJar)) @unlink(static::$cookieJar);
            static::$cookieJar = true;
        }
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
            curl_setopt(self::$C[$n], CURLOPT_HTTPHEADER, static::$requestHeaders);
        }
        if(static::$cookieJar) {
            if(!is_string(static::$cookieJar)) {
                static::$cookieJar = tempnam(Tecnodesign_Cache::cacheDir(), 'cookie');
            }
            curl_setopt(self::$C[$n], CURLOPT_COOKIEFILE, static::$cookieJar);
            curl_setopt(self::$C[$n], CURLOPT_COOKIEJAR, static::$cookieJar);
        }
        if(static::$connectionCallback) {
            self::$C[$n] = call_user_func(static::$connectionCallback, self::$C[$n], $n);
        }

        if(!self::$C[$n]) {
            tdz::log('[INFO] Failed connection to '.$n);
            if($exception) throw new Tecnodesign_Exception(array(tdz::t('Could not connect to %s.', 'exception'), $n));
        }
        return self::$C[$n];
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
        $k = (isset($this->_options['limit']))?($this->_options['limit']):(static::$limit);
        if($k) {
            $qs .= (($qs)?('&'):('?'))
                 . $k.'='.static::$limitCount;
        }
        return $qs;
    }

    public function buildQueryOrder($qs='')
    {
        $k = (isset($this->_options['sort']))?($this->_options['sort']):(static::$sort);
        if($k) {
            $order = '';
            foreach($this->_orderBy as $fn=>$asc) {
                if(!$asc || $asc=='desc') $fn = '-'.$fn;
                $order .= ($order)?(','.$fn):($fn);
                unset($fn, $asc);
            }
            $qs .= (($qs)?('&'):('?'))
                 . $k.'='.urlencode($order);
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
        if(static::$queryPath) {
            $url .= sprintf(static::$queryPath, $this->schema('tableName'), null);
        }
        if($this->_where) {
            $qs = $this->buildQueryWhere($qs);
        }
        if($this->_scope) {
            $k = (isset($this->_options['scope']))?($this->_options['scope']):(static::$scope);
            if($k) {
                $qs .= ((strpos($qs, '?')!==false)?('&'):('?'))
                     . $k.'='.urlencode($this->_scope);
                unset($k);
            }
            if($this->_select && ($cn=$this->_schema) && implode(',',$this->_select)===implode(',',$cn::columns($this->_scope))) {
                $this->_select=null;
            }
        }
        if($this->_select) {
            $k = (isset($this->_options['fieldnames']))?($this->_options['fieldnames']):(static::$fieldnames);
            $qs .= ((strpos($qs, '?')!==false)?('&'):('?'))
                 . $k.'='.urlencode(implode(',', $this->_select));
            unset($k);
        }
        if($count) {
            $qs = $this->buildQueryCount($qs, $count);
        } else if(!$this->_unique) {
            if(!is_null($this->_limit)) {
                $k = (isset($this->_options['limit']))?($this->_options['limit']):(static::$limit);
                if($k) {
                    $limit = (int)$this->_limit;
                    if(static::$limitAbsolute && !is_null($this->_offset)) {
                        $limit += (int) $this->_offset -1;
                    }
                    $qs .= ((strpos($qs, '?')!==false)?('&'):('?'))
                         . $k.'='.$limit;
                }
                unset($k);
            }
            if(!is_null($this->_offset)) {
                $k = (isset($this->_options['offset']))?($this->_options['offset']):(static::$offset);
                if($k) {
                    $qs .= ((strpos($qs, '?')!==false)?('&'):('?'))
                         . $k.'='.((int)$this->_offset);
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
        if(is_string($s) && ($cn=$this->_schema) && isset($cn::$schema['scope'][$s])) {
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
        return $this->query($this->buildQuery(), 'class', $this->schema('className'), $prop, $callback, $args);
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
                $this->query($this->buildQuery(true));
            }
        }
        if(is_null($this->_count)) {
            if(!is_null($r=$this->header('headerCount'))) {
                $this->_count = (int) $r;
            } else if($this->response) {
                $this->_count = count($this->response);
            }
        }

        return $this->_count;
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

    public static function runStatic($q, $n='', $data=null, $method=null, $headers=null, $callback='json')
    {
        $conn = static::connect($n);
        curl_setopt($conn, CURLOPT_URL, $q);

        if(!is_array($headers)) $headers = array();

        if($data && !is_string($data)) {
            if(static::$postFormat && static::$postFormat=='json') {
                $data = json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                //$headers[] = 'content-type: application/json';
            } else {
                $data = http_build_query($data);
            }
        }

        if($data) {
            curl_setopt($conn, CURLOPT_POST, true);
            curl_setopt($conn, CURLOPT_POSTFIELDS, $data);
            \tdz::log(__METHOD__, $data);
        }

        if($method && $method!='GET') {
            curl_setopt($conn, CURLOPT_CUSTOMREQUEST, $method);
        }

        if($headers) {
            curl_setopt($conn, CURLOPT_HTTPHEADER, $headers);
        }
        $r = curl_exec($conn);
        $msg = '';
        if(!$r) {
            tdz::log('[ERROR] Curl error: '.curl_error($conn));
            return false;
        }
        if($callback && $callback=='json') {
            if(isset(static::$curlOptions[CURLOPT_HEADER]) && static::$curlOptions[CURLOPT_HEADER]) {
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
                unset($body);
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
        if(tdz::$log) tdz::log("[INFO] API call to $q (".ceil(memory_get_peak_usage() * 0.000001).'M, '.substr((microtime(true) - TDZ_TIME), 0, 5).'s)');
        if(!$conn) $conn = static::connect($this->schema('database'));
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
        if(isset($this->_options['username']) && isset($this->_options['password'])) {
            curl_setopt($conn, CURLOPT_USERPWD, $this->_options['username'].':'.$this->_options['password']);
            curl_setopt($conn, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }
        $this->_last = $q;
        if(!$this->_method) {
            $this->_method = 'GET';
        } else if($this->_method!='GET' && $this->_method!='POST') {
            curl_setopt($conn, CURLOPT_CUSTOMREQUEST, $this->_method);
        }
        $this->cleanup();
        $r = curl_exec($conn);
        $msg = '';
        $body = null;
        if(!$r) {
            $msg = curl_error($conn);
        } else {
            if(isset(static::$curlOptions[CURLOPT_HEADER]) && static::$curlOptions[CURLOPT_HEADER]) {
                list($this->headers, $body) = preg_split('/\r?\n\r?\n/', $r, 2);
                while(preg_match('#^HTTP/1.[0-9]+ [0-9]+ #', $body)) {
                    list($this->headers, $body) = preg_split('/\r?\n\r?\n/', $body, 2);
                }
            } else {
                $this->headers = null;
                $body = $r;
            }
            $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);
            if($this->headers && strpos($this->header('content-type'), 'json')===false) {
                $this->response = $body;
            } else {
                $this->response = json_decode($body, true);
                if($this->response===null) {
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
            }
        }
        unset($r);

        if($this->response && is_array($this->response) && $this->_unique) {
            $this->response = array($this->response);
        }

        if($enablePaging && $this->response && static::$pagingAttribute && isset($this->response[static::$pagingAttribute])) {
            $url = $q;
            $R = array();
            $dataAttribute = null;
            if($this->response && static::$dataAttribute) {
                $dataAttribute = static::$dataAttribute;
                static::$dataAttribute = null;
                $R = $this->_getResponseAttribute($dataAttribute);
                if(!$R && !is_array($R)) $R=array();
                /*
                if(isset($this->response[$dataAttribute])) {
                    $R = $this->response[$dataAttribute];
                }
                */
            } else {
                $R = $this->response;
                unset($R[static::$pagingAttribute]);
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
            while(isset($this->response[static::$pagingAttribute]) && $url!=$this->response[static::$pagingAttribute]) {
                $url = $this->response[static::$pagingAttribute];
                unset($this->response[static::$pagingAttribute]);
                $this->response = null;

                $this->run($url, $conn, false, true);

                if($this->response) {
                    if($dataAttribute) {
                        $M = $this->_getResponseAttribute($dataAttribute);
                        $count += count($M);
                        /*
                        if(isset($this->response[$dataAttribute])) {
                            $count += count($this->response[$dataAttribute]);
                            $M = $this->response[$dataAttribute];
                        }
                        */
                    } else {
                        unset($this->response[static::$pagingAttribute]);
                        $count += count($this->response);
                        $M = $this->response;
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
                static::$dataAttribute = $dataAttribute;
                $dataAttribute = null;
            }

            if($cn) $cn=null;
            $this->response = $R;
            unset($R);
        } else {
            if(static::$dataAttribute) {
                if(static::$countAttribute) {
                    $this->_count = $this->_getResponseAttribute(static::$countAttribute);
                }
                if($R=$this->_getResponseAttribute(static::$dataAttribute)) {
                    $this->response = $R;
                } else if($cn) {
                    $cn = null;
                    //$this->response = null;
                }
                unset($R);
            }
            /*
            if($this->response && is_array($this->response) && static::$dataAttribute) {
                if(static::$countAttribute && isset($this->response[static::$countAttribute])) {
                    $this->_count = $this->response[static::$countAttribute];
                }
                if(isset($this->response[static::$dataAttribute])) {
                    $this->response = $this->response[static::$dataAttribute];
                //} else {
                //    $this->response = array();
                }
            }
            */
        }

        if(!$keepAlive) {
            unset($conn);
            self::disconnect($this->schema('database'));
        }

        $m = null;
        if($msg || preg_match(static::$errorPattern, $this->headers, $m)) {
            if(!$msg && (!static::$errorAttribute || !($msg=$this->_getResponseAttribute(static::$errorAttribute)))) {
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
        } else if(!preg_match(static::$successPattern, $this->headers)) {
            $this->response = false;
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

    protected function _getResponseAttribute($dataAttribute=null)
    {
        if(is_null($dataAttribute)) $dataAttribute = static::$dataAttribute;
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

        $R = null;
        if($this->response && is_array($this->response)) {
            if(isset($this->response[$dataAttribute])) {
                $R = $this->response[$dataAttribute];
            } else if(strpos($dataAttribute, '.') || strpos($dataAttribute, '{$')!==false) {
                $p = explode('.', $dataAttribute);
                $R = $this->response;
                while($n=array_shift($p)) {
                    if(preg_match('/\{\$([a-zA-Z0-9\-\_]+)\}/', $dataAttribute, $m)) {
                        $prop=$this->schema($m[1]);
                        $n = str_replace($m[0], $prop, $n);
                    }
                    if(isset($R[$n])) {
                        $R = $R[$n];
                    } else {
                        $R = null;
                        break;
                    }
                }
            }
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
                /*
                $this->run($q, null, true);
                $arg = func_get_args();
                array_shift($arg);
                if(isset($arg[1])) {
                    return $this->fetchAll($arg[0], $arg[1]);
                } else {
                    return $this->response;
                }
                */
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
        if(!$conn) $conn = static::connect($this->schema('database'));
        $H = static::$requestHeaders;
        if(!is_array($H)) $H = array();
        if($data && !is_string($data)) {
            if(static::$postFormat && static::$postFormat=='json') {
                $data = json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                $H[] = 'content-type: application/json';
            } else {
                $data = http_build_query($data);
            }
        }

        if($data) {
            curl_setopt($conn, CURLOPT_POST, true);
            curl_setopt($conn, CURLOPT_POSTFIELDS, $data);
            curl_setopt($conn, CURLOPT_HTTPHEADER, $H);
            $this->_method = 'POST';
            \tdz::log(__METHOD__, $data);
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
        $action = sprintf(static::$insertPath, $tn, $pk);
        if(static::$insertQuery) $action .= '?'.sprintf(static::$insertQuery, $tn, $pk);
        try {
            $R = $this->runAction($action, $M, static::$insertMethod);
            if(static::$saveToModel && $R && is_object($R) && $R->response) {
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
        $action = sprintf(static::$previewPath, $tn, $pk);
        if(static::$previewQuery) $action .= '?'.sprintf(static::$previewQuery, $tn, $pk);
        try {
            $R = $this->runAction($action, null, static::$previewMethod);
            if(static::$saveToModel && $R && is_object($R) && $R->response) {
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
        $pk = $M->pk;
        if(is_array($pk)) $pk = array_shift($pk);
        $pk = urlencode($pk);
        $tn = urlencode($this->schema('tableName'));
        $action = sprintf(static::$updatePath, $tn, $pk);
        if(static::$updateQuery) $action .= '?'.sprintf(static::$updateQuery, $tn, $pk);
        try {
            $R = $this->runAction($action, $M, static::$updateMethod);
            if(static::$saveToModel && $R && is_object($R) && $R->response) {
                foreach($R->response as $fn=>$v) {
                    if(is_int($fn)) {
                        break;
                    }
                    if($v && $v!=$M->$fn) {
                        $M->$fn = $v;
                    }
                }
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
        $pk = $M->pk;
        if(is_array($pk)) $pk = array_shift($pk);
        $pk = urlencode($pk);
        $tn = urlencode($this->schema('tableName'));
        $action = sprintf(static::$deletePath, $tn, $pk);
        if(static::$deleteQuery) $action .= '?'.sprintf(static::$deleteQuery, $tn, $pk);
        try {
            $R = $this->runAction($action, $M->getPk(null, true), static::$deleteMethod);
            if(static::$saveToModel && $R && is_object($R) && $R->response) {
                foreach($R->response as $fn=>$v) {
                    if(is_int($fn)) {
                        break;
                    }
                    if($v && $v!=$M->$fn) {
                        $M->$fn = $v;
                    }
                }
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

    public function response()
    {
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
        if(tdz::$perfmon>0) tdz::log(__METHOD__.': '.tdz::formatNumber(microtime(true)-tdz::$perfmon).'s '.tdz::formatBytes(memory_get_peak_usage()).' mem: '.tdz::$variables['timestamp'][$cn]);
        return tdz::$variables['timestamp'][$cn];
    }

}
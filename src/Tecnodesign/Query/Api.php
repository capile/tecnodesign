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
    public static $microseconds=6, 
        $envelope,
        $search='q',
        $fieldnames='fieldnames',
        $limit='limit',
        $offset='offset',
        $sort='sort',
        $scope='scope',
        $curlOptions=array(
            CURLOPT_HEADER=>1,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => 'API browser',
            CURLOPT_AUTOREFERER    => true,         // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => 120,          // timeout on connect
            CURLOPT_TIMEOUT        => 120,          // timeout on response
            CURLOPT_MAXREDIRS      => 2,           // stop after 10 redirects
            CURLOPT_POST           => false,
            CURLOPT_HTTPHEADER     => array('Accept'=>'application/json'),
        ),
        $successPattern='/ 200 +OK/',
        $headerCount='X-Total-Count',
        $headerModified='Last-Modified';
    protected static $options, $conn=array();
    protected $_schema, $_url, $_scope, $_select, $_where, $_orderBy, $_limit, $_offset, $_options, $headers, $response;

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
    public static function connect($n='', $exception=true, $tries=3)
    {
        static $C=array();

        if(!isset($C[$n])) {
            $C[$n] = curl_init();
            // cookie-based authentication
            /*
            curl_setopt($C[$n], CURLOPT_URL, self::$auth);
            curl_setopt_array($C[$n], array(
                CURLOPT_URL=>self::$auth,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => self::$authParams,
            )+self::$ccurlOptions);
            $req = curl_exec($C[$n]);
            if(preg_match_all('/Set-Cookie: ([^;]+)/', $req, $m)) {
                $cookies = implode('; ', $m[1]);
                curl_setopt($C[$n], CURLOPT_COOKIE, $cookies);
            } else {
                self::log('Couldn\'t authenticate '.$id."\n".self::$auth."\n\n".$req);
                continue;
            }
            unset($r);
            */
            $O = static::$curlOptions;
            curl_setopt_array($C[$n], $O);
        }
        return $C[$n];
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

        //if(!is_array($w)) {
        //    // must get from primary key or first column
        //    $pk = $this->schema()->getScope('primary');
        //    if(!$pk) return '';
        //    else if(count($pk)==1) $w = array( $pk[0] => $w );
        //    else {
        //        $v = preg_split('/\s*[\,\;]\s*/', $w, count($pk));
        //        unset($w);
        //        $w=array();
        //        foreach($v as $i=>$k) {
        //            $w[$pk[$i]] = $k;
        //            unset($i, $k);
        //        }
        //        unset($v);
        //    }
        //}
        //$r='';
        //$op = '=';
        //$xor = 'and';
        //$not = false;
        //static $cops = array('>=', '<=', '<>', '>', '<');
        //static $like = array('%', '$', '^', '*', '~');
        //static $xors = array('and'=>'and', '&'=>'and', 'or'=>'or', '|'=>'or');
        //foreach($w as $k=>$v) {
        //    if(is_int($k)) {
        //        if(is_array($v)) {
        //            if($v = $this->getWhere($v)) {
        //                $r .= ($r)?(" {$xor} ({$v})"):("({$v})");
        //            }
        //        } else {
        //            if(substr($v, 0, 1)=='!') {
        //                $not = true;
        //                $v = substr($v, 1);
        //            }
        //            if(in_array($v, $cops)) {
        //                $op = $v;
        //            } else if(in_array($v[0], $like)) {
        //                $op = $v[0];
        //            } else if(isset($xors[$v])) {
        //                $xor = $xors[$v];
        //            }
        //        }
        //    } else {
        //        $cop = $op;
        //        $cxor = $xor;
        //        $cnot = $not;
        //        if(preg_match('/(\~|\<\>|[\<\>\^\$\*\!\%]?\=?|[\>\<])$/', $k, $m) && $m[1]) {
        //            // operators: <=  >= < > ^= $=
        //            $cop = (!in_array($m[1], $cops))?(substr($m[1], 0, 1)):($m[1]);
        //            $k = trim(substr($k, 0, strlen($k) - strlen($m[0])));
        //            unset($m);
        //        }
        //        $fn = $this->getAlias($k);
        //        if($fn) {
        //            $r .= ($r)?(" {$cxor}"):('');
        //            if(is_array($v) && count($v)==1) {
        //                $v = array_shift($v);
        //            }
        //            if($cop=='~') {
        //                // between
        //            } else if (is_array($v) && ($cop=='=' || $cop=='<>')) {
        //                foreach ($v as $vk=>$vs) {
        //                    $v[$vk] = self::escape($vs);
        //                    if($vs==''){
        //                        $v['']='null';
        //                    }
        //                    unset($vk, $vs);
        //                }
        //                $r .= " {$fn}".(($cnot || $cop=='<>')?(' not'):('')).' in('.implode(',',$v).')';
        //            } else if(is_array($v)) {
        //                $nv = array();
        //                if($cop!='=') $nv[] = $cop;
        //                if($cnot) $nv[] = '!';
        //                if($cxor!='and') $nv[] = $cxor;
        //                foreach($v as $vk=>$vs) {
        //                    $nv[] = array($k=>$vs);
        //                }
        //                $v = $this->getWhere($v);
        //                if($v) $r .= "({$v})";
        //            }
        //            else if(!$v && $cop=='=') $r .= (($cnot)?(' not'):(' '))."({$fn}=".self::escape($v)." or {$fn} is null)";
        //            else if(!$v && $cop=='<>') $r .= " ({$fn}<>".self::escape($v)." and {$fn} is not null)";
        //            else if($cop=='^') $r .= " {$fn}".(($cnot)?(' not'):(''))." like '".self::escape($v, false)."%'";
        //            else if($cop=='$') $r .= " {$fn}".(($cnot)?(' not'):(''))." like '%".self::escape($v, false)."'";
        //            else if($cop=='*') $r .= " {$fn}".(($cnot)?(' not'):(''))." like '%".self::escape($v, false)."%'";
        //            else if($cop=='%') $r .= " {$fn}".(($cnot)?(' not'):(''))." like '%".str_replace('-', '%', tdz::slug($v))."%'";
        //            else $r .= ($not)?(" not({$fn}{$cop}".self::escape($v).')'):(" {$fn}{$cop}".self::escape($v));
        //        }
        //        unset($cop, $cxor, $cnot);
        //    }
        //    unset($k, $fn, $v);
        //}
        //return trim($r);
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
        //$ofn = $fn=$f;
        //if(preg_match_all('#`([^`]+)`#', $fn, $m)) {
        //    $r = $s = array();
        //    foreach($m[1] as $i=>$nfn) {
        //        $s[]=$m[0][$i];
        //        $r[]=$this->getAlias($nfn);
        //        unset($i, $nfn);
        //    }
        //    return str_replace($s, $r, $fn);
        //} else if(preg_match('/@([a-z]+)\(([^\)]*)\)/', $fn, $m) && method_exists($this, $a='getFunction'.ucfirst($m[1]))) {
        //    return str_replace($m[0], $this->$a(trim($m[2])), $fn);
        //}
//
        //if(strpos($fn, '[')!==false && preg_match('/\[([^\]]+)\]/', $fn, $m)) {
        //    $fn = $m[1];
        //    $fnt = $m[0];
        //    $ofn = $fn;
        //    unset($m);
        //}
        //$ta='a';
        //$found=false;
        //$sc = $this->schema();
        //if (isset($sc['columns'][$fn])) {
        //    $found = true;
        //    $fn = $ta.'.'.$fn;
        //} else if(!$found) {
        //    $rnf = '';
        //    $cn = get_called_class();
        //    while(strpos($ofn, '.')) {
        //        @list($rn, $fn) = explode('.', $ofn,2);
        //        $ofn=$fn;
        //        $rnf .= ($rnf)?('.'.$rn):($rn);
        //        if(isset($sc->relations[$rn])) {
        //            $rcn = (isset($sc->relations[$rn]['class']))?($sc->relations[$rn]['class']):($rn);
        //            $rsc = $rcn::$schema;
        //            if(!isset($this->_alias[$rnf])) {
        //                $an = chr(97+count($this->_alias));
        //                $this->_alias[$rnf]=$an;
        //                if($sc->relations[$rn]['type']!='one') {
        //                    $this->_distinct = ' distinct';
        //                }
        //                $jtn = (isset($rsc->view))?('('.$rsc->view.')'):($rsc->table);
        //                if(!is_array($sc->relations[$rn]['foreign'])) {
        //                    $this->_from .= " left outer join {$jtn} as {$an} on {$an}.{$sc->relations[$rn]['foreign']}={$ta}.{$sc->relations[$rn]['local']}";
        //                } else {
        //                    $this->_from .= " left outer join {$jtn} as {$an} on";
        //                    foreach($sc->relations[$rn]['foreign'] as $rk=>$rv) {
        //                        $this->_from .= (($rk>0)?(' and'):(''))." {$an}.{$rv}={$ta}.{$sc->relations[$rn]['local'][$rk]}";
        //                    }
        //                }
        //                if(isset($sc->relations[$rn]['on'])) {
        //                    if(!is_array($sc->relations[$rn]['on'])) $sc->relations[$rn]['on']=array($sc->relations[$rn]['on']); 
        //                    foreach($sc->relations[$rn]['on'] as $rfn) {
        //                        list($rfn,$fnc)=explode(' ', $rfn, 2);
        //                        if(substr($rfn,0,strlen($rn))==$rn) $join .=  "and {$an}".substr($rfn,strlen($rn))." {$fnc} ";
        //                        else $join .= ' and '.$this->getAlias($rfn).' '.$fnc;
        //                        unset($rfn, $fnc);
        //                    }
        //                }
        //            } else {
        //                $an = $this->_alias[$rnf];
        //            }
        //            unset($sc, $rn);
        //            $sc = $rsc;
        //            unset($rsc);
        //            $ta=$an;
        //            $fn = $an.'.'.$fn;
        //            $found = true;
        //        } else {
        //            $found = false;
        //            break;
        //        }
        //    }
        //}
        //if(!$found) {
        //    if (isset($sc->relations[$fn])) {
        //        $found = true;
        //        $fn = $ta.'.'.$sc->relations[$fn]['local'];
        //    } else if (isset($sc->columns[$fn]) || property_exists($cn, $fn)) {
        //        $found = true;
        //        $fn = $ta.'.'.$fn;
        //    } else {
        //        tdz::log("Cannot find by [{$fn}] at [{$sc->table}]");
        //        throw new Exception("Cannot find by [{$fn}] at [{$sc->table}]");
        //    }
        //}
        //unset($found, $sc, $ta);
        //if(isset($fnt) && $fnt) {
        //    $fn = str_replace($fnt, $fn, $f);
        //    unset($fnt, $f);
        //}
        //return $fn;
    }

    public static function runStatic($n, $q)
    {
    }

    public function run($q)
    {
        $C = self::connect($this->schema('database'));
        /*
        if(substr($url, 0, 1)=='!') {
            $url = substr($url, 1);
            $O[CURLOPT_POST]=true;
            list($url, $O[CURLOPT_POSTFIELDS]) = explode('?', $url, 2);
        }
        //$O[CURLOPT_URL]=$url;
        */
        curl_setopt($C, CURLOPT_URL, $q);
        $r = curl_exec($C);
        if(!preg_match(static::$successPattern, $r)) {
            $this->headers = $r;
            $this->response = false;
        } else if(isset(static::$curlOptions[CURLOPT_HEADER]) && static::$curlOptions[CURLOPT_HEADER]) {
            list($this->headers, $body) = preg_split('/\r?\n\r?\n/', $r, 2);
            $this->response = json_decode($body, true);
            unset($body);
        } else {
            $this->headers = null;
            $this->response = json_decode($body, true);
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


    public function transaction($id=null)
    {
    }
    
    public function commit($id=null)
    {
    }

    public function rollback($id=null)
    {
    }

    public function insert($o)
    {
    }

    public function update($o)
    {
    }
    public function delete()
    {
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
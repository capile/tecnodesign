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
class Tecnodesign_Query_Soap
{
    const TYPE='soap', DRIVER='soap';
    public static $connectionCallback;
    protected static $options, $conn=array(),
        $wsdlOptions=[
            'soap_version' => SOAP_1_2,
            'cache_wsdl' =>  WSDL_CACHE_DISK,
            //'features' =>  SOAP_SINGLE_ELEMENT_ARRAYS,
        ];
    protected $_schema, $_method, $_wsdl, $_url, $_client, $_scope, $_select, $_where, $_orderBy, $_limit, $_offset, $_options, $_last, $_count, $_unique, $headers, $response;

    public function __construct($s=null)
    {
        if($s) {
            $db = null;
            if(is_object($s)) {
                $this->_schema = get_class($s);
            } else if((is_array($s) && ($db=$s)) || ($db=Tecnodesign_Query::database($s))) {
            } else if(property_exists($s, 'schema')) {
                $this->_schema = $s;
            }
            if(!$db && property_exists($s, 'schema') && isset($s::$schema['database'])) {
                $db = Tecnodesign_Query::database($s::$schema['database']);
            }

            if($db) {
                $wsdl = (isset($db['dsn'])) ?$db['dsn'] :null;
                if($wsdl && substr($wsdl, 0, 5)==='soap:') $wsdl = substr($wsdl, 5);

                if($wsdl) $this->_wsdl = $wsdl;

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

    protected static $C=array();
    public function connect($n=null, $exception=true, $tries=3)
    {
        if(is_null($n)) $n = $this->_wsdl;
        if(!isset(self::$C[$n])) {
            $o = (isset($this->_options['wsdl'])) ?$this->_options['wsdl'] :static::$wsdlOptions;
            $o['exceptions'] = (bool) $exception;
            self::$C[$n] = new SoapClient($n, $o);
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


    public function __toString()
    {
        return (string) $this->buildQuery();
    }

    // move this to curl requests?
    public function disconnect($n='')
    {
        if(isset(self::$C[$n])) {
            self::$C[$n]=null;
            unset(self::$C[$n]);
        }
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

    public function runAction($action, $data=null, $method=null, $conn=null)
    {
        $Q = $this->connect($conn);
        if($this->_options['arguments']) {
            $data = (is_array($data) && $data) ?$this->_options['arguments'] + $data :$this->_options['arguments'];
        }
        if($method) {
        }
        $this->response = $Q->$action($data);
        return $this->response;
    }    

    public function lastQuery()
    {
        return $this->_last;
    }

    public function response()
    {
        return $this->response;
    }
}
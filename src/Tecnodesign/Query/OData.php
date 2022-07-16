<?php
/**
 * OData as a database abstraction
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   3.0
 */
class Tecnodesign_Query_OData extends Tecnodesign_Query_Api
{
    public static 
        $microseconds=6, 
        $envelope,
        $search='q',
        $fieldnames='$select',
        $limit='$top',
        $offset='$skip',
        $sort='$orderby',
        $scope,
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
        $requestHeaders = array(
            'Accept: application/json',
            'Prefer: odata.maxpagesize=100',
            'OData-MaxVersion: 4.0',
            'OData-Version: 4.0'
        ),
        $dataAttribute='value',
        $pagingAttribute='@odata.nextLink',
        $countWhereSupported=false,
        $enableOffset=false,
        $connectionCallback;

    public function buildQueryWhere($qs='')
    {
        $url = '';
        if($this->_where) {
            $r = '';
            static $ops = array(
                '=' => 'eq',
                '!='=> 'ne',
                '>' => 'gt',
                '<' => 'lt',
                '>='=> 'ge',
                '<='=> 'le',
            );
            foreach($this->_where as $fn=>$v) {
                $fn = trim($fn);
                $op = 'eq';
                $xor = 'and';
                if(preg_match('/[\=\<\>\!\^\$]+$/', $fn, $m)) {
                    $fn = substr($fn, 0, strlen($fn)-strlen($m[0]));
                    if(isset($ops[$m[0]])) {
                        $op = $ops[$m[0]];
                    } else {
                        tdz::log(__METHOD__.': operator not implemented: '.$m[1]);
                        continue;
                    }
                    unset($m);
                }
                if(substr($fn, 0, 1)=='|') {
                    $xor='or';
                    $fn = substr($fn,1);
                } else if(substr($fn, 0, 1)=='!') {
                    $xor='not';
                    $fn = substr($fn,1);
                }
                $fd = $this->schema('columns/'.$fn);
                if(!$fd) $fd = array();
                if(isset($fd['type']) && $fd['type']=='guid') {
                    $url = '('.$v.')';
                    $this->_unique = true;
                    continue;
                } else if(!is_int($v)) {
                    if(is_string($v)) {
                        if(!isset($fd['type']) || ($fd['type']!='int' && substr($fd['type'], 0, 4)!='date')) {
                            $v = escapeshellarg($v);
                        }
                    } else {
                        // needs to add handler for multiple values
                        continue;
                    }
                }
                $r .= (($r)?(" {$xor} "):(''))
                    . "{$fn} {$op} {$v}";
            }
            if($r) {
                return $url.$qs.(($qs)?('&'):('?'))
                    . '$filter='.urlencode($r);

            }
        }
        return $url.$qs;
    }

    public function buildQueryCount($qs='')
    {
        if($qs) {
            $qs = preg_replace('/\$(select|top|orderby)=[^\&]+\&?/', '', $qs);
            if($qs == '?') $qs = '';
        }
        return '/$count'.$qs;
    } 

    public function buildQueryOrder($qs='')
    {
        $k = (isset($this->_options['sort']))?($this->_options['sort']):(static::$sort);
        if($k) {
            $order = '';
            foreach($this->_orderBy as $fn=>$asc) {
                if(is_int($fn)) {
                    // fix: look scope and fetch field name
                    continue;
                }


                if(!$asc || $asc=='desc') $fn .= ' desc';
                else $fn .= ' asc';
                $order .= ($order)?(','.$fn):($fn);
                unset($fn, $asc);
            }
            $qs .= (($qs)?('&'):('?'))
                 . $k.'='.urlencode($order);
        }
        unset($k);
        return $qs;
    } 

    public function count($column='1')
    {
        if(is_null($this->_count)) {
            if(!$this->_schema) return false;
            if(is_null($this->response)) {
                $count = (!$this->_where || static::$countWhereSupported);
                $this->query($this->buildQuery($count));
            }
            $this->_count = 0;
            if($this->response) {
                if(is_array($this->response)) {
                    $this->_count = count($this->response);
                } else {
                    $this->_count = (int)$this->response;
                }
            }
            $this->response = null;
        }
        return $this->_count;
    }

}
<?php
/**
 * Tecnodesign Collection
 * 
 * A collection is an extended array which extends its unknown properties to its
 * collected items.
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.6
 */
class Tecnodesign_Collection implements ArrayAccess, Countable, Iterator
{
    protected $_count = 0, $_items=array(), $_hint, $_query, $_queryKey, $_driver, $_current, $_offset=0, $_max=100000000, $_pageStart=0, $_cid;
    protected static $st=array();
    public function __construct($items=null, $hint=null, $query=null, $queryKey=null, $conn=null)
    {
        if(!is_null($hint)) {
            $this->_hint = $hint;
        }
        // enable caching by assigning an arrayObject to the collection holder
        if(!is_null($items)) {
            if (!is_array($items)) {
                $items = array($items);
            }
            foreach ($items as $name=>$item) {
                $this->$name=$item;
                $this->_items[]=$name;
            }
            $this->_count = count($items);
        }
        $this->_current = 0;
        if(!is_null($query)) {
            if(!$this->setQuery($query, $conn, $queryKey)) {
                return false;
            }
        }
    }
    
    
    public function setClass($hint)
    {
        $this->_hint = $hint;
        return $this;
    }


    public function combine($col, $distinct=true)
    {
        if($col) {
            if(!($col instanceof Tecnodesign_Collection)) {
                $col = new Tecnodesign_Collection($col);
            } else if($this->_query && ($q=$col->getQuery())) {
                if(preg_match('/\s+order\s+by[^\']+$/i', $this->_query, $m)) {
                    $sql = substr($this->_query, 0, strlen($this->_query) - strlen($m[0]));
                    $order = $m[0];
                    unset($m);
                } else {
                    $sql = $this->_query;
                    $order = '';
                }
                if(preg_match('/\s+order\s+by[^\']+$/i', $q, $m)) {
                    $sql .= ' union '.substr($q, 0, strlen($q) - strlen($m[0])).$order;
                } else {
                    $sql .= ' union '.$q.$order;
                }

                // @todo: apply $distinct if not found

                $this->setQuery($sql);
                unset($col, $sql, $order, $q);
            } else {
                // merge both collections
            }
        }
    }


    public function setQueryKey($key)
    {
        $this->_queryKey = $key;
    }

    /**
     * Adds a SQL query to the collection
     *
     * By doing this, all items from this collection will be dynamically retrieved from the query
     */
    public function setQuery($sql, $conn=null, $key=null)
    {
        if(!is_null($key)) {
            $this->setQueryKey($key);
        }

        $count = null;
        if(is_null($sql) || $sql===false) {
            if(is_object($this->_query)) {
                $this->_items = $this->getItem(0, $this->_max);
                $this->_query = null;
                $this->_count = count($this->_items);
            }
        } else if(is_object($sql) && method_exists($sql, 'count')) {
            $this->_query = $sql;
            $count = true;
        } else {
            if(!$this->_query) {
                if($this->_hint) {
                    $cn = $this->_hint;
                    $this->_query = $cn::queryHandler();
                    unset($cn);
                } else {
                    $this->_query = tdz::connect();
                }
            }

            if(is_string($sql)) {
                $this->_query->setQuery($sql);
            } else {
                $this->filter($sql);
            }

            $count = true;
        }

        if($conn && $this->_query) {
            if($this->_hint) {
                $cn = $this->_hint;
                $db = $cn::$schema->database;
                unset($cn);
            } else {
                $db = '';
            }
            $this->_query::setConnection($db, $conn);
        }

        if($count) {
            $this->_count = $this->_query->count();
        }

        return $this;
    }

    public function getQueryKey()
    {
        return $this->_queryKey;
    }
    public function getQuery()
    {
        return $this->_query;
    }
    public function getClassName()
    {
        return $this->_hint;
    }
    

    public function rewind()
    {
        $this->_current = 0;
    }

    public function reset($removeMeta=false)
    {
        foreach($this->_items as $i) {
            unset($this->$i);
        }
        $this->_items = array();
        $this->rewind();
        if($removeMeta) {
            $this->_hint = null;
            $this->_count = null;
            $this->_query = null;
            $this->_queryKey = null;
        }
    }

    public function current()
    {
        return $this->getItem($this->_current);
    }

    public function key()
    {
        return (isset($this->_items[$this->_current]))?($this->_items[$this->_current]):($this->_current);
    }

    public function next()
    {
        ++$this->_current;
    }

    public function valid()
    {
        return (isset($this->_items[$this->_current]) || ($this->_query && $this->_current < $this->_count));
    }

    public function asSpreadsheet($fname=null, $scope='review')
    {
        /**
         * Create a new Sheet
         */
        $ext = 'xlsx';
        $cn = $this->_hint;
        if(!$fname) {
            if($cn) {
                $fname = tdz::uncamelize($cn).'.'.$ext;
            } else {
                $fname = 'collection.'.$ext;
            }
        } else {
            if(preg_match('#(.+)\.([a-z]+)$#i', $fname, $m)) {
                $ext = strtolower($m[2]);
            } else {
                $fname .= '.'.$ext;
            }
        }
        $fc=1000;
        $xls = new Tecnodesign_Excel();
        $xls->setSheetTitle($cn::label());
        $header = false;
        $ln = 0;
        $data=array();
        if($cn && isset($cn::$schema)) {
            /**
             * Add Header Line
             */
            $header = $cn::columns($scope);
            $xls->setStyle('A1:'.$xls->getColLetter(count($header)-1).'1', array(
                'font'=>array('bold'=>true, 'color'=>array('rgb'=>'444444')),
                'alignment'=>array('horizontal'=>'left'),
                'borders'=>array('bottom'=>array('style'=>'medium', 'color'=>array('rgb'=>'444444'))),
            ));
            $xls->setRowHeight(1, 20);
            $c=0;
            foreach ($header as $label=>$fn) {
                if(is_numeric($label)) $label = tdz::t(ucwords(str_replace('_', ' ', $fn)), 'model-'.$cn::$schema['tableName']);
                else if(substr($label,0,1)=='*') $label = tdz::t(substr($label,1), 'model-'.$cn::$schema['tableName']);

                $xls->setColWidth($c, strlen($label));
                $data[$ln][$c] = $label;
                $c++;
                unset($label, $fn);
            }
            unset($c);
            $ln++;
        } else {
            $this->_hint = false;
        }
        
        /**
         * Add Data
         */
        $reg = $this->getItem($this->_current, $fc, false);
        while (isset($reg[0])) {
            $r = array_shift($reg);
            if(!$header) {
                $header = array_keys($r);
            }
            foreach ($header as $fn) {
                if(!is_object($r) || is_int($fn)) {
                    $data[$ln][] = $r[$fn];
                } else {
                    if($p=strrpos($fn, ' ')) {
                        $fn = substr($fn, $p+1);
                        unset($p);
                    }
                    $m = tdz::camelize(ucfirst($fn));
                    $dm = 'preview'.$m;
                    $m = 'get'.$m;
                    $display=false;
                    if(method_exists($r, $dm)) {
                        $value = $r->$dm();
                        $display=true;
                    } else if(method_exists($r, $m)) {
                        $value = $r->$m();
                    } else {
                        $value = $r->$fn;
                        if($value===false) {
                            $value='';
                        }
                    }
                    $fd=false;
                    if(!$display && isset($cn::$schema['columns'][$fn])) {
                        $fd=$cn::$schema['columns'][$fn];
                        if($fd['type']=='datetime' || $fd['type']=='date') {
                            if($value && ($t=strtotime($value))) {
                                $df = ($fd['type']=='datetime')?(tdz::$dateFormat.' '.tdz::$timeFormat):(tdz::$dateFormat);
                                $value = date($df, $t);
                            }
                            unset($t, $df);
                        } else {
                            if(isset($cn::$schema['form'][$fn]['choices'])) {
                                $ffd = $cn::$schema['form'][$fn];
                                $co=false;
                                if(is_array($ffd['choices'])) {
                                    $co = $ffd['choices'];
                                } else {
                                    // make Tecnodesign_Form_Field::getChoices available
                                }
                                if(is_array($co) && isset($co[$value])) {
                                    $value = $co[$value];
                                }
                                unset($ffd, $co);
                            }
                        }
                        unset($fd);
                    }
                    $data[$ln][] = $value;
                }
            }
            $ln++;
            if(!isset($reg[0]) && $this->_current < $this->_count) {
                $reg = $this->getItem($this->_current, $fc, false);
                $xls->addData($data);
                unset($data);
                $data = array();
            }
        }
        if($data) {
            $xls->addData($data);
        }
        return $xls->render($ext, $fname);
    }

    public function paginate($hpp=20, $renderMethod=null, $renderArgs=array(), $pagesOnTop=false, $pagesOnBottom=true)
    {
        $page = 1;
        $pages = ceil($this->_count/$hpp);
        if(($get=Tecnodesign_App::request('get', tdz::$pageParam)) && is_numeric($get)) {
            $page = (int)$get;
            if($page<1) $page=1;
            else if($page>$pages)$page=$pages;
            $this->_current = ($page -1)*$hpp;
        } else if($this->_current > 0 && $this->_query && $this->_count) {
            $this->_current = $this->_current % $this->_count;
        }
        $pn = tdz::pages(array('page'=>$page, 'last-page'=>$pages), tdz::requestUri());
        if(!is_array($renderMethod)) $renderMethod = array($this->_hint, $renderMethod);
        if($renderMethod && $this->_hint && method_exists($renderMethod[0], $renderMethod[1])) {
            $i=0;
            $s = '';
            if($pagesOnTop) {
                $s .= $pn;
            }
            $this->_pageStart = $this->_current;
            $items = $this->getItem($this->_current, $hpp, false, false);
            if(!isset($renderArgs[0]) && count($renderArgs)>0) {
                $renderArgs = array_values($renderArgs);
            }

            if($arga = is_array($renderArgs[0])) {
                $renderArgs[0]['start']=$this->_current;
                $renderArgs[0]['hits']=count($items);
            }

            $hint = true;
            $ap=0;
            if($renderMethod[0]!=$this->_hint) {
                $hint = false;
                $ap=1;
                array_unshift($renderArgs, null);
            }

            foreach($items as $o) {
                if($hint) {
                    $renderMethod[0] = $o;
                } else {
                    $renderArgs[0] = $o;
                }
                if($arga) $renderArgs[$ap]['position']=$this->_current;
                $s .= tdz::call($renderMethod, $renderArgs);
                if($hint) {
                    unset($renderMethod[0]);
                }
                $this->_current++;
                unset($o);
            }
            if($pagesOnBottom) {
                $s .= $pn;
            }
        } else {
            $s = $pn;
        }


        return $s;
    }

    /**
     * Adds new items to the collection. If the item is a collection, then it 
     * merges both collections. 
     */
    public function add()
    {
        $a = func_get_args();
        foreach($a as $item) {
            if(is_object($item) && ($item instanceof Tecnodesign_Collection)) {
                foreach ($item as $subitem) {
                    $this->add($subitem);
                }
            } else {
                $this[]=$item;
            }
        }
        return $this;
    }
    
    public function getPosition()
    {
        return $this->_current;
    }

    public function getPageStart()
    {
        return $this->_pageStart;
    }
    
    public function getFirst()
    {
        $items = $this->_items;
        $name = array_shift($items);
        return $this->$name;
    }

    public function getItems($offset=0, $limit=10000)
    {
        return $this->getItem($offset, $limit, false, false);
    }
    
    public function getNamedItem($p)
    {
        $ret = null;
        if(isset($this->$p)) {
            $ret = $this->$p;
        } else if($this->_query) {
            if($this->_queryKey) {
                $k = $this->_queryKey;
            } else if($this->_hint) {
                $cn = $this->_hint;
                $k = $cn::pk();
            } else {
                \tdz::log('[WARNING] Cannot fetch named item from unhinted query or query without a key!');
                return null;
            }

            if(is_array($k) && count($k)==1) $k = implode('', $k);
            $p0 = $p;
            if(is_array($k)) {
                if(!is_array($p)) {
                    if(!isset($cn)) $cn = ($this->_hint) ?$this->_hint :Tecnodesign_Model;
                    $p = explode($cn::$keySeparator, $p, count($k));
                }
                $q = [];
                foreach($k as $fn) {
                    $q[$fn] = array_shift($p);
                    unset($fn);
                    if(!$p) break;
                }
            } else {
                $q = [$k=>$p];
            }

            $this->_query->filter(['where'=>$q]);
            $ret = $this->getItem(0, 1);
            $this->_current = $p0;
        }
        return $ret;
    }
    
    public function getItem($offset=null, $limit=1, $asCollection=false, $single=true)
    {
        $offset0 = $offset;
        if(is_null($offset)) $offset = $this->_current;
        $p = (int)$offset;
        $ret = null;
        $this->_current = $p;//+$limit;
        if(tdz::$perfmon) tdz::$perfmon = microtime(true);
        if(!$this->_query && isset($this->_items[$p])) {
            if($limit>1) {
                $ret = array();
                $climit = $p+$limit;
                if($this->_count < $climit) {
                    $climit = $this->_count;
                }
                for($i=$p;$i < $climit;$i++) {
                    $item = $this->_items[$i];
                    $ret[]=$this->$item;
                }
            } else {
                $ret = $this->{$this->_items[$p]};
            }
        } else if($p < $this->_count && $p >= 0) {
            $this->_offset = (int) $p;
            $this->_max    = (int) $limit;
            if($this->_hint) {
                $ret = $this->_query->fetch($this->_offset, $this->_max);
            } else {
                $ret = $this->_query->fetchArray($this->_offset, $this->_max);
            }
            if($single && is_int($offset0) && $limit===1 && $ret) $ret = array_shift($ret);
            else if($ret && $this->_queryKey && $limit>1) {
                $r = [];
                $key = $this->_queryKey;
                foreach($ret as $i=>$o) {
                    $r[$o->$key] = $o;
                    unset($ret[$i], $i, $o);
                }
                $ret = $r;
                unset($r);
            } else if(is_null($ret) || $ret===false) {
                $ret = [];
            }
        }
        if(tdz::$perfmon>0) tdz::log(__METHOD__.': '.tdz::formatNumber(microtime(true)-tdz::$perfmon).'s '.tdz::formatBytes(memory_get_peak_usage())." mem: {$limit}\n  query: {$sql}");
        if ($limit > 1 && $asCollection) {
            if($ret) {
                if(!is_array($this->_items) || !$this->_items) $this->_items = $ret;
                else $this->_items = array_merge($ret, $this->_items);
            }
            return $this;
        } else if($ret instanceof Tecnodesign_Model && Tecnodesign_Model::$keepCollection) {
            $ret->setCollection($this); // se for instanceof Tecnodesign_Model
        } else if(!$ret && $limit!=1 && !is_array($ret)) {
            $ret = [];
        }

        return $ret;
    }
    
    public function getLast()
    {
        $items = $this->_items;
        $name = array_pop($items);
        return $this->$name;
    }
    
    /**
     * Items counter
     *  
     * @return int 
     */
    public function count()
    {
        return $this->_count;
    }

    /**
     * Magic terminator. Returns the page contents, ready for output.
     * 
     * @return string page output
     */
    function __toString()
    {
        $s = array();
        foreach($this->_items as $name) {
            $s[] = $this->$name;
        }
        return implode(', ', $s);
    }

    /**
     * Magic setter. Searches for a set$Name method, and stores the value in $_vars
     * for later use.
     *
     * @param string $name  parameter name, should start with lowercase
     * @param mixed  $value value to be set
     *
     * @return void
     */
    public function  __set($name, $value)
    {
        if ($this->_hint && !is_object($value)) {
            $cn = $this->_hint;
            $value = new $cn($value);
        }
        $name = (string) $name;
        if($name==='') {
            if($this->_count==0) {
                $name = '0';
            } else {
                $name = (string) max(array_keys($this->_items))+1;
            }
        }
        if(!in_array($name, $this->_items)) {
            $this->_items[]=$name;
            $this->_count++;
        }
        $this->$name = $value;
        /*
        if($name=='' && $name!==0){
            $this->_items[]=$name;
            $keys = array_keys(array_slice($this->_items, -1, 1, true));
            $name = array_shift($keys);
            array_pop($this->_items);
            $this->$name = $value;
        } else {
            $this->$name=$value;
            if(!$this->_query && !in_array($name, $this->_items)) {
                $this->_items[]=$name;
                $this->_count++;
            }
        }
        */
        return $this;
    }

    public static function __set_state($a)
    {
        $items      = (isset($a['_items']))?($a['_items']):(null);
        $hint       = (isset($a['_hint']))?($a['_hint']):(null);
        $query      = (isset($a['_query']))?($a['_query']):(null);
        $queryKey   = (isset($a['_queryKey']))?($a['_queryKey']):(null);
        return new static($items, $hint, $query, $queryKey);
    }

    /**
     * Magic functions. Pass to referred object, if any.
     *
     * @param string $name parameter name, should start with lowercase
     * 
     * @return mixed the stored value, or method results
     */
    public function  __call($name, $arguments)
    {
        $ret = new Tecnodesign_Collection;
        if($this->_count) {
            foreach($this as $i=>$o) {
                if(is_object($o)) {
                    $add = tdz::objectCall($o, $name, $arguments);
                    if(!is_null($add)) {
                        $ret->add($add);
                    }
                }
            }
        }
        return $ret;
    }
 
    /**
     * Magic getter. Searches for a get$Name method, or gets the stored value in
     * $_vars.
     *
     * @param string $name parameter name, should start with lowercase
     * 
     * @return mixed the stored value, or method results
     */
    public function  __get($name)
    {
        $ret = null;
        if (isset($this->$name)) {
            $ret = $this->$name;
        } else if($this->_query && $this->_queryKey) {
            return $this->getNamedItem($name);
        } else if($this->_query && is_int($name) && $name >=0 && $name < $this->_count) {
            return $this->getItem($name);
        }
        return $ret;
    }

    /**
     * ArrayAccess abstract method. Searches for stored parameters.
     *
     * @param string $name parameter name, should start with lowercase
     *
     * @return bool true if the parameter exists, or false otherwise
     */
    public function offsetExists($name)
    {
        return (in_array($name, $this->_items) || ($this->_query && $this->_queryKey && $this->getNamedItem($name)) || ($this->_query && is_numeric($name) && (int)$name >=0 && (int)$name < $this->_count));
    }
    
    /**
     * ArrayAccess abstract method. Gets stored parameters.
     *
     * @param string $name parameter name, should start with lowercase
     *
     * @return mixed the stored value, or method results
     * @see __get()
     */
    public function offsetGet($name)
    {
        return $this->__get($name);
    }
    
    /**
     * ArrayAccess abstract method. Sets parameters to the PDF.
     *
     * @param string $name  parameter name, should start with lowercase
     * @param mixed  $value value to be set
     * 
     * @return void
     * @see __set()
     */
    public function offsetSet($name, $value)
    {
        return $this->__set($name, $value);
    }
    
    /**
     * ArrayAccess abstract method. Unsets parameters to the PDF. Not yet implemented
     * to the PDF classes — only unsets values stored in $_vars
     *
     * @param string $name parameter name, should start with lowercase
     * 
     * @return void
     */
    public function offsetUnset($name)
    {
        $key = array_search($name, $this->_items);
        if($key!==false) {
            unset($this->$name);
            unset($this->_items[$key]);
            $this->_items = array_values($this->_items);
            $this->_count--;
        }
    }

}
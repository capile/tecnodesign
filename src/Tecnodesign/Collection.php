<?php
/**
 * Tecnodesign Collection
 *
 * A collection is an extended array which extends its unknown properties to its
 * collected items.
 *
 * PHP version 5.2
 *
 * @category  Collection
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @version   SVN: $Id: Collection.php 1293 2013-11-04 13:23:44Z capile $
 * @link      http://tecnodz.com/
 */

/**
 * Tecnodesign Collection
 *
 * A collection is an extended array which extends its unknown properties to its
 * collected items.
 *
 * @category  Collection
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      http://tecnodz.com/
 */
class Tecnodesign_Collection implements ArrayAccess, Countable, Iterator
{
    protected $_count = 0, $_items=array(), $_hint, $_query, $_queryKey, $_driver, $_current, $_offset=0, $_max=100000000, $_pageStart=0, $_statement=null, $_keyStatement=null, $_cid;
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
            if(!$this->setQuery($query, $conn)) {
                return false;
            }
        }
        if($queryKey) {
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

    /**
     * Adds a SQL query to the collection
     *
     * By doing this, all items from this collection will be dynamically retrieved from the query
     */
    public function setQuery($sql, $conn=null, $key=null)
    {
        if(!$sql) {
            if($this->_statement) {
                // must fetch the objects and remove the query
                $toAdd = $this->getItem(0, 10000);
                $this->_queryStatement(false);
                $this->_count=0;
                if($toAdd) {
                    foreach($toAdd as $k=>$v) {
                        $this->$k=$v;
                    }
                }
            }
            return $this;
        }
        
        if(is_object($sql)) {
            $this->_query = $sql;
            $this->_count = $sql->count();
            return $this;
        }


        if(!$conn) {
            $conn = $this->_conn();
            /*
            // add parameters
            if($this->_hint) {
                $cn = $this->_hint;
                if(isset($cn::$schema) && isset($cn::$schema['database'])) {
                    $conn = tdz::connect($cn::$schema['database'], null, true);
                    $cid = $cn::$schema['database'];
                }
            }
            if(!$conn) {
                $conn = tdz::connect();
                $cid = tdz::$connection;
            }
            */
        }
        $this->_driver = @$conn->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sqlc=false;
        if($this->_driver=='dblib') {
            if(!$this->_cid) {
                $this->_cid = tdz::$connection;
            }
            $dk = 'driverInfo/'.$this->_cid;
            $dinfo = Tecnodesign_Cache::get($dk);
            if(!$dinfo) {
                try{
                    $dos = $conn->query('select @@version');
                    if($dos) {
                        $do = $dos->fetchColumn();
                        $dos->closeCursor();
                        if(preg_match('/^Microsoft SQL Server\s+([0-9]+)[^\-]+\-\s+([0-9]+)/', $do, $m)) {
                            $dinfo=array('version'=>$m[2], 'year'=>$m[1], 'description'=>$do);
                        }
                        Tecnodesign_Cache::set($dk, $dinfo);
                    }
                } catch(Exception $e) {
                    tdz::log(__METHOD__.': Failed to get driver options', $e->getMessage());
                }
            }
            if($dinfo && isset($dinfo['version']) && $dinfo['version']>=11) $this->_driver ='mssql2012';
        }
        if($key) {
            $this->_queryKey = $key;
            $key = preg_replace('/[^a-z0-9\_]+/i', '', $key);
            if($this->_driver=='dblib') {
                //MSSQL if there's ORDER BY, we must also use TOP, or remove the ORDER BY to count items:
                // The ORDER BY clause is invalid in views, inline functions, derived tables, and subqueries, unless TOP is also specified.
                if(preg_match('/^\s*select\s*(top)?(.*\sorder\s+by[^\']+)$/i', $sql, $m) && $m[1]=='') {
                    $sql = 'select top '.$this->_max.' '.$m[2]; 
                }
            }
            $sql = "select q.* from ($sql) as q where q.{$key}=:key";
        } else {
            $this->_query = $sql;
            // add limiting params for the query
            // this restricts the Tecnodesign_Collection for MySQL usage only
            // for MSSQL this will need to be worked with cursors
            if($this->_driver=='dblib') {
                //MSSQL if there's ORDER BY, we must also use TOP, or remove the ORDER BY to count items:
                // The ORDER BY clause is invalid in views, inline functions, derived tables, and subqueries, unless TOP is also specified.
                if(preg_match('/^\s*select\s*(distinct\s+)?(top\s+[0-9]+\s*)?(.*\sorder\s+by[^\']+)$/i', $sql, $m) && $m[2]=='') {
                    $sql = 'select '.$m[1].'top '.$this->_max.' '.preg_replace('/\s+order by [^\']+$/i', '', $m[3]); 
                }
                $sql = "select count(*) as count from ({$sql}) as q";
                
            } else if($this->_driver=='pgsql') {
                $sqlc="select count(*) as count from ({$sql}) as q";
                $sql .= ' limit :max offset :offset';
            } else if($this->_driver=='mssql2012') {
                $sqlc="select count(*) as count from (".preg_replace('/\s+order by [^\']+$/i', '', $sql).") as q";
                $sql .= ' OFFSET (:offset) ROWS FETCH NEXT :max ROWS ONLY';
            } else {
                $sql .= ' limit :offset,:max';
            }
            
        }
        try {
            if($key) {
                $this->_queryStatement($conn->prepare($sql), true);
            } else {
                // setting the pdo object with the actual query with limit params
                // @NOTE: some queries could benefit from stripping the ORDER BY clause from the query (since this involves temporary tables for filesorting)
                //        however, this would implicitly force the query to be updated and executed again
                // @TODO: compare both performance and make the best choice
                if(tdz::$perfmon) tdz::$perfmon = microtime(true);
                if($this->_driver=='dblib') {
                    // since it's better to store the connection instead of the statement in MSSQL queries, we do it here
                    $this->_queryStatement($conn);
                    $query = $conn->query($sql);
                    $this->_count=($query)?((int) ($query->fetchColumn())):(0);
                    if($query) $query->closeCursor();
                    unset($query);
                    if(tdz::$perfmon>0) tdz::log(__METHOD__.': '.tdz::formatNumber(microtime(true)-tdz::$perfmon).'s '.tdz::formatBytes(memory_get_peak_usage())." mem: {$this->_count}\n  query: {$sql}");
                } else if($this->_driver=='pgsql' || $this->_driver=='mssql2012') { // cannot find the number of rows automatically
                    $this->_queryStatement($conn->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL)));
                    $query = $conn->query($sqlc);
                    if(!$query) {
                        $err = $conn->errorInfo();
                        throw new Tecnodesign_Exception("Error at query for Collection: {$err[2]}");
                    }
                    $this->_count=(int) $query->fetchColumn();
                    $query->closeCursor();
                    unset($query);
                    if(tdz::$perfmon>0) tdz::log(__METHOD__.': '.tdz::formatNumber(microtime(true)-tdz::$perfmon).'s '.tdz::formatBytes(memory_get_peak_usage())." mem: {$this->_count}\n  query: {$sql}");
                } else {
                    $query = $this->_queryStatement($conn->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL)));
                    $query->bindParam(':offset', $this->_offset, PDO::PARAM_INT);
                    $query->bindParam(':max', $this->_max, PDO::PARAM_INT);
                    $query->execute();
                    if(!$query) {
                        $err = $conn->errorInfo();
                        throw new Tecnodesign_Exception("Error at query for Collection: {$err[2]}");
                    }
                    $this->_count=$query->rowCount();
                    if(tdz::$perfmon>0) tdz::log(__METHOD__.': '.tdz::formatNumber(microtime(true)-tdz::$perfmon).'s '.tdz::formatBytes(memory_get_peak_usage())." mem: {$this->_count}\n  query: {$sql}");
                }
            }
        } catch(Exception $e) {
            tdz::log(__METHOD__.': '.$e->getMessage()."\nSQL: {$sql}");
            return false;
        }
        return $this;
    }

    private function _queryStatement($st=null, $key=false)
    {
        $n = ($key)?('_keyStatement'):('_statement');
        if($this->$n) {
            $cid = $this->_cid.':'.$this->$n;
            if(!isset(self::$st[$cid])) {
                if(!(self::$st[$cid]=$this->_conn(true))) {
                    $this->$n = null;
                }
            } else {
                // check if valid
                if((self::$st[$cid] instanceof PDOStatement) && self::$st[$cid]->errorCode()) {
                    // need to reset PDOStatement
                    self::$st[$cid] = $this->_conn(true);
                }
            }
        }
        if($st) {
            if(isset($cid) && isset(self::$st[$cid])) unset(self::$st[$cid]);
            $this->$n = microtime(true);
            $cid = $this->_cid.':'.$this->$n;
            self::$st[$cid] = $st;
        } else if($st!=null && $this->$n) {
            if(isset($cid) && isset(self::$st[$cid])) unset(self::$st[$cid]);
            $this->$n = null;
        }

        if($this->$n && isset($cid) && isset(self::$st[$cid])) return self::$st[$cid];
        return false;
    }

    protected function _conn($reset=false)
    {
        // add parameters
        $conn = null;
        if($this->_hint) {
            $cn = $this->_hint;
            if(isset($cn::$schema) && isset($cn::$schema['database'])) {
                $this->_cid = $cn::$schema['database'];
                if($reset) tdz::setConnection($this->_cid, null);
                $conn = tdz::connect($this->_cid, null, true);
            }
        }
        if(!$conn) {
            if($reset) tdz::setConnection('', null);
            $conn = tdz::connect();
            $this->_cid = tdz::$connection;
        }
        return $conn;
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
    

    public function rewind() {
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
            $this->_queryStatement(false);
            $this->_queryStatement(false, true);
        }
    }

    public function current() {
        return $this->getItem($this->_current);
    }

    public function key() {
        return (isset($this->_items[$this->_current]))?($this->_items[$this->_current]):($this->_current);
    }

    public function next() {
        ++$this->_current;
    }

    public function valid() {
        return (isset($this->_items[$this->_current]) || ($this->_queryStatement() && $this->_current < $this->_count));
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
        if(isset($_GET['p']) && is_numeric($_GET['p'])) {
            $page = (int)$_GET['p'];
            if($page<1) $page=1;
            else if($page>$pages)$page=$pages;
            $this->_current = ($page -1)*$hpp;
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
            $items = $this->getItem($this->_current, $hpp);
            if(!isset($renderArgs[0]) && count($renderArgs)>0) {
                $renderArgs = array_values($renderArgs);
            }
            $renderArgs[0]['start']=$this->_current;
            $renderArgs[0]['hits']=count($items);

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
                $renderArgs[$ap]['position']=$this->_current;
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
        return $this->getItem($offset, $limit, false);
    }
    
    public function getNamedItem($p)
    {
        $ret = null;
        if(isset($this->$p)) {
            $ret = $this->$p;
        } else if($st = $this->_queryStatement(null, true)) {
            $ft = PDO::FETCH_ASSOC;
            if($this->_hint) {
                $st->setFetchMode(PDO::FETCH_CLASS, $this->_hint);
                $ft = PDO::FETCH_CLASS;
            }
            $st->bindParam(':key', $p);
            $st->execute();
            $ret = $st->fetch($ft);
            $this->$p = $ret;
            unset($st);
        }
        return $ret;
    }
    
    public function getItem($offset=null, $limit=1, $asCollection=false)
    {
        if(is_null($offset)) $offset = $this->_current;
        $p = (int)$offset;
        $ret = null;
        $this->_current = $p;//+$limit;
        if(tdz::$perfmon) tdz::$perfmon = microtime(true);
        if(isset($this->_items[$p])) {
            if($limit>1) {
                $ret = array();
                $climit = $p+$limit;
                if($this->_count < $climit) {
                    $climit = $this->_count;
                }
                for($i=$p;$i < $climit;$i++) {
                    $ret[]=$this->{$this->_items[$i]};
                }
            } else {
                $ret = $this->{$this->_items[$p]};
            }
        } else if($p < $this->_count && $p >= 0) {
            if(is_object($this->_query)) {
                $this->_offset = (int) $p;
                $this->_max    = (int) $limit;
                if($this->_hint) {
                    $ret = $this->_query->fetch($this->_offset, $this->_max);
                } else {
                    $ret = $this->_query->fetchArray($this->_offset, $this->_max);
                }
            } else {
                $q=$this->_queryStatement();
                if($this->_driver!='dblib' && get_class($q)=='PDO') { // lost original connection
                    $sql = $this->_query;
                    if($this->_driver=='pgsql') {
                        $sql .= ' limit :max offset :offset';
                    } else if($this->_driver=='mssql2012') {
                        $sql .= ' OFFSET (:offset) ROWS FETCH NEXT :max ROWS ONLY';
                    } else {
                        $sql .= ' limit :offset,:max';
                    }
                    $q = $q->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL));
                    unset($sql);
                }

                try {
                    $ft = PDO::FETCH_ASSOC;
                    $this->_offset = (int) $p;
                    $this->_max    = (int) $limit;

                    if(get_class($q)=='PDO') { // the connection is stored for dblib (MSSQL)
                        $scroll = uniqid('tdz');
                        $start = $this->_offset +1;
                        $q->query('set rowcount '.($p+$limit));
                        $q->query('set nocount on');
                        $q->query("DECLARE {$scroll} SCROLL CURSOR FOR {$this->_query}");
                        $q->query("OPEN {$scroll}");
                        $st = $q->query("FETCH ABSOLUTE {$start} FROM {$scroll}");
                        //$this->reset();
                        if($this->_hint) {
                            $ret = $st->fetchObject($this->_hint);
                        } else {
                            $ret = $st->fetch($ft);
                        }
                        unset($st);
                        if($ret && $limit > 1) {
                            if($asCollection) {
                                $this->{$this->_offset} = $ret;
                                $this->_count--;
                                //$this->_items[]=$this->_offset;
                                unset($ret);
                            } else {
                                $ret=array($ret);
                            }
                            $l=$limit -1;
                            while($l>0) {
                                $st = $q->query("FETCH NEXT FROM {$scroll}");
                                if($this->_hint) {
                                    $r = $st->fetchObject($this->_hint);
                                } else {
                                    $r = $st->fetch($ft);
                                }
                                unset($st);
                                if($r) {
                                    if($asCollection) {
                                        $this->{$start} = $r;
                                        $this->_count--;
                                        //$this->_items[]=$start;
                                        $start++;
                                    } else {
                                        $ret[]=$r;
                                    }
                                } else {
                                    break;
                                }
                                unset($r);
                                $l--;
                            }
                        }
                        $q->query("CLOSE {$scroll}");
                        $q->query("DEALLOCATE {$scroll}");
                        $q->query('set rowcount 0');
                        $q->query('set nocount off');
                        unset($q);
                    } else {
                        if($this->_hint) {
                            $q->setFetchMode(PDO::FETCH_CLASS, $this->_hint);
                            $ft = PDO::FETCH_CLASS;
                        }
                        $q->bindParam(':offset', $this->_offset, PDO::PARAM_INT);
                        $q->bindParam(':max', $this->_max, PDO::PARAM_INT);
                        $q->execute();
                        if($limit > 1) {
                            if($this->_hint) {
                                $ret = $q->fetchAll($ft, $this->_hint);
                            } else {
                                $ret = $q->fetchAll($ft);
                            }
                            if ($asCollection) {
                                //$this->reset();
                                foreach($ret as $k=>$v) {
                                    $this->__set($k, $v);
                                    $this->_count--;
                                    //$this->_items[] = $k;
                                }
                            }
                        } else {
                            $ret = $q->fetch($ft);
                        }
                        $q->closeCursor();
                        unset($q);
                    }
                } catch(Exception $e) {
                    tdz::log(__METHOD__.': '.$e->getMessage()."\n  ".$e->getFile().':'.$e->getLine());
                }
            }
        }
        if(tdz::$perfmon>0) tdz::log(__METHOD__.': '.tdz::formatNumber(microtime(true)-tdz::$perfmon).'s '.tdz::formatBytes(memory_get_peak_usage())." mem: {$limit}\n  query: {$sql}");
        if ($limit > 1 && $asCollection) {
            return $this;
        } else if($ret instanceof Tecnodesign_Model && Tecnodesign_Model::$keepCollection) {
            $ret->setCollection($this); // se for instanceof Tecnodesign_Model
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
        } else if($this->_statement && $this->_keyStatement) {
            return $this->getNamedItem($name);
        /*} else if(!is_int($name)) {
            $ret = new Tecnodesign_Collection;
            if($this->_count) {
                foreach($this as $i=>$o) {
                    if(is_object($o)) {
                        $add = $o->$name;
                        if(!is_null($add)) {
                            $ret->add($o->$name);
                        }
                    }
                }
            }*/
        } else if($this->_statement && is_numeric($name) && (int)$name >=0 && (int)$name < $this->_count) {
            return $this->getItem((int)$name);
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
        return (in_array($name, $this->_items) || ($this->_statement && $this->_keyStatement && $this->getNamedItem($name)) || ($this->_statement && is_numeric($name) && (int)$name >=0 && (int)$name < $this->_count));
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
        if($this->_statement && $this->_keyStatement) {
            return $this->getNamedItem($name);
        } else if($this->_statement && is_numeric($name) && (int)$name >=0 && (int)$name < $this->_count) {
            return $this->getItem($name);
        }
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
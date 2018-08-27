<?php
/**
 * Tecnodesign Excel
 *
 * Create a excel sheet 
 *
 * PHP version 5.2
 *
 * @category  Excel
 * @package   Tecnodesign
 * @author    Mirela Lisboa, Tecnodesign <ti@tecnodz.com>
 * @copyright 2012 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @version   SVN: $Id: Excel.php 1265 2013-07-21 17:21:51Z capile $
 * @link      http://tecnodz.com/
 */

/**
 * Tecnodesign Excel
 *
 * Create a excel sheet
 *
 * @category  Excel
 * @package   Tecnodesign
 * @author    Mirela Lisboa, Tecnodesign <ti@tecnodz.com>
 * @copyright 2012 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      http://tecnodz.com/
 */

if(!class_exists('PHPExcel', true)) Tecnodesign_App_Install::dep('PHPExcel');

class Tecnodesign_Excel 
{
    const TYPE_STRING2      = 'str';
    const TYPE_STRING       = 's';
    const TYPE_IMAGE        = 'i';
    const TYPE_FORMULA      = 'f';
    const TYPE_NUMERIC      = 'n';
    const TYPE_BOOL         = 'b';
    const TYPE_NULL         = 'null';
    const TYPE_INLINE       = 'inlineStr';
    const TYPE_ERROR        = 'e';

    private $_cols;
    
    /**
     * Configurations
     */
    private $_template      = '';       //File name with complete path
    private $_style;                    //CSS Stylesheet
    private $_properties    = array();  //excel properties
    private $_firstcol      = 'A';
    private $_firstrow      = '1';
    private $_col           = 'A';
    private $_row           = '1';
    private $_data          = array();
    private $_saveas        = TDZ_VAR;
    
    /**
     * Objects
     */
    private $excel;
    private $sheet;
    private $sheetNum=0;
    protected $x, $y;
    public static $headerFooter=array('OddHeader', 'OddFooter', 'EvenHeader', 'EvenFooter', 'FirstHeader', 'FirstFooter');
    
    public function __construct($config=array())
    {
        //A:CZ 103 columns
        $rcol = range('A','C');
        $az = range('A','Z');
        $this->_cols = $az;
        foreach ($rcol as $v) {            
            foreach($az as $vaz) {                
                $this->_cols[] = $v.$vaz;
            }
        }        
        
        $this->setAllConfig($config);
        $this->init();        
    }
    
    public function language($lang)
    {
        if(class_exists('Locale')) Locale::setDefault(substr($lang, 0, 2).strtoupper(substr($lang, 2)));
        return PHPExcel_Settings::setLocale($lang);
    }


    /**
     * Set configurations
     * @param array $config 
     */
    public function setAllConfig($config)
    {
        if(!is_array($config))
            throw new Exception('Unable to use non-array configuration');
        
        foreach ($config as $k => $v) {
            $this->setConfig($k,$v);
        }
    }
    
    public function setConfig($name, $value) 
    {
        $cfg = '_'.$name;        
        $this->$cfg = $value;
    }
    
    /**
     * Initializes internal objects 
     */
    private function init()
    {
        $sheettype = '';
        /**
         * Verify is template exists and file format is correct
         */
        if ($this->_template != '' && !file_exists($this->_template)) {
            throw new Exception('Unable to load template file: '.$this->_template);
            $this->_template = '';            
        }
        
        if ($this->_template != '') {
            /**
             * xlsx = Excel2007
             * xls = Excel5
             * ods = OOCalc 
             */            
            $sheettype = PHPExcel_IOFactory::identify($this->_template);            
        }        
        
        /**
         * Create a Excel objects
         */
        if ($sheettype != '') {
            $reader = PHPExcel_IOFactory::createReader($sheettype);		
            $this->excel = $reader->load($this->_template);            
            
        } else {
            $this->excel = new PHPExcel();
            $this->excel->setActiveSheetIndex(0);
        }
        
        if (count($this->_properties) > 0) {
            $this->setProperties();
        }
       
        $this->sheet = $this->excel->getActiveSheet();        
        
        //Posiciona o cursor na primeira linha livre
        if ($sheettype != '') {
            $this->setConfig('firstrow',$this->sheet->getHighestRow()+1);
        }
        //require_once 'PHPExcel/Cell/AdvancedValueBinder.php';
        PHPExcel_Cell::setValueBinder( new PHPExcel_Cell_AdvancedValueBinder() );
    }

    public function page($p=array())
    {
        $ms = array('setup'=>'getPageSetup', 'margin'=>'getPageMargins');
        foreach($p as $k=>$v){
            list($m1, $m2) = explode('-', $k, 2);
            if(isset($ms[$m1])) $m1 = $ms[$m1];
            else $m1 = 'getPage'.ucfirst($m1);

            if(method_exists($this->sheet, $m1)) {
                $m2 = 'set'.tdz::camelize($m2, true);
                $o = $this->sheet->$m1();
                if(method_exists($o, $m2)) {
                    if(!is_array($v)) $v=array($v);
                    tdz::objectCall($o, $m2, $v);
                    //tdz::log(get_class($o).'::'.$m2.'('.var_export($v, true).')');
                }
            }
        }
    }
    
    /**
     * Set the excel property
     * @param string $name
     * @param mixed $value 
     */
    public function setProperty($name, $value) 
    {
        $this->_properties[$name] = $value;
        $this->setProperties();
    }
    
    /**
     * Set Row Height
     * @param string $row     
     * @param integer $tam 
     */
    public function setRowHeight($row, $tam) 
    {
        $this->sheet->getRowDimension($row)->setRowHeight($tam);
    }
    
    /**
     * Set Col Width
     * @param string $col     
     * @param integer $tam 
     */
    public function setColWidth($col, $tam) 
    {
        if (is_int($col)) {
            $col = $this->getColLetter($col);
        }
        $this->sheet->getColumnDimension($col)->setWidth($tam);
    }
    
    /**
     * Set all documents properties  
     */
    private function setProperties() 
    {       
        $class = $this->excel->getProperties();
        foreach($this->_properties as $k => $v) {
            $prop = (str_replace(' ','',(ucwords(str_replace('_',' ',$k)))));
            $method = 'set'.$prop;             
            if (method_exists($class,$method)) {
                $class->$method($v);
            } else {
                $p = '_'.lcfirst($prop);
                $class->setCustomProperty($p, $v);
            }
        }
    }
    
    public function addData($data, $purge=false)
    {
        $this->_data += $data;
        if($purge) {
            $this->compositeExcel();
        }
    }
    
    public function getColLetter($idx) 
    {
        if(isset($this->_cols[$idx])) {
            return $this->_cols[$idx];
        }
    }
    
    private function getColIndex($letter) 
    {
        $ret = array_search($letter, $this->_cols);
        if ($ret === false) {
            $ret = -1; 
        }
        return $ret;                 
    }

    public static function cell($c, $to=null)
    {
        if(is_array($c)) {
            if($c[1]<0)$c[1]=0;
            $c = strtoupper(tdz::letter($c[0])) . ($c[1]+1);
        } else if(!preg_match('/^[a-z]{1,2}[0-9]*(:[a-z]{1,2}[0-9]*)?$/i', $c)) {
            return false;
        }
        if($to) $c .= ':'.self::cell($to);
        return $c;
    }

    public function merge($from, $to=null)
    {
        $this->sheet->mergeCells(self::cell($from, $to));
    }

    public static function image($v, &$s=array(), $headerFooter=false)
    {
        //if($s && ((isset($s['height']) && !$s['height'])||(isset($s['width']) && !$s['width']))) return false;
        $img = ($headerFooter)?(new PHPExcel_Worksheet_HeaderFooterDrawing()):(new PHPExcel_Worksheet_Drawing());
        $img->setName(basename($v));
        //$img->setDescription('PHPExcel logo');
        $img->setPath($v);       // filesystem reference for the image file
        if($s) {
            if(isset($s['height']) && isset($s['width'])) {
                $img->setResizeProportional(false);
            }
            if(isset($s['height'])) {
                $img->setHeight($s['height']);
                unset($s['height']);
            }
            if(isset($s['width'])) {
                if($s['width']==0) $s['width']=0.0001; 
                $img->setWidth($s['width']);
                unset($s['width']);
            }
            if(isset($s['top'])) $img->setOffsetY($s['top']);
            if(isset($s['left'])) $img->setOffsetX($s['left']);
        }
        return $img;
    }

    public function setCell($c, $v=null, $t=null, $s=null, $comment=null)
    {
        $c = self::cell($c);
        if($t) {
            if($t==self::TYPE_IMAGE) {
                $img = self::image($v, $s);
                if($img) {
                    $img->setCoordinates($c);    // pins the top-left corner of the image to cell D24
                    $img->setWorksheet($this->sheet);
                }
                unset($img);
            } else {
                $this->sheet->setCellValueExplicit($c, $v, $t);
            }
        } else {
            if(isset($s['number-format']) && $s['number-format']=='@') {
                $this->sheet->setCellValueExplicit($c,$v);
            } else {
                $this->sheet->setCellValue($c,$v);
                if(substr($v, 0, 8)=='https://' || substr($v, 0, 7)=='http://') {
                    $this->sheet->getHyperlink($c)->setUrl(strip_tags($v));
                }
            }
        }
        if($s) {
            $this->setStyle($c, $s);
        }
        if($comment) {
            $this->sheet->getComment($c)->getText()->createTextRun($comment);
        }
        return $this;
    }
    
    private function compositeExcel() 
    {
        if(count($this->_data)==0) return;
        foreach($this->_data as $r => $c) {
            $this->_col = $this->_firstcol;
            $colidx = $this->getColIndex($this->_col);
            foreach ($c as $ck => $cv) {
                if (is_string($ck) && in_array($ck, $this->_cols)) {
                    $this->_col = $ck;
                    $colidx = $this->getColIndex($this->_col);
                } else {
                    $this->_col = $this->getColLetter($colidx);
                    $colidx++;
                }
                $cell = $this->_col.$this->_row;
                //var_dump($cell,$cv);
                $this->sheet->setCellValue($cell,$cv);               
                unset($ck, $cv, $cell);
            }
            unset($r, $c, $collidx);
            $this->_row++;
        }
        $this->applyStylesheet();
        $this->_data = array();
    }    

    public function addSheet($i=null)
    {
        if(count($this->_data)>0) {
            $this->sheetNum++;
            $this->compositeExcel();
        }
        if(is_null($i)) {
            $i = $this->sheetNum;
        }
        $this->sheet = $this->excel->createSheet($i);
    }
    
    protected $_sheets=array();
    public function sheet($s)
    {
        $n = $this->excel->getSheetCount();
        if(is_int($s) && $n>$s) {
            $this->sheet = $this->excel->setActiveSheetIndex($s);
        } else if(is_int($s)) {
            while($n<=$s) {
                $this->addSheet();
                $n++;
            }
        } else {
            $s = (string) $s;
            if(isset($this->_sheets[$s])) {
                $this->sheet = $this->excel->setActiveSheetIndex($this->_sheets[$s]);
            } else {
                if(in_array($this->excel->getActiveSheetIndex(), $this->_sheets)) {
                    // new sheet
                    $this->addSheet();
                }
                $this->_sheets[$s] = $this->excel->getActiveSheetIndex();
                $this->sheet->setTitle("$s");
            }
        }
        $this->x=0;
        $this->y=0;
        return $this->sheet;
    }


    public function setSheetTitle($s)
    {
        return $this->sheet($s);
    }

    public function addStylesheet($style=null, $add=true)
    {
        if($this->_style && !is_array($this->_style)) $this->_style = tdz::parseCss($this->_style);
        if(!is_null($style)) {
            $style = tdz::parseCss($style);
            if($this->_style) $this->_style = ($add)?(tdz::mergeRecursive($style, $this->_style)):(tdz::mergeRecursive($this->_style, $style));
            else $this->_style = $style;
        }
    }

    public function setStylesheet($style=null)
    {
        return $this->addStylesheet($style, false);
    }

    public function applyStylesheet($style=null)
    {
        if(is_null($style)) $this->addStylesheet($style);
        if($this->_style) {
            foreach($this->_style as $sel=>$rules) {
                $this->setStyle($sel, $rules);
            }
        }
    }

    /**
     * Set style on column
     * @param string $cols -- Sintaxe: 'A1' (unique column) or 'A1:D1' (range of columns)
     * @param array $sytle -- array of the format
     * @internal See ../PHPExcel/docs/PHPExcel developer documentation.pdf, page 25.
     */
    public function setStyle($c, $style)
    {
        if($c=='*' || $c=='html' || $c=='table') $c = $this->sheet->getDefaultStyle();
        else {
            $c = self::cell($c);
            if(!$c) return;
            //if(isset($s['width'])) {
            //    $this->setColWidth(preg_replace('/[0-9].*/', '', $c), $style['width']);
            //}
            //if(isset($s['height'])) {
            //    $this->setRowHeight(preg_replace('/^[a-z]+([0-9]+).*/i', '$1', $c), $style['height']);
            //}
            if(isset($style['width'])) {
                $col = preg_replace('/[0-9].*/', '', $c);
                if($style['width']<=0) {
                    $this->sheet->getColumnDimension($col)->setVisible(false);
                } else {
                    $this->sheet->getColumnDimension($col)->setWidth($style['width']);
                    $wrap = true;
                }
            }
            if(isset($style['height'])) {
                $row = preg_replace('/^[a-z]+([0-9]+).*/i', '$1', $c);
                if($style['height']<=0) {
                    $this->sheet->getRowDimension($row)->setVisible(false);
                } else {
                    $this->sheet->getRowDimension($row)->setRowHeight($style['height']);
                }
            }
            $c = $this->sheet->getStyle($c);
        }
        $s = array();

        if(isset($style['font-family'])) $s['font']['name'] = $style['font-family'];
        if(isset($style['font-size'])) $s['font']['size'] = $style['font-size'];
        if(isset($style['font-weight'])) $s['font']['bold'] = ($style['font-weight']=='bold');
        if(isset($style['color'])) {
            if(is_array($style['color'])) $p=$style['color'];
            else {
                $p = ($style['color'][0]=='#')?(substr($style['color'],1)):($style['color']);
                $p = (strlen($p)==6)?(array('rgb'=>$p)):(array('argb'=>$p));
            }
            $s['font']['color'] = $p;
            unset($p);
        }
        if(isset($style['background-color'])) $style['background']=$style['background-color'];
        if(isset($style['background'])) {
            if(is_array($style['background'])) $s['fill']=$style['background'];
            else {
                $p = ($style['background'][0]=='#')?(substr($style['background'],1)):($style['background']);
                $p = (strlen($p)==6)?(array('rgb'=>$p)):(array('argb'=>$p));
                $s['fill']['color'] = $p;
                unset($p);
            }
            if(!isset($s['fill']['type'])) $s['fill']['type'] = 'solid';
            if(!isset($s['fill']['rotation'])) $s['fill']['rotation'] = 90;
        }
        if(isset($style['padding'])) $s['alignment']['indent'] = $style['padding'];
        if(isset($style['text-align'])) $s['alignment']['horizontal'] = $style['text-align'];
        if(isset($style['vertical-align'])) $s['alignment']['vertical'] = $style['vertical-align'];
        if(isset($style['number-format'])) $s['numberformat']['code'] = $style['number-format'];
        $b=array();
        if(isset($style['border']) && $style['border']) $b['outline'] = $style['border'];
        if(isset($style['border-top']) && $style['border-top']) $b['top'] = $style['border-top'];
        if(isset($style['border-bottom']) && $style['border-bottom']) $b['bottom'] = $style['border-bottom'];
        if(isset($style['border-left']) && $style['border-left']) $b['left'] = $style['border-left'];
        if(isset($style['border-right']) && $style['border-right']) $b['right'] = $style['border-right'];
        foreach($b as $bt=>$bd) {
            if(is_array($bd)) $s['borders'][$bt]=$bd;
            else {
                if(preg_match('/(dashed|dotted)/', $bd, $m)) $s['borders'][$bt]['style']=$m[1];
                else if(!preg_match('/[0-9]+/', $bd, $m) || $m[0]<=1) $s['borders'][$bt]['style']='thin';
                else if($m[0]<=3) $s['borders'][$bt]['style']='medium';
                else $s['borders'][$bt]['style']='thick';
                if(preg_match('/#([0-9a-f]{6,8})/i', $bd, $m)) {
                    if(strlen($m[1])==6) $s['borders'][$bt]['color']=array('rgb'=>$m[1]);
                    else $s['borders'][$bt]['color']=array('argb'=>$m[1]);
                } else $s['borders'][$bt]['color']=array('rgb'=>'000000');
            }
        }
        if(isset($style['white-space']) || isset($wrap)) {
            if(isset($style['white-space'])) $wrap = ($style['white-space']=='nowrap' || $style['white-space']=='pre');
            $s['alignment']['wrap'] = $wrap;
        }

        if(count($s)>0)
          $c->applyFromArray($s);


        unset($c, $s);
    }

    public function style($x, $y, $c, $cn=null)
    {
        $style = (isset($c['style']))?($this->val($c['style'])):(array());
        $on = '::nth-child('.((int)$x).')';
        if(isset($this->_style[$on])) $style += $this->_style[$on];
        if(isset($c['use'])) {
            if($x!==false && $y!==false) {
                $ox = ($x%2)?($c['use'].':odd'):($c['use'].':even');
                $oy = ($y%2)?(':odd '.$c['use']):(':even '.$c['use']);
                $o = ($y%2)?(':odd '.$ox):(':even '.$ox);
                if(isset($this->_style[$oy.$on])) $style += $this->_style[$oy.$on];
                if(isset($this->_style[$c['use'].$on])) $style += $this->_style[$c['use'].$on];
                if(isset($this->_style[$o])) $style += $this->_style[$o];
                if(isset($this->_style[$ox])) $style += $this->_style[$ox];
                if(isset($this->_style[$oy])) $style += $this->_style[$oy];
                //tdz::log(array($on, $oy.$on, $c['use'].$on, $o, $ox, $oy, $c['use']));
            }
            if(isset($this->_style[$c['use']]))$style += $this->_style[$c['use']];
        }
        if($cn && !is_numeric($cn) && isset($this->_style[$cn])) {
            $style += $this->_style[$cn];
        }
        return $style;
    }



    public function setHeader($s, $w='odd', $p='L', $img=null, $imgStyle=array())
    {
        $w = ucfirst($w).'Header';
        return $this->setHeaderFooter($s, $m, $p, $img, $imgStyle);
    }

    public function setFooter($s, $w='odd', $p='L', $img=null, $imgStyle=array())
    {
        $w = ucfirst($w).'Footer';
        return $this->setHeaderFooter($s, $m, $p, $img, $imgStyle);
    }

    public function setHeaderFooter($s, $w='oddHeader', $p='L', $img=null, $imgStyle=array())
    {
        $w = ucfirst($w);
        if(!in_array($w, self::$headerFooter)) return;
        $pa = 'LCH';
        if(!(strlen($p)==1 && strpos($pa, $p)!==false)){
            $p='L';
        }
        if($img) {
            $m = 'get'.$w;
            $img = self::image($img, $imgStyle, true);
            if($img)
                $this->sheet->getHeaderFooter()->addImage($img, $p.'H');
            unset($m, $img);
            $s = '&G';
        }
        if($s) {
            $m = 'set'.$w;
            if(!preg_match('/&[LCH]/', $s)) $s = '&'.$p.$s;
            if(substr($w, 0,5)=='First') $this->sheet->getHeaderFooter()->setDifferentFirst(true);
            $this->sheet->getHeaderFooter()->$m($s);
            unset($m);
        }
    }

    public function render($format = 'xlsx', $filename= '', $download=true, $keepFile=false) 
    {
        $this->compositeExcel();

        if($format == '') {
			$format = 'xlsx';
        }
		
		if ($filename == '' && $format!='html') {
			$filename = 'spreadsheet-'.date("Ymd").'.'.$format;
        }
        if($download && !$keepFile && $filename) {
            $download = $filename;
            $file = tempnam($this->_saveas, $filename); 
        } else {
            $file = ($filename && substr($filename, 0, 1)!='/')?($this->_saveas.'/'.$filename):($filename);
        }
	
        if ($format == 'xlsx') {            
            return $this->renderXlsx($file, $download, $keepFile);
        } elseif ($format == 'xls') {
            return $this->renderXls($file, $download, $keepFile);
        } elseif ($format == 'pdf') {
            return $this->renderPdf($file, $download, $keepFile);
        } elseif ($format == 'csv') {
            return $this->renderCsv($file, $download, $keepFile);
        } elseif ($format == 'html') {
            return $this->renderHtml($file, $download, $keepFile);
        } else {
            return "Error: unsupported export format!";
        }		
    }

    private function renderHtml($file=null, $download=true, $keepFile=false)
    {
        $w = new PHPExcel_Writer_HTML($this->excel);
        $w->setImagesRoot('');
        $w->setEmbedImages(true);
        if($file) {
            $w->save($file);

        } else {
            $Ss = $this->excel->getAllSheets();
            $s = '';
            foreach($Ss as $i=>$S) {
                $w->setSheetIndex($i);
                $w->buildCSS(true);
                $s .=  $w->generateStyles(true)
                    . $w->generateNavigation()
                    . $w->generateSheetData();

            }
            return $s;
        }
        $this->clearAllObjects();
        $this->download($download, $file, $keepFile);
        return $file;
    }
    
    private function renderXlsx($file, $download=true, $keepFile=false)
    {
        $writer = new PHPExcel_Writer_Excel2007($this->excel);
        $writer->save($file);
        $this->clearAllObjects();
        $this->download($download, $file, $keepFile);
        return $file;
    }
    
    private function renderXls($file, $download=true, $keepFile=false)
    {
        $writer = new PHPExcel_Writer_Excel5($this->excel);
        $writer->save($file);
        $this->clearAllObjects();
        $this->download($download, $file, $keepFile);
        return $file;
    }

    public function setGrid($v=true)
    {
        $this->sheet->setShowGridlines($v);
        return $this;
    }
    /**
     * @TODO: Aprimorar -- não funciona com template de outro formato;
     */
    private function renderPdf($file, $download=true, $keepFile=false)
    {
        if(!class_exists('Dompdf\Dompdf') || !PHPExcel_Settings::setPdfRenderer(PHPExcel_Settings::PDF_RENDERER_DOMPDF, dirname(TDZ_ROOT).'/dompdf')) {
            if(!class_exists('TCPDF') || !PHPExcel_Settings::setPdfRenderer(PHPExcel_Settings::PDF_RENDERER_TCPDF, dirname(TDZ_ROOT).'/TCPDF-src')) {
                new Tecnodesign_Pdf();
                PHPExcel_Settings::setPdfRenderer(PHPExcel_Settings::PDF_RENDERER_TCPDF, dirname(TDZ_ROOT).'/TCPDF-src');
            }

        }

        $writer = PHPExcel_IOFactory::createWriter($this->excel, 'PDF');
        $writer->save($file);
        $this->clearAllObjects();
        $this->download($download, $file, $keepFile);
        return $file;
    }
    
    /**
     * @TODO: Aprimorar -- não funciona com template de outro formato;
     */
    private function renderCsv($file, $download=true, $keepFile=false)
    {
        $writer = new PHPExcel_Writer_CSV($this->excel);
        $writer->save($file);
        $this->clearAllObjects();
        $this->download($download, $file, $keepFile);
        return $file;
    }

    private function download($download, $file, $keepFile=false)
    {
        if($download) {
            $fname = (is_string($download) && strlen($download)>1)?($download):(pathinfo($file, PATHINFO_BASENAME));
            tdz::download($file, null, $fname, 0, true, false, false);
            if(!$keepFile) unlink($file);
            unset($fname);
        }

    }
    
    private function clearAllObjects()
    {
        unset($this->excel, $this->sheet);
    }


    public function val($s)
    {
        return str_replace(array_keys($this->r), array_values($this->r), $s);
    }

    public function pos($p=null)
    {
        $P=array($this->x, $this->y);
        if($p && is_array($p)) {
            if(isset($p[0]) && $p[0]!='') {
                if($p[0][0]=='{') $p[0]=$this->val($p[0]);
                if($p[0]<0 || $p[0][0]=='+') $P[0] += (int) $p[0];
                else $P[0] = $p[0];
            }
            if(isset($p[1]) && $p[1]!='') {
                if($p[1][0]=='{') $p[1]=$this->val($p[1]);
                if($p[1]<0 || $p[1][0]=='+') $P[1] += (int) $p[1];
                else $P[1] = $p[1];
            }
        }
        return $P;
    }

    protected $r=array();
    public function addReplacement($r)
    {
        if(is_array($r)) {
            $this->r=array_merge($r, $this->r);
        }
    }

    public function addContent($c)
    {
        if(isset($c['if']) && !(isset($this->r[$c['if']]) && $this->r[$c['if']])) return;
        if(isset($c['ifnot']) && (isset($this->r[$c['ifnot']]) && $this->r[$c['ifnot']])) return;
        if(isset($c['sheet'])) {
            $this->sheet($this->val($c['sheet']));
        }
        $write = true;
        $headerFooter = false;
        if(isset($c['position'])) {
            if($c['position']===false) $write=false;
            else if(is_string($c['position']) && ($c['position']=='header' || $c['position']=='footer' || in_array($c['position'], static::$headerFooter))) $headerFooter = ucfirst($c['position']);
            else list($this->x, $this->y) = $this->pos($c['position']);
        }
        if(isset($c['merge'])) {
            $to = $this->pos($c['merge']);
            $this->merge(array($this->x-1, $this->y-1), array($to[0]-1, $to[1]-1));
        }
        $this->r['{x}']  = $this->getColLetter($this->x-1);
        $this->r['{_x}'] = $this->x;
        $this->r['{y}']  = $this->y;
        if(isset($c['content']) && isset($c['method']) && is_array($c['content'])) {
            $v = (isset($c['content'][$c['method']]))?($c['content'][$c['method']]):('');
        } else if(isset($c['content']) && isset($c['property'])) {
            $v = (isset($c['content'][$c['property']]))?($c['content'][$c['property']]):('');
        } else if(isset($c['class']) || isset($c['method'])) {
            if(isset($c['content']) && is_object($c['content']) && !isset($c['class'])) {
                $o = $c['content'];
            } else if(isset($c['content']) && isset($c['class'])) {
                $o = $c['class'];
                $o = new $o($c['content']);
            } else if(!isset($c['class'])) {
                $call = $c['method'];
            } else {
                $o = $c['class'];
                if(isset($c['id'])) {
                    $o = $o::find($c['id']);
                }
            }
            if(!isset($call)) $call = array($o, (isset($c['method']))?($c['method']):('render'));
            if(isset($c['arguments'])) {
                foreach($c['arguments'] as $a=>$b) {
                    $c['arguments'][$a] = $this->val($b);
                    unset($a, $b);
                }
                $v = tdz::call($call, $c['arguments']);
                unset($c['arguments']);
            } else {
                $v = tdz::call($call);
            }
            unset($o, $call);
        } else if(isset($c['content']) && is_object($c['content']) && $c['content'] instanceof Tecnodesign_Collection) {
            if(isset($c['arguments'])) {
                foreach($c['arguments'] as $a=>$b) {
                    $c['arguments'][$a] = $this->val($b);
                    unset($a, $b);
                }
                array_unshift($c['arguments'], $c['content']);
                tdz::objectCall($this, 'addCollection', $c['arguments']);
                unset($c['arguments']);
            } else {
                $this->addCollection($c['content']);
            }
            unset($c['content'], $c);
            return;
        } else if(isset($c['content']) && $c['content']===false) {
            $v = null;
            $write = false;
        } else if(isset($c['content'])) {
            $v = $this->val($c['content']);
        } else {
            $v = null;
            $write = false;
        }
        if($v && is_object($v) && get_class($v)=='ArrayObject') {
            $v = (array) $v;
        }
        $t = null;
        if(isset($c['type'])) {
            if($c['type']=='formula') $t = Tecnodesign_Excel::TYPE_FORMULA;
            else if($c['type']=='string' || $c['type']=='text') $t = Tecnodesign_Excel::TYPE_STRING;
            else if($c['type']=='date') {
                $t = '';
            } else if($c['type']=='number') $t = Tecnodesign_Excel::TYPE_NUMERIC;
            else if($c['type']=='image') {
                $t = Tecnodesign_Excel::TYPE_IMAGE;
                $v = tdz::getApp()->tecnodesign['document-root'].'/'.$v;
            } else if($c['type']=='loop') {
                if(is_array($v) && is_array($c['loop'])) {
                    $this->r['{count}'] = count($v);
                    if(isset($c['variable'])) {
                        foreach($c['variable'] as $vn=>$vv) {
                            $this->r['{'.$vn.'}'] = $this->val($vv);
                            unset($vn, $vv);
                        }
                    }
                    foreach($v as $lk=>$vc) {
                        $id = (isset($c['loop-variable']))?($c['loop-variable']):('loop');
                        $this->r['{'.$id.'}'] = $vc;
                        $this->r['{key}'] = $lk;
                        foreach($c['loop'] as $cc) {
                            if(!isset($cc['content']) && !isset($cc['class'])) $cc['content'] = $vc;
                            $this->addContent($cc);
                            unset($cc);
                        }
                        unset($vc, $id, $lk, $this->r['{key}']);
                    }
                }
                return;
            } else if($c['type']=='page') {
                $this->page($c['style']);
            }
        }
        if(is_array($v)) {
            if(count($v)==0) $v = '';
            else if(count($v)==1) $v = array_shift($v);
        }
        if(!is_array($v)) $this->r['{value}'] = $v;
        else $this->r['{count}'] = count($v);
        if(isset($c['variable'])) {
            foreach($c['variable'] as $vn=>$vv) {
                $this->r['{'.$vn.'}'] = $this->val($vv);
                unset($vn, $vv);
            }
        }
        if(isset($c['constant'])) {
            foreach($c['constant'] as $vn=>$vv) {
                if(!isset($this->r['{'.$vn.'}']))
                    $this->r['{'.$vn.'}'] = $this->val($vv);
                unset($vn, $vv);
            }
        }
        if($headerFooter) {
            if(strlen($headerFooter)<7) $headerFooter = 'Odd'.ucfirst($headerFooter);
            $p='L';
            $s = $this->style(false, false, $c);
            if(isset($s['align']) && $s['align']=='right') $p='R';
            else if(isset($s['align']) && $s['align']=='center') $p='C';

            if(isset($c['type']) && $c['type']=='image') {
                $img = $v;
                $this->setHeaderFooter(null, $headerFooter, $p, $img, $s);
            } else {
                $this->setHeaderFooter($v, $headerFooter, $p);
            }
            return;
        } else if($write) {
            if(is_array($v)) {
                foreach($v as $y=>$cv) {
                    if(is_array($cv)) {
                        $xi=0;
                        foreach($cv as $x=>$cvv) {
                            if(!is_numeric($x)) $x = $xi;
                            $sty=$this->style($x, $y, $c);
                            $ty=$t;
                            if(isset($sty['type'])) {
                                if($sty['type']=='string' || $sty['type']=='text') $ty = Tecnodesign_Excel::TYPE_STRING;
                            }
                            $this->setCell(array($this->x-1+$x, $this->y-1+$y), $cvv, $ty, $sty);
                            $this->x+$x;
                            $this->y+$y;
                            $xi++;
                            unset($cvv, $sty, $ty);
                        }
                        unset($cv);
                    } else {
                        $sty=$this->style($this->x, $this->y, $c, $y);
                        $ty = $t;
                        if(isset($sty['type'])) {
                            if($sty['type']=='string' || $sty['type']=='text') $ty = Tecnodesign_Excel::TYPE_STRING;
                        }
                        $this->setCell(array($this->x-1, $this->y-1), $cv, $ty, $sty);
                        $this->x++;
                        unset($y, $cv, $sty, $ty);
                    }
                }
                if(isset($x)) $this->x+=$x;
                if(isset($y)) $this->y+=$y;
            } else {
                $comment = (isset($c['comment']))?($this->val($c['comment'])):(null);
                if(is_null($t) && !is_numeric($v)) $t = Tecnodesign_Excel::TYPE_STRING;
                if(isset($to)) {
                    $this->setCell(array($this->x-1, $this->y-1), $v, $t, $this->style($this->x, $this->y, $c), $comment);
                    $to = $this->cell(array($this->x-1, $this->y-1), array($to[0]-1, $to[1]-1));
                    $this->setStyle($to, $this->style($this->x, $this->y, $c));
                } else {
                    $this->setCell(array($this->x-1, $this->y-1), $v, $t, $this->style($this->x, $this->y, $c), $comment);
                }
                $this->x++;
            }
        }
        if(isset($c['repeat']) && ($repeat=$this->val($c['repeat']))) {
            unset($c['repeat']);
            while($repeat-- > 1) {
                $this->addContent($c);
            }
        } 
    }

    public function addCollection($c, $scope='report')
    {
        $cn = $c->getClassName();
        if($cn) {
            $f=array();
            $cols = (isset($cn::$schema['scope'][$scope]))?($cn::$schema['scope'][$scope]):(array_keys($rn::$schema['columns']));
            $labels = array();
            foreach($cols as $label=>$fn) {
                $fi = $fn;
                if(strpos($fn, ' ')) list($fn, $fi) = explode(' ', $fn, 2);
                if(isset($f[$fi])) continue;
                if(is_int($label)) $label = $cn::fieldLabel($fn);
                $f[$fi] = $cn::column($fn,true,true);
                $f[$fi]['label']=$label;
                $labels[] = $label;
                unset($fi, $l, $label, $fn);
            }
            $this->addContent(array('content'=>$labels, 'use'=>'.header', 'position'=>array(1, '+1')));
            unset($cols, $labels);
        }

        $limit = 100;
        $max = 5000;
        $fks=array();
        if($c && ($tot=$c->count()) && $tot>0) {
            $i = 0;
            if($tot>$max) $tot = $max;
            while($i<$tot) {
                $i0 = $i;
                foreach((array) $c->getItem($i, $limit) as $o) {
                    if(isset($f)) {
                        $a = array();
                        foreach($f as $fn=>$fd) {
                            $a[$fn]=$o->renderField($fn, $fd);
                            unset($fd, $fn);
                        }
                        $this->addContent(array('content'=>$a,'use'=>'.normal','position'=>array(1, '+1')));
                        unset($a);
                    } else {
                        $this->addContent(array('content'=>$o,'use'=>'.normal','position'=>array(1, '+1')));
                    }
                    $i++;
                    unset($o);
                }
                //self::status('Armazenando registros ('.((int) (100*$i/$tot)).'%)');
                unset($ro);
                if($i==$i0) break;
            }
            if(isset($f))
                $this->addContent(array('content'=>'', 'use'=>'.end','position'=>array(1, '+1'),'merge'=>array(count($f), 0)));
        }
    }

}
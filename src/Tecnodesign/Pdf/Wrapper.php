<?php
/**
 * PDF creation and manipulation
 * 
 * This package extends TCPDF (www.tcpdf.org) and FPDI and includes several methods
 * to make the PDF creation process as simple as possible, with many resources
 * available.
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.6
 */

/**
 * Loading TCPDF and FPDI bootstraps
 */
define('K_TCPDF_EXTERNAL_CONFIG', 1);
define('K_CELL_HEIGHT_RATIO', 1.25);

/**
 * Installation path (/var/www/tcpdf/).
 * By default it is automatically calculated but you can also set it as a fixed string to improve performances.
 */
if(!defined('K_PATH_MAIN')) define ('K_PATH_MAIN', TDZ_VAR);

/**
 * URL path to tcpdf installation folder (http://localhost/tcpdf/).
 * By default it is automatically calculated but you can also set it as a fixed string to improve performances.
 * -- not available
 */
if(!defined('K_PATH_URL')) define ('K_PATH_URL', '');

/**
 * path for PDF fonts
 * use K_PATH_MAIN.'fonts/old/' for old non-UTF8 fonts
 */
if(!defined('K_PATH_FONTS') && is_dir(K_PATH_MAIN.'/fonts/')) define ('K_PATH_FONTS', K_PATH_MAIN.'/fonts/');

/**
 * cache directory for temporary files (full path)
 */
if(!defined('K_PATH_CACHE')) 
if (isset(tdz::getApp()->tecnodesign['cache-dir']) && tdz::getApp()->tecnodesign['cache-dir'] != null) {
    define ('K_PATH_CACHE', tdz::getApp()->tecnodesign['cache-dir'].'/');
} else {
    define ('K_PATH_CACHE', S_VAR.'/cache/');
};

/**
 * cache directory for temporary files (url path)
 */
if(!defined('K_PATH_URL_CACHE')) define ('K_PATH_URL_CACHE', '/cache/');

/**
 *images directory
 */
if(!defined('K_PATH_IMAGES') && is_dir(S_VAR.'/images/')) define ('K_PATH_IMAGES', S_VAR.'/images/');

/**
 * blank image
 */
if(!defined('K_BLANK_IMAGE') && file_exists(S_VAR.'/images/_blank.png')) define ('K_BLANK_IMAGE', K_PATH_IMAGES.'_blank.png');

/**
 * page format
 */
if(!defined('PDF_PAGE_FORMAT')) define ('PDF_PAGE_FORMAT', 'A4');

/**
 * page orientation (P=portrait, L=landscape)
 */
if(!defined('PDF_PAGE_ORIENTATION')) define ('PDF_PAGE_ORIENTATION', 'P');

/**
 * document creator
 */
if(!defined('PDF_CREATOR')) define ('PDF_CREATOR', 'Tecnodesign');

/**
 * document author
 */
if(!defined('PDF_AUTHOR')) define ('PDF_AUTHOR', 'Tecnodesign');

/**
 * header title
 */
if(!defined('PDF_HEADER_TITLE')) define ('PDF_HEADER_TITLE', 'Untitled');

/**
 * header description string
 */
if(!defined('PDF_HEADER_STRING')) define ('PDF_HEADER_STRING', "2016 Tecnodesign");

/**
 * image logo
 */
if(!defined('PDF_HEADER_LOGO')) define ('PDF_HEADER_LOGO', 'tecnodesign.png');

/**
 * header logo image width [mm]
 */
if(!defined('PDF_HEADER_LOGO_WIDTH')) define ('PDF_HEADER_LOGO_WIDTH', 30);

/**
 *  document unit of measure [pt=point, mm=millimeter, cm=centimeter, in=inch]
 */
if(!defined('PDF_UNIT')) define ('PDF_UNIT', 'mm');

/**
 * header margin
 */
if(!defined('PDF_MARGIN_HEADER')) define ('PDF_MARGIN_HEADER', 5);

/**
 * footer margin
 */
if(!defined('PDF_MARGIN_FOOTER')) define ('PDF_MARGIN_FOOTER', 10);

/**
 * top margin
 */
if(!defined('PDF_MARGIN_TOP')) define ('PDF_MARGIN_TOP', 27);

/**
 * bottom margin
 */
if(!defined('PDF_MARGIN_BOTTOM')) define ('PDF_MARGIN_BOTTOM', 25);

/**
 * left margin
 */
if(!defined('PDF_MARGIN_LEFT')) define ('PDF_MARGIN_LEFT', 15);

/**
 * right margin
 */
if(!defined('PDF_MARGIN_RIGHT')) define ('PDF_MARGIN_RIGHT', 15);

/**
 * default main font name
 */
if(!defined('PDF_FONT_NAME_MAIN')) define ('PDF_FONT_NAME_MAIN', 'dejavusans');

/**
 * default main font size
 */
if(!defined('PDF_FONT_SIZE_MAIN')) define ('PDF_FONT_SIZE_MAIN', 10);

/**
 * default data font name
 */
if(!defined('PDF_FONT_NAME_DATA')) define ('PDF_FONT_NAME_DATA', 'dejavusans');

/**
 * default data font size
 */
if(!defined('PDF_FONT_SIZE_DATA')) define ('PDF_FONT_SIZE_DATA', 8);

/**
 * default monospaced font name
 */
if(!defined('PDF_FONT_MONOSPACED')) define ('PDF_FONT_MONOSPACED', 'courier');

/**
 * ratio used to adjust the conversion of pixels to user units
 */
if(!defined('PDF_IMAGE_SCALE_RATIO')) define ('PDF_IMAGE_SCALE_RATIO', 787);

/**
 * magnification factor for titles
 */
if(!defined('HEAD_MAGNIFICATION')) define('HEAD_MAGNIFICATION', 1.1);

/**
 * height of cell repect font height
 */
//define('K_CELL_HEIGHT_RATIO', 1.25);

/**
 * title magnification respect main font size
 */
if(!defined('K_TITLE_MAGNIFICATION'))  define('K_TITLE_MAGNIFICATION', 1.3);

/**
 * reduction factor for small font
 */
if(!defined('K_SMALL_RATIO'))  define('K_SMALL_RATIO', 2/3);

/**
 * set to true to enable the special procedure used to avoid the overlappind of symbols on Thai language
 */
if(!defined('K_THAI_TOPCHARS')) define('K_THAI_TOPCHARS', true);

/**
 * if true allows to call TCPDF methods using HTML syntax
 * IMPORTANT: For security reason, disable this feature if you are printing user HTML content.
 */
if(!defined('K_TCPDF_CALLS_IN_HTML')) define('K_TCPDF_CALLS_IN_HTML', true);

class Tecnodesign_Pdf_Wrapper extends \setasign\Fpdi\TcpdfFpdi
{
    private $Parent;
   
    /**
     * This is the class constructor.
     * It allows to set up the page format, the orientation and the measure unit used in all the methods (except for the font sizes).
     * @param $orientation (string) page orientation. Possible values are (case insensitive):<ul><li>P or Portrait (default)</li><li>L or Landscape</li><li>'' (empty string) for automatic orientation</li></ul>
     * @param $unit (string) User measure unit. Possible values are:<ul><li>pt: point</li><li>mm: millimeter (default)</li><li>cm: centimeter</li><li>in: inch</li></ul><br />A point equals 1/72 of inch, that is to say about 0.35 mm (an inch being 2.54 cm). This is a very common unit in typography; font sizes are expressed in that unit.
     * @param $format (mixed) The format used for pages. It can be either: one of the string values specified at getPageSizeFromFormat() or an array of parameters specified at setPageFormat().
     * @param $unicode (boolean) TRUE means that the input text is unicode (default = true)
     * @param $encoding (string) Charset encoding; default is UTF-8.
     * @param $diskcache (boolean) If TRUE reduce the RAM memory usage by caching temporary data on filesystem (slower).
     * @param $pdfa (boolean) If TRUE set the document to PDF/A mode.
     * @public
     * @see getPageSizeFromFormat(), setPageFormat()
     */
    public function __construct($orientation='P', $unit='mm', $format='A4', $unicode=true, $encoding='UTF-8', $diskcache=false, $pdfa=false, $Parent=null) {
        if($Parent) $this->Parent=$Parent;
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
    }

    /**
     * Header control. Looks for parameters to set page background and custom layouts
     *
     * @return void
     */
    public function header()
    {
        $bg=false;
        if (($B = $this->Parent->data('backgroundImages'))
            && is_array($B)
            && count($B)>0
        ) {
            $class=($this->page % 2)?('odd'):('even');
            if (isset($B[$class])) {
                $bg = $B[$class];
            } else if (isset($B[0])) {
                $bg = array_shift($B);
                $this->Parent->data('backgroundImages', $B);
            }
            $this->Parent->data('backgroundImage', $bg);
            unset($B);
        }
        if (!$bg && ($B=$this->Parent->data('backgroundImage'))) {
            $bg = $B;
        }
        unset($B);
        // store current auto-page-break status
        $bMargin = $this->getBreakMargin();
        $auto_page_break = $this->AutoPageBreak;
        $this->SetAutoPageBreak(false, 0);
        if ($bg) {
            if (is_array($bg)) {
                $bg+=array(1 => 0, 2 => 0);
                tdz::objectCall($this, 'addImage', $bg);
            } else {
                $this->addImage($bg, 0, 0);
            }
        }
        if ($C=$this->Parent->data('headerContent')) {            
            $this->add($C);            
        }
        unset($C);
        // restore auto-page-break status
        $this->SetAutoPageBreak($auto_page_break, $bMargin);
    }

    /**
     * Footer control. Looks for contents to be set.
     *
     * @return void
     */
    public function footer()
    {
        // store current auto-page-break status
        $bMargin = $this->getBreakMargin();
        $auto_page_break = $this->AutoPageBreak;
        $this->SetAutoPageBreak(false, 0);

        if ($footer=$this->Parent->data('footerContent')) {
            if(!is_array($footer)) {
                $footer = array($footer);
            }
            foreach($footer as $k=>$v) {
                if(is_string($v) && strpos($v, '[[')!==false) {
                    $footer[$k] = str_replace(array('[[page]]', '[[pages]]'), array($this->PageNo(), $this->getAliasNbPages()), $v);
                }
            }
            $this->add($footer);
        }
        // restore auto-page-break status
        $this->SetAutoPageBreak($auto_page_break, $bMargin);
    }

    /**
     * General content addition. Simply place the arguments and it will try to add
     * it properly
     *
     * @param  mixed $content content to be added
     *
     * @return void
     */
    public function add()
    {
        $a = func_get_args();
        if (!empty($a[0]) && is_array($a[0])) {
            foreach ($a as $arg) {
                tdz::objectCall($this, 'add', $arg);
            }
        } else if(strpos($a[0], '<')!==false) { // html content
            tdz::objectCall($this, 'WriteHTML', $a);
        } else if(file_exists($a[0])) { // images
            tdz::objectCall($this, 'addImage', $a);
        } else if(isset($a[4]) && strpos($a[4], '<')!==false) { // html box
            tdz::objectCall($this, 'writeHtmlCell', $a);
        } else { // write...
            tdz::objectCall($this, 'Write', $a);
        }
    }

    /**
     * Inserts images in PDF. Calls each individual method according to the image
     * file extension. Further arguments are passed directly to the specific method.
     *
     * @param string $image image file name
     *
     * @return void
     * @see Image()
     * @see ImagePDF()
     * @see ImageEPS()
     * @see ImageSVG()
     */
    public function addImage($image)
    {
        $arguments=func_get_args();
        $ret = false;
        $ext = strtolower(preg_replace('/^.*\.([a-z]{2,4})$/i', '$1', $image));
        if ($ext == 'pdf') {
            $ret = tdz::objectCall($this, 'addPdf', $arguments);
        } else if ($ext == 'eps' || $ext == 'ai') {
            $ret = tdz::objectCall($this, 'ImageEps', $arguments);
        } else if ($ext == 'svg' || $ext == 'xml') {
            $ret = tdz::objectCall($this, 'ImageSVG', $arguments);
        } else {
            $ret = tdz::objectCall($this, 'Image', $arguments);
        }
        return $ret;
    }

    /**
     * Inserts external PDFs as pages or images.
     *
     * @param string $file PDF file to be inserted
     * @param int    $x    left position of the image, defaults to current position
     * @param int    $y    top position of the image, defaults to current position
     * @param int    $w    image width, defaults to real dimension
     * @param int    $h    image height, defaults to real dimension
     *
     * @return void
     * @see Image()
     * @see ImagePDF()
     * @see ImageEPS()
     * @see ImageSVG()
     */
    public function addPdf($file, $x=null, $y=null, $w=null, $h=null)
    {
        $pagecount = $this->setSourceFile($file);
        $tplIdx = $this->importPage(1);
        $size = $this->useTemplate($tplIdx, $x, $y, $w, $h);
        
        
    }
    
    public function mergePDF($files, $meta=array())
    {
        return $this->merge($files, $meta);
    }
    
    /**
     * Merge external PDFs as pages
     * 
     * @param array $files PDF files
     * @param array $meta info to PDF files
     * @return void
     */
    public function merge($files, $meta = array()) 
    {
        foreach ($files as $file) {
            if (file_exists($file)) {
                $bookmarks=array();
                for ($i = 1; ($pagecount = $this->setSourceFile($file)) && $i <= $pagecount; $i++) {
                    $tpl = $this->importPage($i);
                    $size = $this->getTemplatesize($tpl);   
                    $this->AddPage('P', array($size['w'], $size['h']));
                    $this->useTemplate($tpl, null, null, null, null, true);
                    if (isset($meta['bookmarks'][$file])) { //um bookmark mais detalhado
                        $bookmarks = $meta['bookmarks'][$file][$i];
                        
                        foreach($bookmarks as $pos => $level) {                            
                            foreach($level as $k => $v) {
                                $this->Bookmark($v, $k, $pos, '', '', array(0,0,0));
                            }
                        }
                    } else {
                         $bookmark=false;
                        if ($i == 1) {
                            $level=0;
                            if (isset($meta['bookmark'][$file]) && is_array($meta['bookmark'][$file])) {
                                $bookmark = html_entity_decode($meta['bookmark'][$file][1]);
                                $bookmarks = $meta['bookmark'][$file];
                            } else if (isset($meta['bookmark'][$file])) {
                                $bookmark = html_entity_decode($meta['bookmark'][$file]);
                            } else {
                                $bookmark = $file;
                            }
                        } else if(isset($bookmarks[$i])){
                            $bookmark = html_entity_decode($bookmarks[$i]);                        
                        }
                        if($bookmark){
                            if(preg_match('/^(\s+)(.*)/', $bookmark, $m)){
                                $level = strlen($m[1]);
                                $bookmark = $m[2];
                            }                                                
                            $this->Bookmark($bookmark, $level, 0, '', '', array(0,0,0));
                        }
                    }
                }
            }
        }
    }
    
    
    /**
     * To Verify the coordinates
     * ATENTION: This is only for development. Please don't use this
     * on PDF production
     */
    public function printPageMesh() 
    {
        $this->addPage();
        $this->SetMargins(50, 0, 0, 0);
        $this->footerMargin = 0;
        $this->WriteHtml('<p><strong>PDF Mesh - Unit: '.$this->_options['unit'].'</strong></p>');
        while($this->GetY() < 370) {
            $this->writeHTML('<p>Y: '.round($this->GetY(),2).' '.str_repeat('.',140).'</p>');
            if ($this->checkPageBreak(0,$this->GetY()+10,false)) {
                break;
            }
        }
        $this->addPage();
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
    /*
    public function  __set($name, $value)
    {
        $m='set'.ucfirst($name);
        $M='Set'.ucfirst($name);
        if (method_exists($this, $m)) {
            $this->$m($value);
        } else if (method_exists($this, $M)) {
            $this->$M($value);
        } else if(property_exists($this, $name)) {
            $this->$name = $value;
        }
        if(isset($this->Parent)) $this->Parent->data($name, $value);
    }
    */

    /**
     * Magic getter. Searches for a get$Name method, or gets the stored value in
     * $_vars.
     *
     * @param string $name parameter name, should start with lowercase
     * 
     * @return mixed the stored value, or method results
     */
    /*
    public function  __get($name)
    {
        $m='get'.ucfirst($name);
        $M='Get'.ucfirst($name);
        $ret = false;
        if (method_exists($this, $m)) {
            $ret = $this->$m();
        } else if (method_exists($this, $M)) {
            $ret = $this->$M();
        } else if ((!isset($this->Parent) || !($ret=$this->Parent->data($name))) && isset($this->$name)) {
            $ret = $this->$name;
        }
        return $ret;
    }
    */
}
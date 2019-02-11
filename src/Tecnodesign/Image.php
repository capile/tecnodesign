<?php
/**
 * Tecnodesign Image
 *
 * Image manipulation component 
 *
 * PHP version 5.2
 *
 * @category  Image
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2012 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @version   SVN: $Id$
 * @link      http://tecnodz.com/
 */

/**
 * Tecnodesign Image
 *
 * Image manipulation component 
 *
 * @category  Image
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2012 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      http://tecnodz.com/
 */

class Tecnodesign_Image 
{
    public $img, $src, $srcX=0, $srcY=0, $srcHeight, $srcWidth, $srcType, $srcRGB=true, $originalMemoryLimit, $data, $color=0, $font='OpenSans-Regular.ttf', $fontSize=10, $textAlign='left', $stroke=1;
    public $width=0, $height=0, $x=0, $y=0, $ratio, $type, $sharpen, $quality=90, $backgroundColor, $antialias, $backgroundImage, $crop=true, $resize=true, $fit=false, $fill=false, $stretch=true, $transparent;
    protected static $reset=array('src'=>false, 'backgroundColor'=>false, 'backgroundImage'=>false);

    public function __construct($src=null, $config=array())
	{
        if(is_array($src)) {
            $config=$src;
        } else if(!is_null($src)) {
            $config['source']=$src;
        }
        $this->addSettings($config);
	}

    public function __toString()
    {
        return $this->render();
    }

    public function mimeType()
    {
        $type=(!is_null($this->type))?('image/'.$this->type):(false);
        return $type;
    }

    public function addSettings($config=array())
    {
        $x=$this->x;$y=$this->y;$width=$this->width;$height=$this->height;
        if(isset($config['width']))  $width =abs((int)$config['width']);
        if(isset($config['height'])) $height=abs((int)$config['height']);
        if(isset($config['x']))      $x     =abs((int)$config['x']);
        if(isset($config['y']))      $y     =abs((int)$config['y']);
        $r=false;
        if(isset($config['ratio'])) {
            $r=$config['ratio'];
        }
        if($r!==false) {
            if($width==0 && $height>0) $width = $r*$height;
            else if($height==0 && $width>0) $height= $width/$r;
        }
        if($r===false && $height>0 && $width>0) {
            $r=$width/$height;
        }
        if(isset($config['background'])) {
            $this->fill=true;
            if(strpos($config['background'], ' ')>0) {
                list($config['background-color'], $config['background-color'])=explode(' ', $config['background'], 2);
            } else {
                $config['fill']=$config['background'];
            }
        }
        if(isset($config['fill'])) {
            $this->fill=true;
            if(substr($config['fill'], 0, 1)=='#' || is_numeric($config['fill'])) {
                if(!isset($config['background-color'])) $config['background-color']=$config['fill'];
            } else if(strpos($config['fill'], '.')>0) {
                if(!isset($config['background-image'])) $config['background-image']=$config['fill'];
            }
        }
        if(isset($config['background-color'])) {
            $this->backgroundColor=$config['background-color'];
        }
        if(isset($config['background-image'])) {
            $this->backgroundImage=self::fileSrc($config['background-image']);
        }

        if(isset($config['source'])) $this->setSource($config['source']);
        if(!is_null($this->src)) {
            // image adjustments: resize, crop, fit
            if(isset($config['resize'])) $this->resize = (bool)$config['resize'];
            if(isset($config['stretch'])) $this->stretch = (bool)$config['stretch'];
            if(isset($config['fit'])) $this->fit = (bool)$config['fit'];
            $srcX=$this->srcX;$srcY=$this->srcY;$srcWidth=$this->srcWidth;$srcHeight=$this->srcHeight;
            if(isset($config['crop']))  {
                if(is_array($config['crop'])) {
                    if(isset($config['crop']['x'])) $srcX=$config['crop']['x'];
                    if(isset($config['crop']['y'])) $srcY=$config['crop']['y'];
                    if(isset($config['crop']['width'])) $srcWidth=$config['crop']['width'];
                    if(isset($config['crop']['height'])) $srcHeight=$config['crop']['height'];
                    $this->crop=1;
                } else $this->crop = $config['crop']; // ammount to crop, relative to the src size
            }
            $sr=$srcWidth/$srcHeight;


            // boundary adjustments, if not set
            if($width==0||$height==0) {
                if($width==0 && $height>0) $width = $sr*$height;
                else if($height==0 && $width>0) $height= $width/$sr;
                else if($r!==false && $r!=$sr) {
                    // fit: image should be within boundary
                    if(($this->fit && $sr>$r) || (!$this->fit && $sr<$r)) { // src is wider than expected, height should be adjusted to it
                        $height=$this->srcWidth/$r;
                        $width=$this->srcWidth;
                    } else {
                        $width=$this->srcHeight*$r;
                        $height=$this->srcHeight;
                    }
                    $r=$sr;
                } else {
                    $width=$this->srcWidth;
                    $height=$this->srcHeight;
                }
            }
            if($this->resize && !$this->stretch) {
                if($this->crop===false) {
                    if(($srcWidth>$width || $srcHeight>$height) && $r!=$sr) {
                        if($sr>$r) {
                            $srcWidth=$width;
                            $srcHeight=$width/$sr;
                        } else {
                            $srcHeight=$height;
                            $srcWidth=$height*$sr;
                        }
                    }
                }
                if($srcWidth<$width || $srcHeight<$height) {
                    $this->resize=false;
                    $srcX = ($width - $srcWidth)*0.5;
                    $srcY = ($height - $srcHeight)*0.5;
                }
            }
            if($this->resize) {
                if((int)$this->crop<=0) $this->crop=1;
                if($sr==$r) {
                    if(!is_bool($this->crop) && $this->crop!==1) {
                        $srcX = ($srcWidth - ($width / $this->crop))*0.5;
                        $srcY = ($srcHeight - ($height / $this->crop))*0.5;
                    }
                    $srcWidth=$width/$this->crop;
                    $srcHeight=$height/$this->crop;
                } else {

                    if(($this->fit && $r>$sr)||(!$this->fit && $r<$sr)) { // src is wider than expected, height should be adjusted to it
                        $srcHeight=$height/$this->crop;
                        $srcWidth=$srcHeight*$sr;
                    } else { // src is taller than expected, width should be adjusted to it
                        $srcWidth=$width/$this->crop;
                        $srcHeight=$srcWidth/$sr;
                    }
                }
                if($srcWidth!=$width) $srcX=($width - $srcWidth)*0.5;
                if($srcHeight!=$height) $srcY=($height - $srcHeight)*0.5;
            }
        }
        if(isset($config['output-format']) && !isset($config['type'])) {
            $config['type']=$config['output-format'];
        }
        if(isset($config['type'])) {
            $type = str_replace(array('image/', 'x-'), '', $config['type']);
            if(function_exists('imagecreatefrom'.$type)) {
                $this->type=$type;
            }
        }
        if(is_null($this->type)) {
            if(!is_null($this->src)) $this->type=$this->srcType;
            else $this->type='png';
        }
        if(isset($config['transparent'])) {
            $this->transparent=$config['transparent'];
        }
        if(is_null($this->transparent)) {
            $this->transparent=($this->type=='png' || $this->type=='gif');
        }

        // Determine the quality of the output image
        if(isset($config['quality'])) {
            $this->quality = $config['quality'];
        }
        // Anti-alias
        if(isset($config['anti-alias'])) {
            $this->antialias = (bool)$config['anti-alias'];
        } else if(is_null($this->antialias) && $this->resize) {
            $this->antialias=true;
        }

        // We don't want to run out of memory
        if(is_null($this->originalMemoryLimit))
            $this->originalMemoryLimit = ini_set('memory_limit', '100M');

        // Set up a blank canvas for our resized image (destination)
        $new=false;
        if(is_null($this->img)) {
            $this->img = imagecreatetruecolor($width, $height);
            $new = true;
            $this->width=$width;
            $this->height=$height;
            $this->x=$x;
            $this->y=$y;
        } else if($width!=$this->width || $height!=$this->height) {
            // if sizes are different than original size, resize image
            $img = imagecreatetruecolor($width, $height);
            $new = true;
            ImageCopyResampled($img, $this->img, $x, $y, $this->x, $this->y, $width, $height, $this->width, $this->height);
            $oimg=$this->img;
            $this->img=$img;
            ImageDestroy($oimg);
        }
        if($new && !is_null($this->antialias) && function_exists('imageantialias')) {
            imageantialias($this->img, $this->antialias);
        }

        if($new && $this->transparent) {
            // If this is a GIF or a PNG, we need to set up transparency
            imagealphablending($this->img, true);
            imagesavealpha($this->img, true);
            if(!is_bool($this->transparent) && is_numeric($this->transparent)) {
                imagecolortransparent($this->img, $this->transparent);
            }
        }

        if(isset($config['sharpen'])) {
            $this->sharpen=$config['sharpen'];
        }

        if(!is_null($this->backgroundColor)) {
            // Fill the background with the specified color for matting purposes
            $color=$this->backgroundColor;
            if ($color[0] == '#')
                $color = substr($color, 1);
            $background = $this->setColor($color);
            if ($background)
                imagefill($this->img, 0, 0, $background);
        }

        if($this->backgroundImage) {
            // imagefill
            $prop = GetImageSize($this->backgroundImage);
            $bgtype=false;
            if($prop) {
                $bgtype = str_replace(array('image/', 'x-'), '', $prop['mime']);
                if(!function_exists('imagecreatefrom'.$bgtype)) {
                    $bgtype=false;
                }
            }
            if($bgtype) {
                $bgfn='imagecreatefrom'.$bgtype;
                $bgi=$bgfn($this->backgroundImage);
                imagesettile($this->img, $bgi);
                // Make the image repeat
                imagefilledrectangle($this->img, 0, 0, $width, $height, IMG_COLOR_TILED);
            }
        }
        if(!is_null($this->src)) {
            // Resample the original image into the resized canvas we set up earlier
            ImageCopyResampled($this->img, $this->src, $srcX, $srcY, $this->srcX, $this->srcY, $srcWidth, $srcHeight, $this->srcWidth, $this->srcHeight);
            ImageDestroy($this->src);
            $this->src=null;
            if(is_null($this->sharpen) && $this->resize && $this->type!='gif') {
                $this->sharpen=true;
            }
            if ($this->sharpen) {
                // Sharpen the image based on two things:
                //  (1) the difference between the original size and the final size
                //  (2) the final size
                $final = $srcWidth * (750.0 / $this->srcWidth);
                $a = 52;
                $b = -0.27810650887573124;
                $c = .00047337278106508946;

                $sharpness = $a + $b * $final + $c * $final * $final;
                $sharpenMatrix = array(
                    array(-1, -2, -1),
                    array(-2, $sharpness + 12, -2),
                    array(-1, -2, -1)
                );
                $divisor = $sharpness;
                $offset = 0;
                imageconvolution($this->img, $sharpenMatrix, $divisor, $offset);
            }
        }

        if(isset($config['color'])) {
            $color =  $this->setColor($config['color']);
            if($color) $this->color=$color;
        }

        if(isset($config['stroke'])) {
            $this->stroke=$config['stroke'];
            imagesetthickness ($this->img, $config['stroke']);
        }
        // Shapes and polylones
        if(isset($config['shape']) && is_array($config['shape'])) {
            imagefilledpolygon($this->img, $config['shape'], count($config['shape'])*0.5, $this->color);
        }
        // Shapes and polylones
        if(isset($config['line']) && is_array($config['line'])) {
            imageline($this->img, $config['line'][0], $config['line'][1], $config['line'][2], $config['line'][3], $this->color);
        }

        // Shapes and polylones
        if(isset($config['point']) && is_numeric($config['point'])) {
            imagefilledellipse($this->img, $x, $y, $config['point'], $config['point'], $this->color);
        }

        if(isset($config['font'])) {
            $font = $config['font'];
            if(substr($config['font'], 0, 1)=='/' && file_exists($config['font'])) $this->font=$config['font'];
            else if(file_exists(TDZ_ROOT.'/src/fonts/'.$config['font'])) $this->font=$config['font'];
        }
        if(isset($config['font-size'])) {
            $this->fontSize = (float)$config['font-size'];
        }
        if(isset($config['text-align'])) {
            $this->textAlign = ($config['text-align']=='right')?('right'):('left');
        }

        // Text
        if(isset($config['text'])) {
            $fontfile = null;
            if($this->font) {
                if(file_exists($this->font)) $fontfile = $this->font;
                else if(file_exists(TDZ_ROOT.'/src/fonts/'.$this->font)) $fontfile = TDZ_ROOT.'/src/fonts/'.$this->font;
            }
            $tx=$x;
            if($this->textAlign=='right') {
                $box= imagettfbbox ($this->fontSize, 0, $fontfile, $config['text']);
                $tx-=$box[2];
            }
            imagettftext($this->img, $this->fontSize, 0, $tx, $y , $this->color , $fontfile, $config['text']);
        }

        if(isset($config['add']) && is_array($config['add'])) {
            foreach($config['add'] as $cfg) {
                $this->resetSettings();
                $this->addSettings($cfg);
            }
        }


        //tdz::debug(__METHOD__.', '.__LINE__, "canvas: $x, $y, $width, $height, $r", "src: $srcX, $srcY, $srcWidth, $srcHeight, $sr", $this);

    }

    public function setColor($color)
    {
        $r=false;
        if ($color[0] == '#')
            $color = substr($color, 1);
        if (strlen($color) == 8) // alpha blending
            $r = imagecolorallocatealpha($this->img, hexdec(substr($color,0,2)), hexdec(substr($color,2,2)), hexdec(substr($color,4,2)), hexdec(substr($color,6,2))*0.5);
        if (strlen($color) == 6)
            $r = imagecolorallocate($this->img, hexdec(substr($color,0,2)), hexdec(substr($color,2,2)), hexdec(substr($color,4,2)));
        else if (strlen($color) == 3)
            $r = imagecolorallocate($this->img, hexdec($color[0].$color[0]), hexdec($color[1].$color[1]), hexdec($color[2].$color[2]));
        return $r;
    }

    public function render() 
    {
        if(!is_null($this->img)) {
            // Write the resized image to the cache
            //$outputFunction($dst, $cache, $quality);
            // Put the data of the resized image into a variable
            ob_start();
            $fn='image'.$this->type;
            $quality=($this->type=='png')?((int)($this->quality*0.09)):($this->quality);
            $fn($this->img, null, $quality);
            $this->data = ob_get_contents();
            ob_end_clean();
            // Clean up the memory
            ImageDestroy($this->img);
            $this->img=null;

            if(!is_null($this->originalMemoryLimit)) {
                ini_set('memory_limit', $this->originalMemoryLimit);
                $this->originalMemoryLimit=null;
            }
        }
        return $this->data;
    }

    public function output($exit=true) {
        return tdz::output($this->render(), 'image/'.$this->type, $exit);
    }

    public function resetSettings()
    {
        foreach(self::$reset as $vn=>$vv) {
            if(!is_null($this->$vn)) {
                if($vv===false) $this->$vn=null;
                else $this->$vn=$vv;
            }
        }
    }

    public static function create($src=null, $config=array())
    {
        if(is_array($src)) {
            $config=$src;
        } else if(!is_null($src)) {
            $config['source']=$src;
        }
        return new Tecnodesign_Image($config);
    }

    public static function preview($src=null, $config=array())
    {
        $img=self::create($src, $config);
        return $img->render();
    }

    public function setSource($src)
    {
        if(!is_null($src)) {
            $src = self::fileSrc($src);
            if($src) {
                $prop = GetImageSize($src);
                if($prop) {
                    $this->srcType=str_replace(array('image/', 'x-'), '', $prop['mime']);
                    $this->srcWidth=$prop[0];
                    $this->srcHeight=$prop[1];
                    $this->srcRGB=(!isset($prop['channels']) || $prop['channels']==3);
                    $this->srcX=0;
                    $this->srcY=0;
                    if(function_exists($fn='imagecreatefrom'.$this->srcType)) {
                        $this->src=$fn($src);
                    }
                }
            }
        }

    }

    public static $checkSrc=array('document-root');
    public static function fileSrc($src)
    {
        if(!file_exists($src)) {
            $o=$src;
            $src=false;
            $cfg=tdz::getApp()->tecnodesign;
            foreach(self::$checkSrc as $k) {
                if(isset($cfg[$k]) && file_exists($cfg[$k].'/'.$o)) {
                    $src = $cfg[$k].'/'.$o;
                    break;
                }
            }
        }
        return $src;
    }

    public static function base64Data($img)
    {
        $r = null;
        if(!$img) {
            return;
        } else if(strpos($img, ',')) {
            $r = array();
            foreach(preg_split('/^\[|\s*\,\s*|\]$/', $img, null, PREG_SPLIT_NO_EMPTY) as $i) {
                if($v = static::base64Data($i)) {
                    $r[] = $v;
                }
            }
        } else if($img && strpos($img, '|')) {
            $fpart = explode('|', $img);
            if($fpart && file_exists($f=tdz::uploadDir().'/'.preg_replace('/[^a-z0-9A-Z\.\-\_]/', '', $fpart[0]))) {
                $format = tdz::fileFormat($f);
                $r = 'data:'.(($format)?($format.';'):('')).'base64,'.base64_encode(file_get_contents($f));
            }
        }
        return $r;
    }
}
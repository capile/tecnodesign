<?php
/**
 * Tecnodesign Markdown
 *
 * Markdown syntax extensions based on Parsedown
 * PHP version 5.4
 *
 * @category  Markdown
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2015 Tecnodesign
 * @link      https://tecnodz.com
 */

/*
if(!class_exists('ParsedownExtra')) {
    Tecnodesign_App_Install::dep('Parsedown');
    Tecnodesign_App_Install::dep('ParsedownExtra');
}
*/

class Tecnodesign_Markdown extends ParsedownExtra
{
    public static 
        $htmlMarkup=true,
        $allBreaksEnabled=false,
        $syntaxHighlight='pygmentize',//'geshi',
        $cacheSyntaxHighlight=3600,
        $index=array(),
        $indexElements=array(),
        $indexPrefix='',
        $base='';
    protected static $R;

    function __construct()
    {
        $this->BlockTypes['!'][] = 'App';
        $this->InlineTypes['!'][]= 'App';
        //$this->inlineMarkerList .= '#';
        $this->inlineMarkup = !static::$htmlMarkup;
        if(static::$allBreaksEnabled) $this->breaksEnabled = true;
        parent::__construct();
    }

    protected function blockFencedCode($Line)
    {
        if($Block=parent::blockFencedCode($Line)) {
            if(isset($Block['element']['element']['attributes']['class'])) {
                $Block['highlight'] = true;
                $Block['language'] = substr($Block['element']['element']['attributes']['class'], 9);
            }
            return $Block;
        }
    }

    protected function blockFencedCodeComplete($Block)
    {
        if(!static::$syntaxHighlight || !isset($Block['highlight'])) return parent::blockFencedCodeComplete($Block);

        if(static::$cacheSyntaxHighlight && ($k='hlite'.md5($Block['element']['element']['text'])) && ($text=Tecnodesign_Cache::get($k, static::$cacheSyntaxHighlight))) {
            $Block['element']['element']['rawHtml'] = $text;
            unset($Block['element']['element']['text']);
            if(is_string($Block['highlight'])) {
                $Block['element']['element']['attributes']['class'] .= ' line';
            }
            $Block['element']['element']['attributes']['class'] .= ' highlight';
            return $Block;
        }

        $lang = (isset($Block['language']))?(strtolower($Block['language'])):(null);
        if(strpos($lang, ' ')) $lang = substr($lang, 0, strpos($lang, ' '));

        if(static::$syntaxHighlight=='geshi') {
            if(file_exists($f=TDZ_ROOT.'/src/geshi/src/geshi.php')) require_once $f;
            if($lang && !file_exists(TDZ_ROOT.'/src/geshi/src/geshi/'.$lang.'.php')) $lang='text';
            if($lang) {
                static $G;
                if(is_null($G)) {
                    
                    $G = new GeSHi($Block['element']['element']['text'], $lang);
                    $G->enable_classes();
                    $G->set_header_type(GESHI_HEADER_NONE);
                    $G->enable_line_numbers(GESHI_NORMAL_LINE_NUMBERS);
                    $G->enable_keyword_links(false);
                } else {
                    $G->set_source($Block['element']['element']['text']);
                    $G->set_language($lang);
                }
                if(is_string($Block['highlight'])) {
                    $G->highlight_lines_extra(preg_split('/[^0-9]+/', $Block['highlight'], null, PREG_SPLIT_NO_EMPTY));
                }
                unset($f);
                $text = $G->parse_code();
            } else {
                $text = $Block['element']['element']['text'];
            }

        } else if(static::$syntaxHighlight=='pygmentize') {
            // find out if the language is available
            if($lang) {
                $lang = strtolower($lang);
                static $L;
                if(is_null($L)) {
                    $L=Tecnodesign_Cache::get('pyg-lexers');
                    if(!$L && !is_array($L)) {
                        $L=array();
                        exec('pygmentize -L lexers | grep "* "', $r);
                        if($r) {
                            foreach($r as $i=>$o) {
                                $o = explode(',',preg_replace('/[\*\:\n\s]/', '', strtolower($o)));
                                if($o) $L += array_flip($o);
                                unset($r[$i], $i, $o);
                            }
                        }
                        unset($r);
                        Tecnodesign_Cache::set('pyg-lexers', $L, 3600);
                    }
                }
                if(isset($L[$lang])) {
                    setlocale(LC_CTYPE, "en_US.UTF-8");
                    $cmd = 'echo '.escapeshellarg($Block['element']['element']['text']).' | pygmentize -O encoding=utf8 -f html -l '.$lang;
                }
            }
            if(!isset($cmd) && $lang!='none') {
                setlocale(LC_CTYPE, "en_US.UTF-8");
                $cmd = 'echo '.escapeshellarg($Block['element']['element']['text']).' | pygmentize -O encoding=utf8 -f html -g';
            }
            if($lang=='none') {
                $r = explode("\n", $Block['element']['element']['text']);
                $err=0;
            } else {
                exec($cmd, $r, $err);
            }
            if($r && $err<1) {
                $text = '<ol>';
                $l = count($r) -1;
                $xtra = array();
                if(is_string($Block['highlight'])) {
                    $xtra=preg_split('/[^0-9]+/', $Block['highlight'], null, PREG_SPLIT_NO_EMPTY);
                }

                foreach($r as $i=>$o) {
                    if($i==0) $o = str_replace('<div class="highlight"><pre>', '', $o);
                    else if($i==$l) $o = str_replace('</pre></div>', '', $o);
                    if($i!=$l || $o) {
                        $cn = (preg_match('/^ +/', $o, $m))?(' in'.strlen($m[0])):('');
                        $licn = ($xtra && in_array($i+1, $xtra))?(' class="xtra"'):('');
                        $text .= '<li'.$licn.'><span class="line'.$cn.'">'.$o."\n".'</span></li>';
                    }
                    unset($r[$i], $i, $o);
                }
                $text .= '</ol>';
            }
            unset($r);
        }
        if(!isset($text)) {
            // command line highlighter?
            $text = $Block['element']['element']['text'];
        } else if(static::$cacheSyntaxHighlight) {
            Tecnodesign_Cache::set($k, $text, static::$cacheSyntaxHighlight);
        }
        $Block['element']['element']['rawHtml'] = $text;
        unset($Block['element']['element']['text']);
        if(is_string($Block['highlight'])) {
            $Block['element']['element']['attributes']['class'] .= ' line';
        }
        $Block['element']['element']['attributes']['class'] .= ' highlight';

        return $Block;
    }


    protected function blockApp($Line)
    {
        if (preg_match('/^!([a-z0-9\-\/]+)[\s\n]*(\(.*\))?[\s\n]*$/i', $Line['text'], $m)) {
            if(is_null(static::$R)) static::$R = tdz::getApp()->tecnodesign['routes'];
            if(isset(static::$R[$rurl='markdown:'.$m[1]]) || (isset(static::$R[$rurl=$m[1]]['markdown']) && static::$R[$rurl]['markdown'])) {
                $r = static::$R[$rurl];
                $r['url']=tdz::scriptName(true);
                //$r['arguments']=$m[2];
                $Block = array(
                    'char' => $Line['text'][0],
                    'route' => $r,
                    'element' => array(
                        'name' => 'div',
                        'text' => array(),
                        'attributes' => array(
                            'class' => 'app-'.$m[1],
                        ),
                    ),
                );
                if(isset($m[2]) && $m[2]) {
                    $Block['element']['text'][] = substr($m[2], 1, strlen($m[2])-2);
                    $Block['complete'] = true;
                }
                return $Block;
            }
        }
    }

    protected function blockAppContinue($Line, $Block)
    {
        if (isset($Block['complete'])) {
            return;
        }
        if (isset($Block['interrupted']) || !trim($Line['text'])) {
            return;
        }

        $Block['element']['text'][] = $Line['body'];

        return $Block;
    }

    protected function blockAppComplete($Block)
    {
        $Block['element']['text'] = implode("\n", $Block['element']['text']);
        $r = $Block['route'];
        if(isset($r['class']) && isset($r['method']) && isset($r['static']) && $r['static']) {
            $c = $r['class'];
            $m = $r['method'];
            $Block['element']['text'] = $c::$m($Block['element']['text']);
        } else {
            $r['arguments']=$Block['element']['text'];
            if($s = tdz::getApp()->runRoute($r)) {
                if($s===true) $s = Tecnodesign_App::response('template');
                $Block['element']['text'] = $s;
            }
        }
        if(!isset($r['envelope']) || !$r['envelope']) {
            return array(
                'markup'=>$Block['element']['text'],
            );
        }

        return $Block;
    }

    protected function inlineApp($Block)
    {
        if (preg_match('/^!([a-z0-9\-\/]+) *(\(.*\))?/i', $Block['text'], $m)) {
            if(is_null(static::$R)) static::$R = tdz::getApp()->tecnodesign['routes'];
            if(isset(static::$R[$rurl='markdown:'.$m[1]]) || (isset(static::$R[$rurl=$m[1]]['markdown']) && static::$R[$rurl]['markdown'])) {
                $r = static::$R[$rurl];
                $r['url']=tdz::scriptName(true);
                $r['arguments']=trim(substr($m[2],1, strlen($m[2])-2));
                if($s = tdz::getApp()->runRoute($r)) {
                    if($s===true) $s = Tecnodesign_App::response('template');
                    if(!isset($r['envelope']) || !$r['envelope']) {
                        return array(
                            'extent' => strlen($m[0]),
                            'markup'=>$s,
                        );
                    }
                    return array(
                        'extent' => strlen($m[0]),
                        'element' => array(
                            'name' => 'span',
                            'text' => $s,
                            'attributes' => array(
                                'class' => 'app-'.$m[1],
                            ),
                        ),
                    ); 

                }
            }
        }
    }


    protected function element(array $Element)
    {
        $index = null;
        if(static::$indexElements && in_array($Element['name'], static::$indexElements)) {
            if(!isset($Element['attributes']['class']) || strpos($Element['attributes']['class'], 'noindex')===false) {
                if(!isset($Element['attributes']['id'])) {
                    if(isset(static::$index[$Element['name']])) {
                        $index = count(static::$index[$Element['name']])+1;
                    } else {
                        $index = 1;
                        static::$index[$Element['name']]=array();
                    }
                    if(static::$indexPrefix) $index = static::$indexPrefix.$Element['name'].'_'.$index;
                    else $index = $Element['name'].'_'.$index;
                    $Element['attributes']['id']=$index;
                } else {
                    $index = $Element['attributes']['id'];
                }
            }
        }

        if(static::$base) {
            $link = false;
            if(isset($Element['attributes']['href'])) $link='href';
            else if(isset($Element['attributes']['src'])) $link='src';

            if($link && !preg_match('#^(https?://|s?ftp://|/|mailto:|\#)#', $Element['attributes'][$link])) {
                $Element['attributes'][$link] = self::$base.$Element['attributes'][$link];
            }
        }

        $r = parent::element($Element);

        if($index) {
            static::$index[$Element['name']][$index] = (isset($Element['attributes']['title']))?($Element['attributes']['title']):(strip_tags($r));
            if(substr($Element['name'], 0, 1)=='h') {
                $l = (int)substr($Element['name'],1);
                if($l>=1) {
                    $level=null;
                    static $levels=array();
                    if(!isset(static::$index['h'])) $levels=array();
                    if(!isset($levels[$l])) {
                        if($levels) {
                            $pl = $l;
                            while($pl-- >1) {
                                if(isset($levels[$pl])) {
                                    // pegar o último elemento
                                    $lk = array_pop(array_keys($levels[$pl]));
                                    $level =& $levels[$pl][$lk];
                                    break;
                                }
                            }
                        }
                    } else {
                        $level =& $levels[$l];
                    }
                    if(is_null($level)) {
                        static::$index['h*'][$index] = array();
                        $levels[$l] =& static::$index['h*'];
                        $levels[$l+1] =& static::$index['h*'][$index];
                    } else {
                        $level[$index] = array();
                        $levels[$l] =& $level;
                        $levels[$l+1] =& $level[$index];
                    }

                    static::$index['h'][]=$index;

                }
            }
        }
        return $r;
    }    
}

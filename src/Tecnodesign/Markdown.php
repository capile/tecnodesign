<?php
/**
 * Tecnodesign Markdown
 *
 * Markdown syntax extensions based on Parsedown
 *
 * This package is inspired and based upon erusev/parsedown-extra
 *
 * PHP version 5.4
 *
 * @category  Markdown
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2019 Tecnodesign
 * @link      https://tecnodz.com
 */

/*
if(!class_exists('ParsedownExtra')) {
    Tecnodesign_App_Install::dep('Parsedown');
    Tecnodesign_App_Install::dep('ParsedownExtra');
}
*/

class Tecnodesign_Markdown extends Parsedown
{
    public static 
        $htmlMarkup=true,
        $allBreaksEnabled=false,
        $syntaxHighlight='pygmentize',//'geshi',
        $cacheSyntaxHighlight=3600,
        $index=array(),
        $indexElements=array('h1', 'h2', 'h3', 'h4'),
        $indexPrefix='',
        $base='';
    protected static $R;

    protected $regexAttribute = '(?:[#.][-\w]+[ ]*)';

    function __construct()
    {
        if (version_compare(parent::version, '1.7.1') < 0) {
            throw new Exception('ParsedownExtra requires a later version of Parsedown');
        }


        $this->BlockTypes['$'][] = 'Variable';
        $this->InlineTypes['$'][] = 'GetVariable';
        $this->inlineMarkerList .= '$';
        $this->BlockTypes['-'][] = 'Section';
        $this->BlockTypes['='][] = 'Figure';
        $this->BlockTypes[':'] []= 'DefinitionList';
        $this->BlockTypes['*'] []= 'Abbreviation';

        # identify footnote definitions before reference definitions
        array_unshift($this->BlockTypes['['], 'Footnote');

        # identify footnote markers before before links
        array_unshift($this->InlineTypes['['], 'FootnoteMarker');

        $this->BlockTypes['!'][] = 'App';
        $this->InlineTypes['!'][]= 'App';
        //$this->inlineMarkerList .= '#';
        $this->inlineMarkup = !static::$htmlMarkup;
        if(static::$allBreaksEnabled) $this->breaksEnabled = true;
    }

    function text($text)
    {
        $level = @error_reporting(E_ALL & ~E_NOTICE);
        $markup = parent::text($text);

        # merge consecutive dl elements
        $markup = preg_replace('/<\/dl>\s+<dl>\s+/', '', $markup);

        # add footnotes
        if (isset($this->DefinitionData['Footnote'])) {
            $Element = $this->buildFootnoteElement();
            $markup .= "\n" . $this->element($Element);
        }
        error_reporting($level);

        return $markup;
    }

    #
    # Blocks
    #

    #
    # Abbreviation

    protected function blockAbbreviation($Line)
    {
        if (preg_match('/^\*\[(.+?)\]:[ ]*(.+?)[ ]*$/', $Line['text'], $matches)) {
            $this->DefinitionData['Abbreviation'][$matches[1]] = $matches[2];
            $Block = array(
                'hidden' => true,
            );

            return $Block;
        }
    }

    #
    # Footnote

    protected function blockFootnote($Line)
    {
        if (preg_match('/^\[\^(.+?)\]:[ ]?(.*)$/', $Line['text'], $matches)) {
            $Block = array(
                'label' => $matches[1],
                'text' => $matches[2],
                'hidden' => true,
            );

            return $Block;
        }
    }

    protected function blockFootnoteContinue($Line, $Block)
    {
        if ($Line['text'][0] === '[' && preg_match('/^\[\^(.+?)\]:/', $Line['text'])) {
            return;
        }

        if (isset($Block['interrupted'])) {
            if ($Line['indent'] >= 4) {
                $Block['text'] .= "\n\n" . $Line['text'];
                return $Block;
            }
        } else {
            $Block['text'] .= "\n" . $Line['text'];
            return $Block;
        }
    }

    protected function blockFootnoteComplete($Block)
    {
        $this->DefinitionData['Footnote'][$Block['label']] = array(
            'text' => $Block['text'],
            'count' => null,
            'number' => null,
        );

        return $Block;
    }

    #
    # Definition List
    protected function blockDefinitionList($Line, $Block)
    {
        if (!isset($Block) || isset($Block['type'])) {
            return;
        }
        $Element = array(
            'name' => 'dl',
            'handler' => 'elements',
            'text' => array(),
        );
        $terms = explode("\n", $Block['element']['text']);
        foreach ($terms as $term)
        {
            $Element['text'] []= array(
                'name' => 'dt',
                'handler' => 'line',
                'text' => $term,
            );
        }
        $Block['element'] = $Element;
        $Block = $this->addDdElement($Line, $Block);
        return $Block;
    }

    protected function blockDefinitionListContinue($Line, array $Block)
    {
        if ($Line['text'][0] === ':')
        {
            $Block = $this->addDdElement($Line, $Block);
            return $Block;
        }
        else
        {
            if (isset($Block['interrupted']) and $Line['indent'] === 0)
            {
                return;
            }
            if (isset($Block['interrupted']))
            {
                $Block['dd']['handler'] = 'text';
                $Block['dd']['text'] .= "\n\n";
                unset($Block['interrupted']);
            }
            $text = substr($Line['body'], min($Line['indent'], 4));
            $Block['dd']['text'] .= "\n" . $text;
            return $Block;
        }
    }

    #
    # Header
    protected function blockHeader($Line)
    {
        $Block = parent::blockHeader($Line);
        if (preg_match('/[ #]*{('.$this->regexAttribute.'+)}[ ]*$/', $Block['element']['text'], $matches, PREG_OFFSET_CAPTURE))
        {
            $attributeString = $matches[1][0];
            $Block['element']['attributes'] = $this->parseAttributeData($attributeString);
            $Block['element']['text'] = substr($Block['element']['text'], 0, $matches[0][1]);
        }
        return $Block;
    }

    #
    # Markup

    protected function blockMarkup($Line)
    {
        if ($this->markupEscaped || $this->safeMode) {
            return;
        }

        if (preg_match('/^<(\w[\w-]*)(?:[ ]*'.$this->regexHtmlAttribute.')*[ ]*(\/)?>/', $Line['text'], $matches)) {
            $element = strtolower($matches[1]);

            if (in_array($element, $this->textLevelElements))  {
                return;
            }

            $Block = array(
                'name' => $matches[1],
                'depth' => 0,
                'element' => array(
                    'rawHtml' => $Line['text'],
                    'autobreak' => true,
                ),
            );

            $length = strlen($matches[0]);
            $remainder = substr($Line['text'], $length);

            if (trim($remainder) === '') {
                if (isset($matches[2]) or in_array($matches[1], $this->voidElements)) {
                    $Block['closed'] = true;
                    $Block['void'] = true;
                }
            } else {
                if (isset($matches[2]) or in_array($matches[1], $this->voidElements)) {
                    return;
                }
                if (preg_match('/<\/'.$matches[1].'>[ ]*$/i', $remainder)) {
                    $Block['closed'] = true;
                }
            }

            return $Block;
        }
    }

    protected function blockMarkupContinue($Line, array $Block)
    {
        if (isset($Block['closed'])) {
            return;
        }

        if (preg_match('/^<'.$Block['name'].'(?:[ ]*'.$this->regexHtmlAttribute.')*[ ]*>/i', $Line['text'])) { //open
            $Block['depth'] ++;
        }

        if (preg_match('/(.*?)<\/'.$Block['name'].'>[ ]*$/i', $Line['text'], $matches)) { //close
            if ($Block['depth'] > 0) {
                $Block['depth'] --;
            } else {
                $Block['closed'] = true;
            }
        }

        if (isset($Block['interrupted'])) {
            $Block['element']['rawHtml'] .= "\n";
            unset($Block['interrupted']);
        }

        $Block['element']['rawHtml'] .= "\n".$Line['body'];

        return $Block;
    }

    protected function blockMarkupComplete($Block)
    {
        if ( ! isset($Block['void'])) {
            $Block['element']['rawHtml'] = $this->processTag($Block['element']['rawHtml']);
        }

        return $Block;
    }

    #
    # Setext
    protected function blockSetextHeader($Line, array $Block = null)
    {
        $Block = parent::blockSetextHeader($Line, $Block);
        if (preg_match('/[ ]*{('.$this->regexAttribute.'+)}[ ]*$/', $Block['element']['text'], $matches, PREG_OFFSET_CAPTURE))
        {
            $attributeString = $matches[1][0];
            $Block['element']['attributes'] = $this->parseAttributeData($attributeString);
            $Block['element']['text'] = substr($Block['element']['text'], 0, $matches[0][1]);
        }
        return $Block;
    }

    #
    # Inline Elements
    #

    #
    # Footnote Marker

    protected function inlineFootnoteMarker($Excerpt)
    {
        if (preg_match('/^\[\^(.+?)\]/', $Excerpt['text'], $matches)) {
            $name = $matches[1];
            if ( !isset($this->DefinitionData['Footnote'][$name])) {
                return;
            }
            $this->DefinitionData['Footnote'][$name]['count'] ++;
            if ( ! isset($this->DefinitionData['Footnote'][$name]['number'])) {
                $this->DefinitionData['Footnote'][$name]['number'] = ++ $this->footnoteCount; # » &
            }
            $Element = array(
                'name' => 'sup',
                'attributes' => array('id' => 'fnref'.$this->DefinitionData['Footnote'][$name]['count'].':'.$name),
                'handler' => 'element',
                'text' => array(
                    'name' => 'a',
                    'attributes' => array('href' => '#fn:'.$name, 'class' => 'footnote-ref'),
                    'text' => $this->DefinitionData['Footnote'][$name]['number'],
                ),                
            );

            return array(
                'extent' => strlen($matches[0]),
                'element' => $Element,
            );
        }
    }

    private $footnoteCount = 0;

    #
    # Link

    protected function inlineLink($Excerpt)
    {
        $Link = parent::inlineLink($Excerpt);
        $remainder = substr($Excerpt['text'], $Link['extent']);
        if (preg_match('/^[ ]*{('.$this->regexAttribute.'+)}/', $remainder, $matches)) {
            $Link['element']['attributes'] += $this->parseAttributeData($matches[1]);
            $Link['extent'] += strlen($matches[0]);
        }

        return $Link;
    }

    #
    # ~
    #

    protected function unmarkedText($text)
    {
        $text = parent::unmarkedText($text);
        if (isset($this->DefinitionData['Abbreviation']))
        {
            foreach ($this->DefinitionData['Abbreviation'] as $abbreviation => $meaning)
            {
                $pattern = '/\b'.preg_quote($abbreviation, '/').'\b/';
                $text = preg_replace($pattern, '<abbr title="'.$meaning.'">'.$abbreviation.'</abbr>', $text);
            }
        }
        return $text;
    }

    #
    # Fenced Code

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
        if(!isset($text) || $text===false) {
            // command line highlighter?
            $text = $Block['element']['element']['text'];
        } else {
            if(static::$cacheSyntaxHighlight) {
                Tecnodesign_Cache::set($k, $text, static::$cacheSyntaxHighlight);
            }
            $Block['element']['element']['rawHtml'] = $text;
            unset($Block['element']['element']['text']);
            if(is_string($Block['highlight'])) {
                $Block['element']['element']['attributes']['class'] .= ' line';
            }
            $Block['element']['element']['attributes']['class'] .= ' highlight';
        }
        return $Block;
    }


    /**
     * Loading applications from Markdown
     */
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
        if(!isset($Element['name'])) {
            if(isset($Element['rawHtml'])) {
                return $Element['rawHtml'];
            } else {
                $Element['name'] = 'span';
            }
        }

        if(static::$indexElements && in_array($Element['name'], static::$indexElements)) {
            if(!isset($Element['attributes']['class']) || strpos($Element['attributes']['class'], 'noindex')===false) {
                if(!isset($Element['attributes']['id'])) {
                    if(isset(static::$index[$Element['name']])) {
                        $index = count(static::$index[$Element['name']])+1;
                    } else {
                        $index = 1;
                        static::$index[$Element['name']]=array();
                    }

                    if(isset($Element['text']) && substr($Element['name'], 0, 1)==='h') {
                        $indexTxt = static::$indexPrefix.tdz::slug(strip_tags($this->text($Element['text'])), '_-', true);
                        if(isset(static::$index[$Element['name']][$index])) {
                            $indexTxt .= '_'.$index;
                        }
                        $index = $indexTxt;
                        unset($indexTxt);
                    } else {
                        $index = static::$indexPrefix.$Element['name'].'_'.$index;
                    }
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

    #
    # Util Methods
    #

    protected function addDdElement(array $Line, array $Block)
    {
        $text = substr($Line['text'], 1);
        $text = trim($text);
        unset($Block['dd']);
        $Block['dd'] = array(
            'name' => 'dd',
            'handler' => 'line',
            'text' => $text,
        );
        if (isset($Block['interrupted']))
        {
            $Block['dd']['handler'] = 'text';
            unset($Block['interrupted']);
        }
        $Block['element']['text'] []= & $Block['dd'];
        return $Block;
    }


    protected function buildFootnoteElement()
    {
        $Element = array(
            'name' => 'div',
            'attributes' => array('class' => 'footnotes'),
            'handler' => 'elements',
            'text' => array(
                array(
                    'name' => 'hr',
                ),
                array(
                    'name' => 'ol',
                    'handler' => 'elements',
                    'text' => array(),
                ),
            ),
        );
        uasort($this->DefinitionData['Footnote'], 'self::sortFootnotes');
        foreach ($this->DefinitionData['Footnote'] as $definitionId => $DefinitionData)
        {
            if ( ! isset($DefinitionData['number'])) {
                continue;
            }
            $text = $DefinitionData['text'];
            $text = parent::text($text);
            $numbers = range(1, $DefinitionData['count']);
            $backLinksMarkup = '';
            foreach ($numbers as $number)
            {
                $backLinksMarkup .= ' <a href="#fnref'.$number.':'.$definitionId.'" rev="footnote" class="footnote-backref">&#8617;</a>';
            }
            $backLinksMarkup = substr($backLinksMarkup, 1);
            if (substr($text, - 4) === '</p>')
            {
                $backLinksMarkup = '&#160;'.$backLinksMarkup;
                $text = substr_replace($text, $backLinksMarkup.'</p>', - 4);
            }
            else
            {
                $text .= "\n".'<p>'.$backLinksMarkup.'</p>';
            }
            $Element['text'][1]['text'] []= array(
                'name' => 'li',
                'attributes' => array('id' => 'fn:'.$definitionId),
                'handler'=>'text',
                'text' => "\n".$text."\n",
            );
        }
        return $Element;
    }
    
    protected $variables=array();
    protected function inlineGetVariable($Excerpt)
    {
        if (preg_match('/^\$([a-z_]+)/', $Excerpt['text'], $m) && isset($this->variables[$m[1]])) {
            return array(
                'extent' => strlen($m[0]),
                'markup' => $this->variables[$m[1]],
            );
        }
    }

    protected function blockVariable($Line)
    {
        if (preg_match('/^\$([a-z_]+)=\{(.*)/', $Line['text'], $m)) {
            $Block = array(
                'id' => $m[1],
                'markup' => $m[2],
            );
            return $Block;
        }
    }

    protected function blockVariableContinue($Line, $Block)
    {
        if (isset($Block['complete'])) return;
        else if (isset($Block['closed'])) return;

        if (isset($Block['interrupted'])) {
            unset($Block['interrupted']);
        }

        if (substr($Line['text'], 0, 1)=='}') {
            $Block['complete'] = true;
            return $Block;
        }
        $Block['markup'] .= "\n".$Line['body'];

        return $Block;
    }

    protected function blockVariableComplete($Block)
    {
        if(isset($Block['id'])) {
            $this->variables[$Block['id']] = $this->text($Block['markup']);
            $Block['markup']='';
        }
        return $Block;
    }

    protected function blockQuote($Line)
    {
        if (preg_match('/^> ?(\{'.$this->regexAttribute.'+\})? ?(.*)?/', $Line['text'], $m)) {
            $Block = array(
                'element' => array(
                    'name' => 'blockquote',
                    'handler' => 'lines',
                    'text' => (array) $m[2],
                ),
            );
            if(isset($m[1]) && $m[1]) {
                $Block['element']['name'] = 'div';
                $Block['element']['attributes']=$this->parseAttributeData(substr($m[1],1,strlen($m[1])-2));
            }


            return $Block;
        }
    }

    protected function blockQuoteContinue($Line, array $Block)
    {
        if ($Line['body'][0] === '>' and preg_match('/^>[ ]?(.*)/', $Line['body'], $matches))
        {
            if(substr($matches[1], 0, 1)=='{' || substr($matches[1], 0, 1)=='(') return;
            if (isset($Block['interrupted'])) {
                $Block['element']['text'] []= '';
                unset($Block['interrupted']);
            }
            $Block['element']['text'] []= $matches[1];

            return $Block;
        }

        if ( !isset($Block['interrupted'])) {
            $Block['element']['text'] []= $Line['body'];
            return $Block;
        }
    }

    protected function blockSection($Line, $Block)
    {
        if (preg_match('/^'.$Line['text'][0].'{3,} *(\{'.$this->regexAttribute.'+\})? *$/', $Line['text'], $m)) {
            $Block = array(
                'char' => $Line['text'][0],
                'element' => array(
                    'name' => 'section',
                    'handler'=>'text',
                    'text' => '',
                ),
            );

            if(isset($m[1])) {
                $Block['element']['attributes']=$this->parseAttributeData(substr($m[1],1,strlen($m[1])-2));
            }
            unset($m);

            return $Block;
        }
    }

    protected function blockSectionContinue($Line, $Block)
    {
        if (isset($Block['complete'])) return;

        if (isset($Block['interrupted'])) {
            unset($Block['interrupted']);
        }

        if (preg_match('/^'.$Block['char'].'{3,} *$/', $Line['text'])) {
            $Block['complete'] = true;
            return $Block;
        }
        $Block['element']['text'] .= "\n".$Line['body'];

        return $Block;
    }

    protected function blockSectionComplete($Block)
    {
        $Block['complete'] = true;
        return $Block;
    }

    /**
     * Implement: https://github.com/egil/php-markdown-extra-extended
     */
    protected function blockFigure($Line, $Block)
    {
        if (preg_match('/^'.$Line['text'][0].'{3,} *(\[.*\])? *(\{'.$this->regexAttribute.'+\})? *$/', $Line['text'], $m)) {
            $Block = array(
                'char' => $Line['text'][0],
                'element' => array(
                    'name' => 'figure',
                    'handler'=>'line',
                    'text' => '',
                ),
            );

            if (isset($m[1])) {
                $Block['element']['caption']=substr($m[1],1,strlen($m[1])-2);
            }

            if(isset($m[2])) {
                $Block['element']['attributes']=$this->parseAttributeData(substr($m[2],1,strlen($m[2])-2));
            }
            unset($m);

            return $Block;
        }
    }

    protected function blockFigureContinue($Line, $Block)
    {
        if (isset($Block['complete'])) return;

        if (isset($Block['interrupted'])) {
            unset($Block['interrupted']);
        }

        if (preg_match('/^'.$Block['char'].'{3,} *(\[.*\])? *(\{'.$this->regexAttribute.'+\})? *$/', $Line['text'], $m)) {
            if (isset($m[1])) {
                $Block['element']['caption']=substr($m[1],1,strlen($m[1])-2);
            }
            if(isset($m[2])) {
                $Block['element']['attributes']=$this->parseAttributeData(substr($m[2],1,strlen($m[2])-2));
            }
            unset($m);
            $Block['complete'] = true;
            return $Block;
        }
        $Block['element']['text'] .= "\n".$Line['body'];

        return $Block;
    }

    protected function blockFigureComplete($Block)
    {
        if(isset($Block['element']['caption'])) {
            $line = $this->line($Block['element']['caption']);
            $Block['element']['handler']='multiple';
            $Block['element']['text'] = array(
                $Block['element']['text'],
                array(
                    'name'=>'figcaption',
                    'text'=>$line,
                ),
            );
            $Block['element']['attributes']['title']=strip_tags($line);
            unset($Block['element']['caption']);
        }
        return $Block;
    }

    protected function multiple($a)
    {
        if(isset($a['element'])) return $this->multiple(array($a));
        $s = '';
        foreach($a as $i=>$Block) {
            if(is_string($Block)) {
                if(strpos($Block, "\n")!==false) $s.= $this->text($Block);
                else $s.= $this->line($Block);
            } else if(isset($Block['handler'])) {
                $h = $Block['handler'];
                $s .= $this->$h($Block);
            } else if(isset($Block['name'])) {
                $s .= $this->element($Block);
            }
        }
        return $s;
    }

    # ~

    protected function parseAttributeData($attributeString)
    {
        $Data = array();
        $attributes = preg_split('/[ ]+/', $attributeString, - 1, PREG_SPLIT_NO_EMPTY);
        foreach ($attributes as $attribute) {
            if ($attribute[0] === '#') {
                $Data['id'] = substr($attribute, 1);
            } else {
                $classes []= substr($attribute, 1);
            }
        }
        if (isset($classes)) {
            $Data['class'] = implode(' ', $classes);
        }

        return $Data;
    }

    # ~

    protected function processTag($elementMarkup) # recursive
    {
        # http://stackoverflow.com/q/1148928/200145
        libxml_use_internal_errors(true);

        $DOMDocument = new DOMDocument;

        # http://stackoverflow.com/q/11309194/200145
        $elementMarkup = mb_convert_encoding($elementMarkup, 'HTML-ENTITIES', 'UTF-8');

        # http://stackoverflow.com/q/4879946/200145
        $DOMDocument->loadHTML($elementMarkup);
        $DOMDocument->removeChild($DOMDocument->doctype);
        $DOMDocument->replaceChild($DOMDocument->firstChild->firstChild->firstChild, $DOMDocument->firstChild);

        $elementText = '';

        if ($DOMDocument->documentElement->getAttribute('markdown') === '1') {
            foreach ($DOMDocument->documentElement->childNodes as $Node) {
                $elementText .= $DOMDocument->saveHTML($Node);
            }
            $DOMDocument->documentElement->removeAttribute('markdown');
            $elementText = "\n".$this->text($elementText)."\n";
        } else {
            foreach ($DOMDocument->documentElement->childNodes as $Node) {
                $nodeMarkup = $DOMDocument->saveHTML($Node);

                if ($Node instanceof DOMElement and ! in_array($Node->nodeName, $this->textLevelElements)) {
                    $elementText .= $this->processTag($nodeMarkup);
                } else {
                    $elementText .= $nodeMarkup;
                }
            }
        }

        # because we don't want for markup to get encoded
        $DOMDocument->documentElement->nodeValue = 'placeholder\x1A';
        $markup = $DOMDocument->saveHTML($DOMDocument->documentElement);
        $markup = str_replace('placeholder\x1A', $elementText, $markup);

        return $markup;
    }

    # ~

    protected function sortFootnotes($A, $B) # callback
    {
        return $A['number'] - $B['number'];
    }
}

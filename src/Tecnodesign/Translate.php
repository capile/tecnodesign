<?php
/**
 * Tecnodesign Translation
 *
 * Automatic translation methods.
 *
 * PHP version 5.3
 *
 * @category  Translation
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @version   SVN: $Id: Translate.php 1184 2013-02-20 15:40:12Z capile $
 * @link      http://tecnodz.com/
 */

/**
 * Tecnodesign Translation
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
class Tecnodesign_Translate
{
    public static $method='bing', $apiKey=null, $clientId=null, $sourceLanguage='en';
    protected static $_t=null;
    protected $_from='en', $_lang='en', $_table=null, $_keys=array();

    public function __construct($language=null)
    {
        if(is_null($language)) {
            $language = tdz::$lang;
        }
        $this->_lang = $language;
        $this->_from = self::$sourceLanguage;
    }
    
    /**
     * Translator shortcut
     * 
     * @param mixed  $message message or array of messages to be translated
     * @param string $table   translation file to be used
     * @param string $to      destination language, defaults to tdz::$lang
     * @param string $from    original language, defaults to 'en'
     */
    public static function message($message, $table=null, $to=null, $from=null)
    {
        if(is_null($to)) {
            $to = tdz::$lang;
        }
        if($to==self::$sourceLanguage)
            return $message;
        
        if(!isset(self::$_t[$to])) {
            if(is_null(self::$_t)) {
                self::$_t = new ArrayObject;
            }
            if(!isset(self::$_t[$to])) {
                self::$_t[$to] = new Tecnodesign_Translate($to);
            }
        }
        return self::$_t[$to]->getMessage($message, $table);
    }
    
    public function getMessage($message, $table=null)
    {
        if(is_array($message)) {
            foreach ($message as $mi=>$mv) {
                if(!$mv) {
                    $message[$mi]=$mv;
                } else {
                    $message[$mi]=$this->getMessage($mv, $table);
                }
            }
            return $message;
        } else if(!$message) {
            return $message;
        } else if(!is_string($message)) {
            $message = (string) $message;
        }
        if(is_null($table)) {
            $table = 'default';
        }
        if (is_null($this->_table)) {
            $this->_table = array();
        }
        if(!isset($this->_table[$table][$message])) {
            if(!isset($this->_table[$table])) {
                $yml = TDZ_VAR.'/translate/'.$this->_lang.'/'.$table.'.yml';
                if(!file_exists($yml)) {
                    tdz::save($yml, '--- automatic translation index, please update :)', true);
                }
                $this->_table[$table] = Tecnodesign_Yaml::load($yml);
                if(!is_array($this->_table[$table])) $this->_table[$table] = array();
            }
            if(!isset($this->_table[$table][$message])) {
                $yml = TDZ_VAR.'/translate/'.$this->_lang.'/'.$table.'.yml';
                $text = $message;
                if($this->_from!=$this->_lang){
                    $m = self::$method.'Translate';
                    try{
                        $text = self::$m($this->_from, $this->_lang, $text);
                    } catch(Exception $e) {
                        tdz::log($e->getMessage());
                    }
                }
                $this->_table[$table][$message]=$text;
                Tecnodesign_Yaml::append($yml, array($message=>$text), 0);
            }
        }
        return $this->_table[$table][$message];
    }
    
    public static function bingTranslate($from, $to, $text)
    {
        // replace strings that need to be replaced for an uncommon character
        $appid = self::$apiKey;
        if(!$appid) {
            throw new Tecnodesign_Exception('Translation application needs a valid API key.');
        }
        $utext = urlencode(str_replace('%s', '§', $text));
        $resp = file_get_contents("http://api.microsofttranslator.com/V2/Ajax.svc/GetTranslations?from={$from}&to={$to}&appId={$appid}&maxTranslations=1&text={$utext}");
        tdz::log(__METHOD__.', '.__LINE__, "http://api.microsofttranslator.com/V2/Ajax.svc/GetTranslations?from={$from}&to={$to}&appId={$appid}&maxTranslations=1&text={$utext}", $resp);
        $json = json_decode(substr($resp,3),true);
        if(!$json) {
            throw new Tecnodesign_Exception('Could not translate message. Results are: '.$resp);
        }
        $t = $json['Translations'][0]['TranslatedText'];
        $t = str_replace('§', '%s', html_entity_decode($t));
        return $t;
    }

    public static function microsoftTranslate($from, $to, $text)
    {
        // replace strings that need to be replaced for an uncommon character
        $appid = self::$apiKey;
        if(!$appid) {
            throw new Tecnodesign_Exception('Translation application needs a valid API key.');
        }
        $appid = 'Bearer+'.urlencode(self::microsoftToken());
        $utext = urlencode(str_replace('%s', '§', $text));
        $resp = file_get_contents("http://api.microsofttranslator.com/V2/Ajax.svc/Translate?from={$from}&to={$to}&appId={$appid}&text={$utext}");
        $json = json_decode(substr($resp,3),true);
        if(!$json) {
            throw new Tecnodesign_Exception('Could not translate message. Results are: '.$resp);
        }
        $t = $json;
        $t = str_replace('§', '%s', html_entity_decode($t));
        return $t;
    }
    
    public static function microsoftToken()
    {
        $tk = Tecnodesign_Cache::get('microsoft-translate');
        if(!$tk) {
            $url = 'https://datamarket.accesscontrol.windows.net/v2/OAuth2-13/';
            $curl = curl_init($url);
            
            $req = http_build_query(array(
                'client_id'=>self::$clientId,
                'client_secret'=>self::$apiKey,
                'scope'=>'http://api.microsofttranslator.com',
                'grant_type'=>'client_credentials',
            ));
             
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $req);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $res = curl_exec($curl);
            if($res) {
                $res = json_decode($res, true);
                $tk = $res['access_token'];
                $expires = (int)$res['expires_in'];
                Tecnodesign_Cache::set('microsoft-translate', $tk, $expires);
            }
            curl_close($curl);
        }
        return $tk;
    }


    
    public static function googleTranslate($from, $to, $text)
    {
        // replace strings that need to be replaced for an uncommon character
        $text = str_replace('%s', '§', $text);
        $resp = file_get_contents('https://ajax.googleapis.com/ajax/services/language/translate?v=1.0&q=' . urlencode($text) . '&langpair=' . $from . '|' . $to);
        $json = json_decode($resp);
        $t = $json->responseData->translatedText;
        $t = str_replace('§', '%s', html_entity_decode($t));
        return $t;
    }
}

/*
 

 *  */

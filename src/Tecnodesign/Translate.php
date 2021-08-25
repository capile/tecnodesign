<?php
/**
 * Tecnodesign Translation
 * 
 * Automatic translation methods.
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
class Tecnodesign_Translate
{
    public static $method=null, $apiKey=null, $clientId=null, $sourceLanguage='en', $forceTranslation, $writeUntranslated;
    protected static $_t=null;
    protected $_from='en', $_lang='en', $_table=[], $_keys=[];

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

        if(!isset(self::$_t[$to])) {
            if(is_null(self::$_t)) {
                self::$_t = [];
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
        if(!isset($this->_table[$table][$message])) {
            if(!isset($this->_table[$table]) || !isset($this->_table[$table][$message])) {
                $l = preg_replace('/\-.*/', '', $this->_lang);

                if(
                    file_exists($yml=TDZ_VAR.'/translate/'.$this->_lang.'/'.$table.'.yml') ||
                    file_exists($yml=TDZ_VAR.'/translate/'.$l.'/'.$table.'.yml') ||
                    file_exists($yml=TDZ_VAR.'/translate/'.$table.'.'.$this->_lang.'.yml') ||
                    file_exists($yml=TDZ_VAR.'/translate/'.$table.'.'.$l.'.yml') ||
                    file_exists($yml=TDZ_VAR.'/studio/'.$table.'.'.$l.'.yml') ||
                    file_exists($yml=TDZ_ROOT.'/data/translate/'.$this->_lang.'/'.$table.'.yml') ||
                    file_exists($yml=TDZ_ROOT.'/data/translate/'.$l.'/'.$table.'.yml') ||
                    file_exists($yml=TDZ_ROOT.'/data/translate/'.$table.'.'.$this->_lang.'.yml') ||
                    file_exists($yml=TDZ_ROOT.'/data/translate/'.$table.'.'.$l.'.yml') || 
                    (($yml=TDZ_VAR.'/translate/'.$this->_lang.'/'.$table.'.yml') && false)
                ) {

                } else if(self::$forceTranslation) {
                    if(!tdz::save($yml, '--- automatic translation index, please update', true)) {
                        $yml = null;
                    }
                } else {
                    if(tdz::$log>1) tdz::log('[DEBUG] Translation table '.$table.' was not found.');
                    $yml = null;
                }
            }
            if($yml && !isset($this->_table[$table])) {
                $this->_table[$table] = Tecnodesign_Yaml::load($yml);
                if(isset($this->_table[$table]['all']) && count($this->_table[$table])===1) {
                    $this->_table[$table] = $this->_table[$table]['all'];
                }
                if(!is_array($this->_table[$table])) $this->_table[$table] = array();
            }
            if(!isset($this->_table[$table][$message])) {
                if(tdz::$log>1) tdz::log('[DEBUG] Translation entry '.$table.'.'.$message.' was not found.');
                $text = $message;
                $w = ($yml && self::$writeUntranslated);
                if($w && self::$forceTranslation && $this->_from!=$this->_lang && self::$method && (self::$apiKey || self::$clientId)){
                    $m = self::$method.'Translate';
                    try{
                        $text = self::$m($this->_from, $this->_lang, $text);
                    } catch(Exception $e) {
                        tdz::log($e->getMessage());
                        $w = false;
                    }
                }
                $this->_table[$table][$message]=$text;
                if($w && substr($yml, 0, strlen(TDZ_VAR))===TDZ_VAR) {
                    Tecnodesign_Yaml::append($yml, array($message=>$text), 0);
                }
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
        $resp = file_get_contents("https://api.microsofttranslator.com/V2/Ajax.svc/GetTranslations?from={$from}&to={$to}&appId={$appid}&maxTranslations=1&text={$utext}");
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
        $resp = file_get_contents("https://api.microsofttranslator.com/V2/Ajax.svc/Translate?from={$from}&to={$to}&appId={$appid}&text={$utext}");
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
                'scope'=>'https://api.microsofttranslator.com',
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

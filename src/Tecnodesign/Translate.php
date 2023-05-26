<?php
/**
 * Tecnodesign Translation
 * 
 * Automatic translation methods.
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   3.0
 */
class Tecnodesign_Translate extends Studio\Translate
{
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

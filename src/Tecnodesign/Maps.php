<?php
/**
 * Tecnodesign Maps
 * 
 * This package implements interfaces to Google Maps API
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   3.0
 */
class Tecnodesign_Maps
{
    public static $timeout=87400, $useMem=false, $apiKey=null;
    
    /**
     * Returns an array with geocoding information about $address
     * 
     * @param mixed $address address or aray with latlng location to use as input
     * @param type $useCache whether the caching scheme might be used to fetch results
     * 
     * @return array geographical information regardind the $address 
     */
    public static function getGeocode($address='', $useCache=true)
    {
        $ckey = array();
        if(!is_array($address)) {
            $address = preg_replace('/\s+/', ' ', trim(strtolower($address)));
            $arg = array('address'=>$address);
        } else if(isset($address['latlng']) && preg_match('/^(-?[0-9]+(\.[0-9]*)?)\,?(-?[0-9]+(\.[0-9]*)?)$/', $address['latlng'], $m)) {
            $lat = $m[1];$lng=$m[3];
            $arg = $address;
            $ckey['latlng'] = 'maps/geocode'.str_pad($lat, 12, '0', STR_PAD_RIGHT).'_'.str_pad($lng, 12, '0', STR_PAD_RIGHT);
        } else if(isset($address['address'])) {
            $arg = $address;
        } else {
            tdz::log('don\'t know what to do, exiting...');
            return false;
        }
        if(isset($arg['address'])) {
            $arg['address'] = str_replace('&', '%26', $arg['address']);
        }
        //$arg = tdz::encodeLatin1($arg);
        $arg['sensor']='false';
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?'.preg_replace('/\s+/', '+', urldecode(http_build_query($arg)));
        $url = str_replace('%2C',',', $url);
        $ckey['url']='maps/geocode'.md5($url);
        if (false && $useCache) {
            $data = Tecnodesign_Cache::get($ckey, self::$timeout, self::$useMem);
            if (is_array($data)) {
                return $data;
            }
        }
        $resp = file_get_contents($url);
        $json = json_decode($resp, true);
        if($json['status']!='OK') {
            tdz::log('Maps: '.$url.' returned '.$json['status']);
            $data = array();
        } else {
            $data = array(
                'lat'=>$json['results'][0]['geometry']['location']['lat'],
                'lng'=>$json['results'][0]['geometry']['location']['lng'],
                'address'=>array('full'=>$json['results'][0]['formatted_address']),
            );
            foreach($json['results'][0]['address_components'] as $a) {
                $k = $a['types'][0];
                $data['address'][$k]=$a['short_name'];
            }
            if(!isset($ckey['lanlng'])) {
                $ckey['latlng'] = 'maps/geocode'.str_pad($data['lat'], 12, '0', STR_PAD_RIGHT).'_'.str_pad($data['lng'], 12, '0', STR_PAD_RIGHT);
            }
        }
        Tecnodesign_Cache::set($ckey, $data, self::$timeout, self::$useMem);
        return $data;
    }
    
    /**
     * Alias to geocode locations for $lat/$lng requests
     * 
     * @param float $lat
     * @param float $lng
     * 
     * @return array geocoordinates 
     */
    public static function getAddress($lat, $lng)
    {
        return self::getGeocode(array('latlng'=>"{$lat},{$lng}"));
    }
    
    /**
     * Calculates the distance between two points $a and $b
     * 
     * Please note: 1km = 0.00899365 rad approximately
     * 
     * @param mixed $a     lat,lng value 
     * @param mixed $b     lat,lng value
     * @param string $unit k, n or m
     * 
     * @return float distance in the given unit
     */
    public static function getDistance($a, $b, $unit='k')
    {
        $err = array();
        if(!is_array($a) && preg_match('/^(-?[0-9]+(\.[0-9]*)?)\,?(-?[0-9]+(\.[0-9]*)?)$/', $a, $m)) {
            $a = array('lat'=>$m[1], 'lng'=>$m[3]);
        } else if(!isset($a['lat']) && isset($a[0])) {
            $a = array('lat'=>$a[0], 'lng'=>$a[1]);
        } else if(!isset($a['lat']) || !isset($a['lng'])) {
            $err[]='Tecnodesign_Maps::distance: Origin must be set as a lat,lng string or array containing these values.';
        }
        if(!is_array($b) && preg_match('/^(-?[0-9]+(\.[0-9]*)?)\,?(-?[0-9]+(\.[0-9]*)?)$/', $b, $m)) {
            $b = array('lat'=>$m[1], 'lng'=>$m[3]);
        } else if(!isset($b['lat']) && isset($b[0])) {
            $b = array('lat'=>$b[0], 'lng'=>$b[1]);
        } else if(!isset($b['lat']) || !isset($b['lng'])) {
            $err[]='Tecnodesign_Maps::distance: Destination must be set as a lat,lng string or array containing these values.';
        }
        if(count($err)>0) {
            tdz::log(implode("\n", $err));
            return false;
        }
        $theta = $a['lng'] - $b['lng']; 
        $dist = sin(deg2rad($a['lat'])) * sin(deg2rad($b['lat'])) +  cos(deg2rad($a['lat'])) * cos(deg2rad($b['lat'])) * cos(deg2rad($theta)); 
        $dist = acos($dist); 
        $dist = rad2deg($dist); 
        $miles = $dist * 60 * 1.1515;
        $unit = strtolower($unit);
        if ($unit == 'k') {
            return ($miles * 1.609344); 
        } else if ($unit == "n") {
            return ($miles * 0.8684);
        } else {
            return $miles;
        }
    }
    // mysql: $qry = "SELECT *,(((acos(sin((".$latitude."*pi()/180)) * sin((`Latitude`*pi()/180))+cos((".$latitude."*pi()/180))  * cos((`Latitude`*pi()/180)) * cos(((".$longitude."- `Longitude`)*pi()/180))))*180/pi())*60*1.1515*1.609344) as distance FROM `MyTable` WHERE distance <= ".$distance."

    
    /**
     * Returns an array with geocoding information about $address
     * 
     * @param mixed $address address or aray with latlng location to use as input
     * @param type $useCache whether the caching scheme might be used to fetch results
     * 
     * @return array geographical information regardind the $address 
     */
    public static function getPlaces($arg=array(), $useCache=true, $key=null, $fullDetails=false)
    {
        if(!is_array($arg)) {
            $arg = self::getGeocode($arg);
        }
        if(!is_array($arg)) {
            tdz::log('Tecnodesign_Maps::getPlaces: invalid params');
            return false;
        }
        if(isset($arg['lat'])) {
            $arg['location']=$arg['lat'].','.$arg['lng'];
        }
        if(isset($arg['types']) && is_array($arg['types'])) {
            $arg['types'] = implode('|', $arg['types']);
        }
        if (!is_null($key)) {
            $arg['key']=$key;
        } else if(isset($arg['key'])) {
        } else if(!is_null(self::$apiKey)) {
            $arg['key']=self::$apiKey;
        }
        if(!isset($arg['language'])) {
            $arg['language']=tdz::$lang;
        }
        $valid = array(
            'location'=>true,
            'radius'=>10000,
            'types'=>false,
            'language'=>false,
            'name'=>false,
            'sensor'=>'false',
            'key'=>true,
        );
        $p=array();
        $err = array();
        foreach ($valid as $k=>$v) {
            if($v===true) {
                if (!isset($arg[$k])) {
                    $err[]='Tecnodesign_Maps::getPlaces: '.$k.' is required';
                    continue;
                }
            }
            if (isset($arg[$k])) {
                $v = $arg[$k];
            }
            if($v) {
                $p[$k]=$v;
            }
        }
        if(count($err)>0) {
            tdz::log(implode("\n", $err));
            return false;
        }
        $url = 'https://maps.googleapis.com/maps/api/place/search/json?'.http_build_query($p);
        $url = str_replace('%2C',',', $url);
        $details = ($fullDetails)?('-full'):('');
        $ckey='maps/places'.$details.md5($url);
        if ($useCache) {
            $data = Tecnodesign_Cache::get($ckey, self::$timeout, self::$useMem);
            if (is_array($data)) {
                return $data;
            }
        }
        $resp = file_get_contents($url);
        $json = json_decode($resp, true);
        if($json['status']!='OK') {
            tdz::log('Maps: '.$url.' returned '.$json['status']);
            $data = array();
        } else {
            $data = array();
            foreach ($json['results'] as $i=>$result) {
                $i = $result['id'];
                $data[$i]=array(
                    'lat'=>$result['geometry']['location']['lat'],
                    'lng'=>$result['geometry']['location']['lng'],
                );
                unset($result['geometry']);
                $data[$i] += $result;
                if ($fullDetails) {
                    $details = self::getDetails($result, self::$timeout*10, $key);
                    if($details) {
                        $data[$i]+=$details;
                    }
                }
            }
        }
        Tecnodesign_Cache::set($ckey, $data, self::$timeout, self::$useMem);
        return $data;
    }    
    
    public static function getDetails($arg, $useCache=864000, $key=null)
    {
        if(!is_array($arg)) {
            $arg=array('reference'=>$arg);
        } else if(is_array($arg)) {
            if(!isset($arg['reference'])) {
                $arg = self::getPlaces($arg);
            }
            if(!is_array($arg) || !isset($arg['reference'])) {
                return false;
            }
        }
        $ckey = false;
        if(isset($arg['id'])) {
            $ckey='maps/details-id-'.$arg['id'];
        }
        if (!is_null($key)) {
            $arg['key']=$key;
        } else if(isset($arg['key'])) {
        } else if(!is_null(self::$apiKey)) {
            $arg['key']=self::$apiKey;
        }
        if(!isset($arg['language'])) {
            $arg['language']=tdz::$lang;
        }
        $valid = array(
            'reference'=>true,
            'language'=>false,
            'sensor'=>'false',
            'key'=>true,
        );
        $p=array();
        $err = array();
        foreach ($valid as $k=>$v) {
            if($v===true) {
                if (!isset($arg[$k])) {
                    $err[]='Tecnodesign_Maps::getPlaces: '.$k.' is required';
                    continue;
                }
            }
            if (isset($arg[$k])) {
                $v = $arg[$k];
            }
            if($v) {
                $p[$k]=$v;
            }
        }
        if(count($err)>0) {
            tdz::log(implode("\n", $err));
            return false;
        }
        $url = 'https://maps.googleapis.com/maps/api/place/details/json?'.http_build_query($p);
        if (!$ckey) {
            $ckey='maps/details-'.md5($url);
        }
        $timeout = ($useCache>1)?($useCache):(self::$timeout);
        if ($useCache) {
            $data = Tecnodesign_Cache::get($ckey, $timeout, self::$useMem);
            if (is_array($data)) {
                return $data;
            }
        }
        $resp = file_get_contents($url);
        $json = json_decode($resp, true);
        if($json['status']!='OK') {
            tdz::log('Maps: '.$url.' returned '.$json['status']);
            $data = array();
        } else {
            $result = $json['result'];
            $data = array(
                'lat'=>$result['geometry']['location']['lat'],
                'lng'=>$result['geometry']['location']['lng'],
            );
            unset($result['geometry']);
            foreach($result['address_components'] as $a) {
                $k = $a['types'][0];
                $data['address'][$k]=$a['short_name'];
            }
            unset($result['address_components']);
            $data += $result;
        }
        Tecnodesign_Cache::set($ckey, $data, $timeout, self::$useMem);
        return $data;
    }
}
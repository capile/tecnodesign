<?php
/**
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.6
 */
namespace Studio\Model;

use Studio\Model as Model;
use Studio\Studio as Studio;

class ContentsDisplay extends Model
{
    public static $schema, $schemaClass='Studio\\Schema\\Model';
    protected $content, $link, $version, $display, $created, $updated, $expired, $Content;

    public function matchUrl($url)
    {
        if($this->link=='*' || $this->link==$url) {
            return true;
        } else {
            $link = preg_replace('#/\*$#', '', $this->link);
            if(strpos($link, '*')===false) {
                // only if matches a folder
                return (substr($url, 0, strlen($link)+1)==$link.'/');
            }

            $link = '@'.str_replace('/\*/', '.*', preg_replace('@[a-z0-9\.\-\_/\*]+@i', '', $link)).'@';
            return preg_match($link, $url);
        }
    }

    public static function choicesContentTypes()
    {
        return Studio::config('content_types');
    }
}
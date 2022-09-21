<?php
/**
 * Slack SCIM API access
 *
 * @category  Query
 * @package   FIRST CMS
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2020 Tecnodesign
 */
use Studio as S;
use Tecnodesign_Cache as Cache;
use Tecnodesign_Query_Api as QueryApi;

class Tecnodesign_Query_Scim extends QueryApi
{
    public static 
        $limit='count',
        $limitCount='200',
        $offset='startIndex',
        $queryPath='/%s',
        $previewPath='/%s/%s',
        $insertPath='/%s',
        $updatePath='/%s',
        $deletePath='/%s',
        $deleteQuery='operation=delete',
        $queryTableName=false,
        $countAttribute='totalResults',
        $dataAttribute='Resources|{$_ResponseProperty}',
        $errorAttribute='Error',
        $saveToModel=true,
        $enableOffset=true
        ;

    public function __construct($s=null)
    {
        parent::__construct($s);

        if(preg_match('@^scim(\+https?)?(://.*)@', $this->_url, $m)) {
            $this->_url = (($m[1]) ?substr($m[1], 1) :'https').$m[2];
        }
        unset($m);
    }
}
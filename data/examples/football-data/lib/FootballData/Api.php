<?php

namespace FootballData;

class Api extends \Tecnodesign_Query_Api
{
    public static
        $dataAttribute='{$tableName}',
        $countAttribute='count',
        $enableOffset=false;
}
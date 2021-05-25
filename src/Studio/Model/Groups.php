<?php

namespace Studio\Model;

class Groups extends \Tecnodesign_Studio_Group
{
    public static $schema;
    protected $id, $name, $priority, $created, $updated, $expired;
}
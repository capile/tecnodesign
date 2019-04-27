<?php
/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(dirname(__DIR__));

require 'vendor/autoload.php';
/**
 * @todo create a decent init to avoid constants creation
 */
require 'tdz.php';


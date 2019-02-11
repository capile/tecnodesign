<?php
/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(dirname(__DIR__));

require 'vendor/autoload.php';

// Gambiarra para funcionar o PHPUnit 6.2+ por que estamos usando o ZF1
class_alias(PHPUnit\Framework\TestCase::class, 'PHPUnit_Framework_TestCase');
class_alias(PHPUnit\Runner\Version::class, 'PHPUnit_Runner_Version');
class_alias(PHPUnit\DbUnit\TestCase::class, 'PHPUnit_Extensions_Database_TestCase');
class_alias(PHPUnit\Framework\ExpectationFailedException::class, 'PHPUnit_Framework_ExpectationFailedException');
class_alias(PHPUnit\Framework\Constraint\Constraint::class, 'PHPUnit_Framework_Constraint');



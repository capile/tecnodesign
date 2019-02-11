<?php
/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(dirname(__DIR__));

/**
 * @todo Criar um init decente
 * O TDZ inicializa um monte de constantes
 */
require 'tdz.php';

// Remove o autoloader do TDZ para ter certeza que está usando o do composer
foreach(spl_autoload_functions() as $function) {
    if ($function[0] === 'tdz') {
        spl_autoload_unregister($function);
    }
}

require 'vendor/autoload.php';

<?php
use Bitrix\Main\Loader;

/**
 * Autoloader
 */
Loader::registerNamespace(
    "App",
    Loader::getDocumentRoot()."/local/php_interface/src"
);

/**
 * Project bootstrap files
 */
foreach( [
             /**
              * Events subscribe
              */
             __DIR__.'/events.php',

             /**
              * Include composer libraries
              */
             __DIR__.'/vendor/autoload.php',

             /**
              * Dependency injection and Autowiring
              */
             __DIR__.'/di.php',

             /**
              * Include old legacy code
              *   constant initiation etc
              */
             __DIR__.'/legacy.php',
         ]
         as $filePath )
{
    if ( file_exists($filePath) )
    {
        require_once($filePath);
    }
}

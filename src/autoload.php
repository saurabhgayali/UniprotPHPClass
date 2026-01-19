<?php
/**
 * UniProt PHP Library Autoloader
 * 
 * Simple PSR-4 autoloader for the library.
 * Include this file once to load all library classes.
 */

spl_autoload_register(function ($class) {
    $prefix = 'UniProtPHP\\';
    
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

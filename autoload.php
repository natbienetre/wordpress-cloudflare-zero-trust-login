<?php
include_once 'vendor/autoload.php';

spl_autoload_register( static function ( $class_name ) {
    $file_name = path_join( path_join( __DIR__, 'classes' ), $class_name . '.php' );

    if ( file_exists( $file_name ) ) {
        require_once $file_name;
    }
} );

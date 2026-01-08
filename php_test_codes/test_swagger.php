<?php

require __DIR__.'/vendor/autoload.php';

use OpenApi\Generator;

// Suppress warnings temporarily
$oldErrorHandler = set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if ($errno === E_USER_WARNING || $errno === E_USER_NOTICE) {
        echo "[WARNING] {$errstr}\n";

        return true;
    }

    return false;
});

try {
    $openapi = Generator::scan([__DIR__.'/app']);
    echo "\nSwagger generation successful!\n";

    // Ensure directory exists
    if (! is_dir(__DIR__.'/storage/api-docs')) {
        mkdir(__DIR__.'/storage/api-docs', 0755, true);
    }

    file_put_contents(__DIR__.'/storage/api-docs/api-docs.json', $openapi->toJson());
    echo "Documentation saved to storage/api-docs/api-docs.json\n";
} catch (\Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
    echo 'File: '.$e->getFile()."\n";
    echo 'Line: '.$e->getLine()."\n";
    echo "\nTrace:\n".$e->getTraceAsString()."\n";
} finally {
    restore_error_handler();
}

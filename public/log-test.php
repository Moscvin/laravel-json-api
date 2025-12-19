<?php
// Log request
file_put_contents("/var/www/html/storage/logs/request.log", 
    date("Y-m-d H:i:s") . " - " . $_SERVER["REQUEST_URI"] . "\n", 
    FILE_APPEND);

// Test Laravel bootstrap
require_once __DIR__ . "/../vendor/autoload.php";
\$app = require_once __DIR__ . "/../bootstrap/app.php";
echo "Laravel loaded\n";

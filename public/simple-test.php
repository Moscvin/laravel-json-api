<?php
echo "Simple PHP Test\n";
echo "Working Directory: " . getcwd() . "\n";
echo "PHP Version: " . phpversion() . "\n";

// Check if we can write to logs
$logFile = "/var/www/html/storage/logs/test.log";
if (file_put_contents($logFile, "Test at " . date("Y-m-d H:i:s") . "\n", FILE_APPEND)) {
    echo "Can write to logs\n";
} else {
    echo "Cannot write to logs\n";
}

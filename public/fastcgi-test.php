<?php
echo "FastCGI Test\n";
echo "SCRIPT_FILENAME: " . ($_SERVER["SCRIPT_FILENAME"] ?? "NOT SET") . "\n";
echo "REQUEST_URI: " . ($_SERVER["REQUEST_URI"] ?? "NOT SET") . "\n";
echo "REQUEST_METHOD: " . ($_SERVER["REQUEST_METHOD"] ?? "NOT SET") . "\n";
echo "CONTENT_TYPE: " . ($_SERVER["CONTENT_TYPE"] ?? "NOT SET") . "\n";

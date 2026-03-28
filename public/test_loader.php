<?php
// Show all errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "Attempting to include autoloader...<br>";

// Include the autoloader
require_once __DIR__ . '/../vendor/autoload.php';

echo "Autoloader included. Attempting to use class...<br>";

// Try to use the class
use App\Test;
echo "<strong>Result:</strong> " . Test::hello();
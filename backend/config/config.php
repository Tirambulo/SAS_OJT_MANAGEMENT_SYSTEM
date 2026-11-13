<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sas_ojt_database');
define('DB_CHARSET', 'utf8mb4');

// Application settings
define('SITE_NAME', 'SAS OJT Management System');
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$backendPathPosition = strpos($scriptName, '/backend/');
$basePath = $backendPathPosition === false
    ? '/'
    : substr($scriptName, 0, $backendPathPosition + 1);
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $scheme . '://' . $host . $basePath);

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
?>
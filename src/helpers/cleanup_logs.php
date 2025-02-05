<?php
$logDirectory = __DIR__ . '/../../logs';
$files = glob($logDirectory . '/activity_errors_*.log');

$now = time();
$expiration = 30 * 24 * 60 * 60; // 30 days in seconds

foreach ($files as $file) {
    if (is_file($file) && ($now - filemtime($file)) > $expiration) {
        unlink($file); // Delete old log file
    }
}
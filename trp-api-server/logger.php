<?php
function logFileAccess($filename) {
    $logFile = __DIR__ . '/access_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $referer = $_SERVER['HTTP_REFERER'] ?? 'direct';
    
    $logEntry = sprintf(
        "[%s] File: %s | IP: %s | URI: %s | Referer: %s | User-Agent: %s\n",
        $timestamp,
        $filename,
        $ip,
        $requestUri,
        $referer,
        substr($userAgent, 0, 100)
    );
    
    // Append to log file
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
?>
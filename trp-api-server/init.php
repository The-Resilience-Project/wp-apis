<?php
chdir(dirname(__FILE__));

// Load Sentry SDK (installed via WordPress/Composer)
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',           // WordPress root vendor
    __DIR__ . '/../../vendor/autoload.php',        // Project root vendor
    __DIR__ . '/../wp-content/vendor/autoload.php', // wp-content vendor
];

$autoloadLoaded = false;
foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        try {
            require_once $autoloadPath;
            $autoloadLoaded = true;
            // Log which autoload was used (for debugging)
            if (defined('DEBUG_SENTRY_INIT')) {
                error_log('Sentry autoload loaded from: ' . $autoloadPath);
            }
        } catch (Exception $e) {
            // Can't use log_exception here as functions aren't loaded yet
            error_log('Sentry autoload error from ' . $autoloadPath . ': ' . $e->getMessage());
        }
        break;
    }
}

// Load Sentry DSN from wp-config.php
if (file_exists(__DIR__ . '/../wp-config.php')) {
    $wp_config_content = file_get_contents(__DIR__ . '/../wp-config.php');
    if (preg_match("/define\(\s*'WP_SENTRY_PHP_DSN'\s*,\s*'([^']+)'/", $wp_config_content, $matches)) {
        if (!defined('WP_SENTRY_PHP_DSN')) {
            define('WP_SENTRY_PHP_DSN', $matches[1]);
        }
    }
}

// Initialize Sentry if available
if (defined('WP_SENTRY_PHP_DSN') && function_exists('\Sentry\init')) {
    try {
        \Sentry\init([
            'dsn' => WP_SENTRY_PHP_DSN,
            'traces_sample_rate' => 1.0,
            'environment' => 'production',
            'enable_logs' => true,
        ]);
    } catch (Exception $e) {
        // Can't use log_exception here as it would cause recursion
        error_log('Sentry initialization failed: ' . $e->getMessage());
    }
}

require_once "config.php";
require_once "lib/class_dhpdo.php";

try{
    $dbh = new dhpdo($local_config);
}catch (Exception $e){
    log_exception($e, ['component' => 'database_init']);
    return 'Error Message: ' .$e->getMessage();
}

require_once "lib/class_dhrest.php";
require_once "lib/class_dhvt.php";

require_once "functions.php";

try{
    $vtod = init_vtod();
}catch (Exception $e){
    log_exception($e, ['component' => 'vtiger_init']);
    return  'Error Message: ' .$e->getMessage();
}

/* Instance init functions */
function init_vtod() {
    global $vtod_config;
    try{
        $vtod = new dhvt($vtod_config["url"]."webservice.php",$vtod_config["username"],$vtod_config["accesskey"]);
        return $vtod;
    }catch (Exception $e){
        log_exception($e, ['component' => 'vtiger_connection']);
        return 'Error Message: ' .$e->getMessage();
    }

}

/**
 * Enhanced logging functions that log to both error_log AND Sentry
 */

/**
 * Log debug information
 * @param string $message The message to log
 * @param array $context Additional context data
 */
function log_debug($message, $context = []) {
    error_log('[DEBUG] ' . $message);

    if (function_exists('\Sentry\captureMessage')) {
        \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($message, $context) {
            if (!empty($context)) {
                $scope->setContext('debug_data', $context);
            }
            \Sentry\captureMessage($message, \Sentry\Severity::debug());
        });
    }
}

/**
 * Log info message
 * @param string $message The message to log
 * @param array $context Additional context data
 */
function log_info($message, $context = []) {
    error_log('[INFO] ' . $message);

    if (function_exists('\Sentry\captureMessage')) {
        \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($message, $context) {
            if (!empty($context)) {
                $scope->setContext('info_data', $context);
            }
            \Sentry\captureMessage($message, \Sentry\Severity::info());
        });
    }
}

/**
 * Log warning message
 * @param string $message The message to log
 * @param array $context Additional context data
 */
function log_warning($message, $context = []) {
    error_log('[WARNING] ' . $message);

    if (function_exists('\Sentry\captureMessage')) {
        \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($message, $context) {
            if (!empty($context)) {
                $scope->setContext('warning_data', $context);
            }
            \Sentry\captureMessage($message, \Sentry\Severity::warning());
        });
    }
}

/**
 * Log error message
 * @param string $message The message to log
 * @param array $context Additional context data
 */
function log_error($message, $context = []) {
    error_log('[ERROR] ' . $message);

    if (function_exists('\Sentry\captureMessage')) {
        \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($message, $context) {
            if (!empty($context)) {
                $scope->setContext('error_data', $context);
            }
            \Sentry\captureMessage($message, \Sentry\Severity::error());
        });
    }
}

/**
 * Log exception to both error_log and Sentry
 * @param Exception $exception The exception to log
 * @param array $context Additional context data
 */
function log_exception($exception, $context = []) {
    error_log('[EXCEPTION] ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());

    if (function_exists('\Sentry\captureException')) {
        \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($exception, $context) {
            if (!empty($context)) {
                $scope->setContext('exception_data', $context);
            }
            \Sentry\captureException($exception);
        });
    }
}

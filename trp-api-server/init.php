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
        error_log('Sentry initialization failed: ' . $e->getMessage());
    }
}

require_once "config.php";
require_once "lib/class_dhpdo.php";

try{
    $dbh = new dhpdo($local_config);
}catch (Exception $e){
    error_log($e->getMessage());
    return 'Error Message: ' .$e->getMessage();
}

require_once "lib/class_dhrest.php";
require_once "lib/class_dhvt.php";

require_once "functions.php";

try{
    $vtod = init_vtod();
}catch (Exception $e){
    error_log($e->getMessage());
    return  'Error Message: ' .$e->getMessage();
}

/* Instance init functions */
function init_vtod() {
    global $vtod_config;
    try{
        $vtod = new dhvt($vtod_config["url"]."webservice.php",$vtod_config["username"],$vtod_config["accesskey"]);
        return $vtod;
    }catch (Exception $e){
        error_log($e->getMessage());
        return 'Error Message: ' .$e->getMessage();
    }

}

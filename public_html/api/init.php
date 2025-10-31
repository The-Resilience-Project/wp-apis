<?php
chdir(dirname(__FILE__));

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

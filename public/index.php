<?php
// Path to the front controller (this file)
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

/*
 *---------------------------------------------------------------
 * BOOTSTRAP THE APPLICATION
 *---------------------------------------------------------------
 * This process sets up the path constants, loads and registers
 * our autoloader, along with Composer's, loads our constants
 * and fires up an environment-specific bootstrapping.
 */

// Ensure the current directory is pointing to the front controller's directory
chdir(__DIR__);

function handleCors(){
    if( !function_exists('getallheaders') ){
        return 'fromCli';
    }
    foreach (getallheaders() as $name => $value) {
        if( strtolower($name)=='origin' && (str_contains($value, 'tezkel') || str_contains($value, 'localhost')) ){
            header("Access-Control-Allow-Origin: $value");
            break;
        }
    }
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, x-sid");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Expose-Headers: x-sid");
    $method = isset($_SERVER['REQUEST_METHOD'])?$_SERVER['REQUEST_METHOD']:'';
    if( $method == "OPTIONS" ) {
        die();
    }
}
handleCors();













// Load our paths config file
// This is the line that might need to be changed, depending on your folder structure.
$pathsConfig = FCPATH . '../app/Config/Paths.php';
// ^^^ Change this if you move your application folder
require realpath($pathsConfig) ?: $pathsConfig;

$paths = new Config\Paths();

// Location of the framework bootstrap file.
$bootstrap = rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'bootstrap.php';
$app       = require realpath($bootstrap) ?: $bootstrap;

/*
 *---------------------------------------------------------------
 * LAUNCH THE APPLICATION
 *---------------------------------------------------------------
 * Now that everything is setup, it's time to actually fire
 * up the engines and make this app do its thang.
 */
$app->run();

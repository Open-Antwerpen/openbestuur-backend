<?php 
$developerMode = 1; //bypasses production database works on dev database

if ($developerMode == 1) {
    ini_set('display_errors', 'On');
    error_reporting(E_ALL | E_STRICT);
    $my_db = "opengov_dev"; //open government developer
}

include_once('functions.php');

define('GOOGLEAPIKEY','YOUR GOOGLE API KEY'); 

$mysqli = new mysqli("HOST", "USER", "PASS", "DBNAME"); // dev db server

if (!$mysqli) {
    $log = "Error: Unable to connect to MySQL." . PHP_EOL;
    $log .= "Debugging errno: " . mysqli_connect_errno() . PHP_EOL;
    $log .= "Debugging error: " . mysqli_connect_error() . PHP_EOL;
} else {
    $log = "Success: A proper connection to MySQL was made! The " . $my_db . " database is great." . PHP_EOL . 
"Host information: " . mysqli_get_host_info($mysqli) . PHP_EOL;
}

writeLog ($log);

?>
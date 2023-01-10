<?php
include_once(__DIR__ . '/bootstrap.inc.php');

const API_LIST = [
    'version'    
];

if(empty($_GET['api']))
    die('__ NO INPUT DETECTED __');
    
$api = $_GET['api'];
if(!in_array($api, API_LIST))
    die('__ NO VALID API DETECTED __, received: <' . $api .'>');
    
switch($api){
    case 'version':
        echo json_encode(Config::ADVISOR);
        break;
    default:
        break;
}

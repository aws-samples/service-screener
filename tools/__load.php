<?php 
include_once(__DIR__ .'/config.php');
include_once(__DIR__ .'/aws-sdk-custom-credential-provider.php');
include_once(__DIR__ .'/arguParser.class.php');
include_once(__DIR__ .'/reporter.class.php');
include_once(__DIR__ .'/feedback.class.php');
include_once(__DIR__ .'/policy.class.php');
include_once(__DIR__ .'/uploader.class.php');

function __pr($o){
    global $DEBUG;
    
    if($DEBUG){
        print_r($o);
        echo PHP_EOL;
    }
}

function __info($o){
    __printStatus("info", $o);
}

function __warn($o){
    __printStatus("\033[1;41m__!! WARNING !!__\033[0m", $o);
}

function __printStatus($status, $o){
    $o = "[$status] " . $o;
    __pr($o);
}
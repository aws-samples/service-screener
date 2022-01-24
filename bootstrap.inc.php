<?php

define('AWS_PHP_SDK_PATH', __DIR__ . '/vendor/autoload.php');
if(!file_exists(AWS_PHP_SDK_PATH)){
    echo "\n!!!!!!!!!!!! \n";
    echo "AWS PHP SDK is not found, please install composer using `sudo yum install composer -y` \n";
    echo "After that, run `composer require aws/aws-sdk-php` \n";
    echo "-----\n";
    echo "If you are still facing issue, please take a look here: https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/getting-started_installation.html\n";
    die();   
}

include_once(__DIR__ . '/vendor/autoload.php');
include_once(__DIR__ . '/constants.inc.php');
include_once(__DIR__ . '/tools/__load.php');
include_once(__DIR__ . '/services/__load.php');
date_default_timezone_set('Asia/Singapore');    ## For Feedback + TTL in dynamodb

global $DEBUG, $CONFIG;
$CONFIG = new Config();


function scanByService($service, $regions, $scanInParallel = true){
    global $CONFIG, $CW;

    if($scanInParallel)
        $pid = pcntl_fork();

    if($scanInParallel && $pid == -1){
        exit("Error forking...\n");   
    }else if(!$scanInParallel || $pid ==0){
        $time_start = microtime(true);
        
        $tempCount = 0;
        $service = explode('::', $service);
        
        $__regions = in_array($service[0], CONFIG::GLOBAL_SERVICES) ? ['GLOBAL'] : $regions;
        
        foreach($__regions as $region){
            $CW = new cloudwatch($region);
            
            $reg = $region;
            if($region == 'GLOBAL')
                $reg =  $regions[0];
            
            $serv = new $service[0]($reg);
            
            if(!empty($service[1]))
                $serv->setRules($service[1]);
            
            $contexts[$service[0]][$region] = $serv->advise();
            $tempCount+= sizeof($contexts[$service[0]][$region]);
            unset($serv);
        }
        
        if(!empty($GLOBALRESOURCES)){
            $contexts[$service[0]]['GLOBAL'] = $GLOBALRESOURCES;   
        }
        
        $time_end = microtime(true);
        $scanned = $CONFIG->get('scanned');
        
        
        file_put_contents(FORK_DIR .'/'.$service[0].'.json', json_encode($contexts[$service[0]]));
        file_put_contents(FORK_DIR .'/'.$service[0].'.stat.json', json_encode($scanned));
        
        $resourceCnt = $scanned['resources'];
        __info("#### ($resourceCnt) <".$service[0]."> completed within ". round(($time_end - $time_start), 3) . "s");
        
        if($scanInParallel)
            exit();
    }
}
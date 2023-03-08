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
        $exceptionCnt = $scanned['exceptions'];
        $rules = $scanned['rules'];
        
        $emsg = "";
        if($exceptionCnt > 0) $emsg = ", \033[1;41mwith $exceptionCnt exception(s)\033[0m";
        
        __info("#### $resourceCnt <".$service[0]."> ($rules scanned) completed within ". round(($time_end - $time_start), 3) . "s$emsg");
        
        if($scanInParallel)
            exit();
    }
}

function generateScreenerOutput($runmode, $contexts, $hasGlobal, $serviceStat, $regions, $uploadToS3, $bucket){
    global $CONFIG;
    $stsInfo = $CONFIG->get('stsInfo');
    if($runmode == 'api-raw'){
        file_put_contents(API_JSON, json_encode($contexts));
    }else{
        $apiResultArray = [];
        if($hasGlobal)
            $regions[] = 'GLOBAL';   
        
        $rawServices = [];
        
        if($runmode == 'report'){
            $params = [];
            foreach($CONFIG->get('__SS_PARAMS') as $key => $val){
                if(!empty($val))
                    $params[] = "--$key $val";
            }
            
            $summary = $CONFIG->get('SCREENER-SUMMARY');
            $excelObj = new ExcelBuilder($stsInfo['Account'], implode(' ', $params));
        }
        
        foreach($contexts as $service => $resultSets){
            $rawServices[] = $service;
            
            $reporter = new reporter($service);
            $reporter->process($resultSets)
                ->getSummary()
                ->getDetails();
            
            if($runmode == 'report'){
                $pageBuilderClass = $service . 'pageBuilder';
                if(!class_exists($pageBuilderClass)){
                    __info($pageBuilderClass . ' class not found, using default pageBuilder');
                    $pageBuilderClass = 'pageBuilder';
                }
                    
                $pb = new $pageBuilderClass($service, $reporter, $serviceStat, $regions);
                $pb->buildPage();
                
                ##Excel
                if(!in_array($service, ['guardduty']))
                    $excelObj->generateWorkSheet($service, $reporter->cardSummary);
            }else{
                $apiResultArray[$service]['summary'] = $reporter->getCard();
                $apiResultArray[$service]['detail'] = $reporter->getDetail();
            }
        }
        ## <serviceFamily>:<region>:<serviceName>:<checks>
        
        ## pageBuilderForDashboard
        if($runmode == 'report'){
            $dashPB = new dashboardPageBuilder('index', [], $serviceStat, $regions);
            $dashPB->buildPage();
            
            ## dashPB will gather summary info, hence rearrange the sequences
            $excelObj->buildSummaryPage($summary);
            $excelObj->__save(HTML_DIR.'/');
        
            exec('cd adminlte; zip -r output.zip html; mv output.zip ../output.zip');
            __info("Pages generated, download \033[1;42moutput.zip\033[0m to view");
            __info("CloudShell user, you may use this path: \033[1;42m~/service-screener/output.zip\033[0m");
            
            if ($uploadToS3) {
                $bucket_region = $regions[0]; // use the first region as the bucket region
                $uploader = new Uploader($bucket_region, $bucket); // returns boolean
            
                if ($uploader) {
                    __info("*** Uploading files to S3 bucket: $bucket (region: $bucket_region)");
                    
                    $uploaded = $uploader->uploadFromFolder(__DIR__ . '/adminlte/html');
                    if ($uploaded) {
                        __info("*** Upload completed ***");
                        __info("You may visit the report at: \033[1;42mhttp://$bucket.s3-website-$bucket_region.amazonaws.com\033[0m");
                    }
                }
            }
        }else{
            #runmode == api-full   
            file_put_contents(API_JSON, json_encode($apiResultArray));
        }
    }
}
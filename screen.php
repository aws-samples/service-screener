<?php 
include_once(__DIR__.'/bootstrap.inc.php');

$__cli_options = ArguParser::Load();

$debugFlag = $__cli_options['debug'];
$feedbackFlag = $__cli_options['feedback'];
$testmode = $__cli_options['test'];
$bucket = $__cli_options['bucket'];
$runmode = $__cli_options['mode'];

$DEBUG = ( in_array($debugFlag, CLI_TRUE_KEYWORD_ARRAY) || $debugFlag === true) ? true : false;
$feedbackFlag = ( in_array($feedbackFlag, CLI_TRUE_KEYWORD_ARRAY) || $feedbackFlag === true) ? true : false;
$testmode = ( in_array($testmode, CLI_TRUE_KEYWORD_ARRAY) || $testmode === true) ? true : false;

$runmode = in_array($runmode, ['api-raw', 'api-full', 'report']) ? $runmode : 'report';

# S3 upload specific variables
$uploadToS3 = Uploader::getConfirmationToUploadToS3($bucket);

$profile = $__cli_options['profile'];
if(!empty($profile)){
    global $PHPSDK_CRED_PROFILE;
    $PHPSDK_CRED_PROFILE = $profile;
}

$__AWS_OPTIONS = [
    'signature_version' => CONFIG::AWS_SDK['signature_version']
];

$CONFIG->set("__AWS_OPTIONS", $__AWS_OPTIONS);

$regions = explode(',', $__cli_options['region']);
$services = explode(',', $__cli_options['services']);

$contexts = [];

$tempConfig = $__AWS_OPTIONS;
$tempConfig['region'] = $regions[0];
CONFIG::setAccountInfo($tempConfig);

$CONFIG->set('scanned', ['resources' => 0, 'rules' => 0, 'exceptions' => 0]);

$serviceStat = [];

global $GLOBALRESOURCES, $CW;
$GLOBALRESOURCES = [];
    
$overallTimeStart = microtime(true);

exec('cd __fork; rm -f *.json; echo > tail.txt');

$scanInParallel = sizeof($services) > 1 ? true : false;
foreach($services as $service){
    ## Scripts move to bootstrap.inc.php
    scanByService($service, $regions, $scanInParallel);
};

if($scanInParallel)
    while(pcntl_waitpid(0, $status) != -1);

$files = scandir(FORK_DIR);
$scanned=[
    'resources' => 0,
    'rules' => 0,
    'exceptions' => 0
];
$hasGlobal = false;
foreach($files as $file){
    if($file[0] == '.' || $file == SESSUID_FILENAME || $file == 'tail.txt' || $file == 'error.txt')
        continue;
    
    $f = explode('.', $file);
    if(sizeof($f) == 2){
        $contexts[$f[0]] = json_decode(file_get_contents(FORK_DIR . '/' . $file), true);
    }else{
        list($cnt, $rules, $exceptions) = array_values(json_decode(file_get_contents(FORK_DIR . '/' .$file), true));
        $serviceStat[$f[0]] = $cnt;
        $scanned['resources'] += $cnt;
        $scanned['rules'] += $rules;
        $scanned['exceptions'] += $exceptions;
        
        if(in_array($f[0], CONFIG::GLOBAL_SERVICES))
            $hasGlobal = true;
    }
}

if($testmode)
    exit("Test mode enable, script halted" . PHP_EOL);

__info("Total Resources scanned: " . number_format($scanned['resources']) . " | No. Rules executed: " . number_format($scanned['rules']));
__info("Time consumed: " .  round(microtime(true) - $overallTimeStart, 3));

## Cleanup
exec('cd '.HTML_FOLDER.'; rm -f *.html; rm -f error.txt');

if(file_exists(FORK_DIR.'/error.txt'))
    exec('cd __fork; mv error.txt '.HTML_DIR.'/');

exec('cd __fork; rm -f *.json');
exec('rm -f output.zip');

## Scripts move to bootstrap.inc.php
generateScreenerOutput($runmode, $contexts, $hasGlobal, $serviceStat, $regions, $uploadToS3, $bucket);

if($feedbackFlag){    
    __info("*** Sending feedback ***");
    feedback::send($rawServices, $regions);
}

exec('cd __fork; rm -f tail.txt');
__info("@ Thank you for using ". Config::ADVISOR['TITLE'] ." @");
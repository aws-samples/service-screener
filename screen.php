<?php 
include_once(__DIR__.'/bootstrap.inc.php');

$__cli_options = ArguParser::Load();

$debugFlag = $__cli_options['debug'];
$feedbackFlag = $__cli_options['feedback'];
$testmode = $__cli_options['test'];
$bucket = $__cli_options['bucket'];

$DEBUG = ( in_array($debugFlag, CLI_TRUE_KEYWORD_ARRAY) || $debugFlag === true) ? true : false;
$feedbackFlag = ( in_array($feedbackFlag, CLI_TRUE_KEYWORD_ARRAY) || $feedbackFlag === true) ? true : false;
$testmode = ( in_array($testmode, CLI_TRUE_KEYWORD_ARRAY) || $testmode === true) ? true : false;

# S3 upload specific variables
$uploadToS3 = false;

if ($bucket) {
    __info("You have specified a 'bucket' parameter, the report will be uploaded to S3.");
    __info("The report will be available through public internet, please ensure you understand the risk of exposing the report to the internet. You will be fully RESPONSIBLE on this data.");
    $confirm = strtolower(readline("Please enter 'y' for yes, 'n' for no, or 'c' to continue without uploading the report to S3 : "));

    do {
        __warn("You have entered an invalid option. Please try again.");
        $confirm = strtolower(readline("Please enter 'y' for yes, 'n' for no, or 'c' to continue without uploading the report to S3 : "));    
    } while(!in_array($confirm, ['y', 'n', 'c']));

    if ($confirm == 'y') {
        $uploadToS3 = true;
    }
    
    if ($confirm == 'n') {
        __info("You have chosen not to upload the report to S3.");
    }

    if ($confirm == 'c') {
        __info("You have chosen not to upload the report to S3. Continuing...");
    }
}

$env = $__cli_options['env'];
if($env == 'c9'){
    global $PHPSDK_CRED_PROVIDER;
    $c9Credential = new Aws\Credentials\CustomC9CredentialProvider();
    $PHPSDK_CRED_PROVIDER = Aws\Credentials\CredentialProvider::memoize($c9Credential);
}

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

$CONFIG->set('scanned', ['resources' => 0, 'rules' => 0]);

$serviceStat = [];

global $GLOBALRESOURCES, $CW;
$GLOBALRESOURCES = [];
    
$overallTimeStart = microtime(true);

exec('cd __fork; rm -f *.json');

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
    'rules' => 0
];
$hasGlobal = false;
foreach($files as $file){
    if($file[0] == '.')
        continue;
    
    if($file == SESSUID_FILENAME)
        continue;
    
    $f = explode('.', $file);
    if(sizeof($f) == 2){
        $contexts[$f[0]] = json_decode(file_get_contents(FORK_DIR . '/' . $file), true);
    }else{
        list($cnt, $rules) = array_values(json_decode(file_get_contents(FORK_DIR . '/' .$file), true));
        $serviceStat[$f[0]] = $cnt;
        $scanned['resources'] += $cnt;
        $scanned['rules'] += $rules;
        
        if(in_array($f[0], CONFIG::GLOBAL_SERVICES))
            $hasGlobal = true;
    }
}

if($testmode)
    exit("Test mode enable, script halted" . PHP_EOL);

__info("Total Resources scanned: " . number_format($scanned['resources']) . " | No. Rules executed: " . number_format($scanned['rules']));
__info("Time consumed: " .  round(microtime(true) - $overallTimeStart, 3));

## Cleanup
exec('cd __fork; rm -f *.json');
exec('cd '.HTML_FOLDER.'; rm -f *.html');
exec('rm -f output.zip');

if($hasGlobal)
    $regions[] = 'GLOBAL';   

$rawServices = [];
foreach($contexts as $service => $resultSets){
    $rawServices[] = $service;
    
    $reporter = new reporter($service);
    $reporter->process($resultSets)
        ->getSummary()
        ->getDetails();
     
    $pageBuilderClass = $service . 'pageBuilder';
    if(!class_exists($pageBuilderClass)){
        __info($pageBuilderClass . ' class not found, using default pageBuilder');
        $pageBuilderClass = 'pageBuilder';
    }
        
    $pb = new $pageBuilderClass($service, $reporter, $serviceStat, $regions);
    $pb->buildPage();
}

## pageBuilderForDashboard
$dashPB = new dashboardPageBuilder('index', [], $serviceStat, $regions);
$dashPB->buildPage();

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

if($feedbackFlag){    
    __info("*** Sending feedback ***");
    feedback::send($rawServices, $regions);
}
__info("@ Thank you for using ". Config::ADVISOR['TITLE'] ." @");
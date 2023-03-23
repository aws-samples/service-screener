<?php 

use Aws\Sts\StsClient;

class Config{
    const AWS_SDK = [
        'RDSCLIENT_VERS' => '2014-10-31',
        'EC2CLIENT_VERS' => '2016-11-15',
        'COMPOPTCLIENT_VERS' => '2019-11-01',
        'COSTEXPLORERCLIENT_VERS' => '2017-10-25',
        'ELBCLIENT_VERS' => '2015-12-01',
        'ELBCLASSICCLIENT_VERS' => '2012-06-01',
        'ASGCLIENT_VERS' => '2011-01-01',
        'IAMCLIENT_VERS' => '2010-05-08',
        'S3CLIENT_VERS'  => '2006-03-01',
        'S3CONTROL_VERS' => '2018-08-20',
        'STSCLIENT_VERS' => '2011-06-15',
        'CLOUDWATCHCLIENT_VERS' => '2010-08-01',
        'SSMCLIENT_VERS' => '2014-11-06',
        'AOSCLIENT_VERS' => '2021-01-01',
        'EFSCLIENT_VERS' => '2015-02-01',
        'GUARDDUTYCLIENT_VERS' => '2017-11-28',
        'LAMBDACLIENT_VERS' => '2015-03-31',
        'ACCOUNTCLIENT_VERS' => '2021-02-01',
        'signature_version' => 'v4'
    ];
    
    const ADVISOR = [
        'TITLE' => 'Service Screener',
        'VERSION' => '1.1.0',
        'LAST_UPDATE' => '22-Feb-2023'
    ];
    
    const ADMINLTE = [
        'VERSION'=> '3.1.0',
        'DATERANGE' => '2014-2021',
        'URL' => 'https://adminlte.io',
        'TITLE' => 'AdminLTE.io'
    ];
    
    const CLI_ARGUMENT_RULES = [
        "region" => [
            "required" => true, 
            "errmsg" => "Please key in --region, example: --region ap-southeast-1"
        ],
        "services" => [
            "required" => false,
            "emptymsg" => "Missing --services, using default value: \$defaultValue",
            "default" => "rds,ec2,iam,s3,efs,opensearch,guardduty"
        ],
        "debug" => [
            "required" => false,
            "default" => true
        ],
        "log" => [
            "required" => false,
            "default" => ''
        ],
        "feedback" => [
            "required" => false,
            "default" => false
        ],
        "test" => [
            "required" => false,
            "default" => false
        ],
        "mode" => [
            "required" => false,
            "default" => "report"
        ],
        "profile" => [
            "required" => false,
            "default" => false
        ],
        "bucket" => [
            "required" => false,
            "default" => false
        ],
        "filters" => [
            "required" => false,
            "default" => false
        ]
    ];
    
    const GLOBAL_SERVICES = [
        'iam'
    ];
    
    static function setAccountInfo($__AWS_CONFIG){
        global $CONFIG, $PHPSDK_CRED_PROVIDER, $PHPSDK_CRED_PROFILE;
        $stsInfo = [];

        __info(" -- Acquiring identify info...");
        try{
            $__AWS_CONFIG['version'] = self::AWS_SDK['STSCLIENT_VERS'];
            if(isset($PHPSDK_CRED_PROVIDER))
                $__AWS_CONFIG['credentials'] = $PHPSDK_CRED_PROVIDER;
            else if(isset($PHPSDK_CRED_PROFILE))
                $__AWS_CONFIG['profile'] = $PHPSDK_CRED_PROFILE;
            
            $stsClient = new StsClient($__AWS_CONFIG);
            
            $resp = $stsClient->getCallerIdentity();
            $stsInfo = [
                'UserId' => $resp->get('UserId'),
                'Account' => $resp->get('Account'),
                'Arn'   => $resp->get('Arn')
            ];
        }catch(exception $e){
            __warn('Exception happens, not catching properly');
            debug_print_backtrace();
        }
        
        $CONFIG->set('stsInfo', $stsInfo);
    }
    
    
    
    function set($key, $val){
        $this->$key = $val;
    }
    
    function get($key, $defaultValue = false){
        global $DEBUG;
        if ( !property_exists($this, $key) && $defaultValue === false ){
            if ($DEBUG)
                debug_print_backtrace();
        }
        
        return $this->$key ?? ($defaultValue ?? "");
    }
}

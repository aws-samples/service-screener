<?php
use Aws\CloudWatch\CloudWatchClient;
use Aws\CloudWatch\Exception;

class cloudwatch{
    function __construct($region){
        global $CONFIG; 
        global $PHPSDK_CRED_PROVIDER, $PHPSDK_CRED_PROFILE;
        
        $this->__AWS_OPTIONS = $CONFIG->get("__AWS_OPTIONS");
        $this->__AWS_OPTIONS['region'] = $region;
        $this->__AWS_OPTIONS['version'] = CONFIG::AWS_SDK['CLOUDWATCHCLIENT_VERS'];
        if(isset($PHPSDK_CRED_PROVIDER))
            $this->__AWS_OPTIONS['credentials'] = $PHPSDK_CRED_PROVIDER;
        else if(isset($PHPSDK_CRED_PROFILE))
            $this->__AWS_OPTIONS['profile'] = $PHPSDK_CRED_PROFILE;

        $this->cwClient = new CloudWatchClient($this->__AWS_OPTIONS);
    }
    
    function getClient(){
        return $this->cwClient;   
    }
    
    function test(){
        $metric = 'FreeStorageSpace';
        $results = $this->cwClient->getMetricStatistics([
            'Dimensions' => [
                [
                    'Name' => 'DBInstanceIdentifier',
                    'Value' => 'postgres-13'
                ]
            ],
            'Namespace' => 'AWS/RDS',
            'MetricName' => $metric,
            'StartTime' => strtotime('-5 minutes'),
            'EndTime' => strtotime('now'),
            'Period' => 300,
            'Statistics' => ['Average'],
            #'Unit' => 'None'
        ]);   
        
        __pr($results);
    }
}
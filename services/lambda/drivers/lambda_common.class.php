<?php 

class lambda_common extends evaluator{
    const RUNTIME_PREFIX = [
        'nodejs',
        'python',
        'java',
        'dotnetcore',
        'dotnet',
        'go',
        'ruby'
    ];
    
    const CUSTOM_RUNTIME_PREFIX = [
        'provided'
    ];
    
    // const LATEST_RUNTIME = [
    //     'nodejs18.x',
    //     'python3.9',
    //     'java11',
    //     'dotnetcore3.1',
    //     'dotnet6',
    //     'go1.x',
    //     'ruby2.7',
    // ];
    
    // const DEPRECATED_RUNTIME = [
    //     'python3.6',
    //     'python2.7',
    //     'dotnetcore2.1',
    //     'ruby2.5',
    //     'nodejs10.x',
    //     'nodejs8.10',
    //     'nodejs4.3',
    //     'nodejs6.10',
    //     'dotnetcore1.0',
    //     'dotnetcore2.0',
    //     'nodejs4.3-edge',
    //     'nodejs'
    // ];
    
    const CUSTOM_RUNTIME = [
        'provided.al2',
        'provided'
    ];
    
    const CW_HISTORY_DAYS = [30,7];
    
    function __construct($lambda, $lambdaClient, $iamClient, $roleCount){
        $this->lambda = $lambda;
        $this->functionName = $lambda['FunctionName'];
        $this->roleCount = $roleCount;
        $this->lambdaClient = $lambdaClient;
        $this->iamClient = $iamClient;
        $this->init();
    }
    
    function __checkFunctionURLInUsed(){
        $urlConfig = $this->lambdaClient->listFunctionUrlConfigs([
            'FunctionName' => $this->functionName
        ]);
        if(!empty($urlConfig['FunctionUrlConfigs'])){
            $this->results['lambdaURLInUsed'] = [-1, $this->functionName];
        }
        return;
    }
    
    function __checkMissingRole(){
        $roleArn = $this->lambda['Role'];
        $roleName = $this->getArnRoleName($roleArn);
        
        try{
            $role = $this->iamClient->getRole([
                'RoleName' => $roleName
            ]);
        }catch(Exception $e){
            if($e->getAwsErrorCode() == 'NoSuchEntity'){
                $this->results['lambdaMissingRole'] = [-1, $this->functionName];
            }else{
                throw $e;
            }
        }
        
        return;
    }
    
    function getArnRoleName($arn){
        $array = explode("/", $arn);
        $roleName = $array[sizeof($array) - 1];
        return $roleName;
    }
    
    
    function __checkURLWithoutAuth(){
        $urlConfigs = $this->lambdaClient->listFunctionUrlConfigs([
            'FunctionName' => $this->functionName
        ]);
        
        if(!empty($urlConfigs['FunctionUrlConfigs'])){
            foreach($urlConfigs['FunctionUrlConfigs'] as $config){
                if($config['AuthType'] == 'NONE'){
                    $this->results['lambdaURLWithoutAuth'] = [-1, $this->functionName];
                }
            }
        }
        
        return;
    }
    
    function __checkCodeSigningDisabled(){
        $codeSign = $this->lambdaClient->getFunctionCodeSigningConfig([
            'FunctionName' => $this->functionName
        ]);
        if(!isset($codeSign['CodeSigningConfigArn'])){
            $this->results['lambdaCodeSigningDisabled'] = [-1, $this->functionName];
        }
        
        return;
    }
    
    function __checkDeadLetterQueueDisabled(){
        $config = $this->lambdaClient->getFunctionConfiguration([
            'FunctionName' => $this->functionName
        ]);
        
        if(!isset($config['DeadLetterConfig'])){
            $this->results['lambdaDeadLetterQueueDisabled'] = [-1, $this->functionName];
        }
        
        return;
    }
    
    function __checkEnvVarDefaultKey(){
        $functionName = $this->lambda['FunctionName'];
        if(!isset($this->lambda['KMSKeyArn'])){
            $this->results['lambdaCMKEncryptionDisabled'] = [-1, $this->functionName];
        }
        return;
    }
    
    function __checkEnhancedMonitor(){
        $enabled = false;
        if(isset($this->lambda['Layers'])){
           $layers = $this->lambda['Layers'];
           foreach($layers as $layer){
               if(strpos($layer['Arn'], 'LambdaInsightsExtension')){
                  $enabled = true;
               }
           }
        }
        
        if(!$enabled){
            $this->results['lambdaEnhancedMonitoringDisabled'] = [-1, $this->functionName];
        }
    }
    
    function __checkProvisionedConcurrency(){
        $concurrency = $this->lambdaClient->getFunctionConcurrency([
            'FunctionName' => $this->functionName
        ]);
        
        if(!isset($concurrency['ReservedConcurrentExecutions'])){
            $this->results['lambdaReservedConcurrencyDisabled'] = [-1, $this->functionName];
        }
        
        return;
    }
    
    function __checkTracingEnabled(){
        if($this->lambda['TracingConfig']['Mode'] == 'PassThrough'){
            $this->results['lambdaTracingDisabled'] = [-1, $this->functionName];
        }
        
        return;
    }
    
    function __checkRoleReused(){
        if($this->roleCount[$this->lambda['Role']] > 1){
            $this->results['lambdaRoleReused'] = [-1, $this->functionName];
        }
        return;
    }
    
    function __checkRuntime(){
        $arr = include(__DIR__ .'/../../../vendor/aws/aws-sdk-php/src/data/lambda/2015-03-31/api-2.json.php');
        $runtime = $this->lambda['Runtime'];
        
        $runtime_prefix = '';
        $runtime_version = '';
        foreach(self::CUSTOM_RUNTIME_PREFIX as $prefix){
            if(str_starts_with($runtime, $prefix)){
                return;
            }
        }
        
        foreach(self::RUNTIME_PREFIX as $prefix){
            if(str_starts_with($runtime, $prefix)){
                $runtime_prefix = $prefix;
                
                $replace_arr = [$runtime_prefix];
                if(in_array($runtime_prefix, ['go', 'nodejs'])){
                    array_push($replace_arr, '.x');
                }
                if($runtime_prefix == 'nodejs'){
                    array_push($replace_arr, '-edge');
                }
                
                $runtime_version = str_replace($replace_arr, '', $runtime);
                break;
            }
        }
        
        foreach($arr['shapes']['Runtime']['enum'] as $option){
            if(!str_starts_with($option, $runtime_prefix)){
                continue;
            }else{
                $option_version = str_replace($replace_arr, '', $option);
                if($option_version == ''){
                    $option_version = 0;
                }
                
                if($option_version > $runtime_version){
                    $this->results['lambdaRuntimeUpdate'] = [-1, $this->functionName];
                    return;
                }
            }
        }
        
        return;
    }
    
    function getInvocationCount($day){
        global $CW;
        
        $cwClient = $CW->getClient();
        
        $dimensions = [
            [
                'Name' => 'FunctionName',
                'Value' => $this->functionName
            ]    
        ];
        
        $results = $cwClient->getMetricStatistics([
            'Dimensions' => $dimensions,
            'Namespace' => 'AWS/Lambda',
            'MetricName' => 'Invocations',
            'StartTime' => strtotime('-'. $day .' days'),
            'EndTime' => strtotime('now'),
            'Period' => $day * 24 * 60 * 60,
            'Statistics' => ['SampleCount']
        ]);
        
        if(empty($results['Datapoints'])){
            return 0;
        }else{
            foreach($results['Datapoints'] as $result){
                return $result['SampleCount'];
            }
        }
    }
    
    function __checkFunctionInUsed(){
        foreach(self::CW_HISTORY_DAYS as $day){
            $cnt = $this->getInvocationCount($day);
            
            if($cnt == 0){
                $this->results['lambdaNotInUsed' . $day . 'Days'] = [-1, $this->functionName];
                return;
            }
        }
        
        return;
    }
    
}

?>
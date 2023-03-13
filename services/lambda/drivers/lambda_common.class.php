<?php 

class lambda_common extends evaluator{
    const LATEST_RUNTIME = [
        'nodejs18.x',
        'python3.9',
        'java11',
        'dotnetcore3.1',
        'dotnet6',
        'go1.x',
        'ruby2.7',
    ];
    
    const DEPRECATED_RUNTIME = [
        'python3.6',
        'python2.7',
        'dotnetcore2.1',
        'ruby2.5',
        'nodejs10.x',
        'nodejs8.10',
        'nodejs4.3',
        'nodejs6.10',
        'dotnetcore1.0',
        'dotnetcore2.0',
        'nodejs4.3-edge',
        'nodejs'
    ];
    
    const CUSTOM_RUNTIME = [
        'provided.al2',
        'provided'
    ];
    
    const CW_HISTORY_DAYS = 7;
    
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
        if(isset($codeSign['CodeSigningConfigArn'])){
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
    
    function __checkEnvVarEncryptInTransit(){
        
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
    
    ## Double Check for this
    function __checkProvisionedConcurrency(){
        $concurrency = $this->lambdaClient->getFunctionConcurrency([
            'FunctionName' => $this->functionName
        ]);
        
        if(!isset($concurrency['ReservedConcurrentExecutions'])){
            $this->results['lambdaReservedConcurrencyDisabled'] = [-1, $this->functionName];
        }
        
        return;
    }
    
    function __checkExposed(){
        try{
            $result = $this->lambdaClient->getPolicy([
                'FunctionName' => $this->functionName 
            ]);
            $policy = $result['Policy'];
            $policyArr = json_decode($policy);
            foreach($policyArr->Statement as $statement){
                if($statement->Effect == 'Allow' && $statement->Principal == '*'){
                    $this->results['lambdaExposed'] = [-1, $this->functionName];
                }
            }
        }catch(Exception $e){
            if($e->getAwsErrorCode() == 'ResourceNotFoundException'){
                return;
            }else{
                throw $e;
            }
        }
        
        return;
    }
    
    function __checkAdminPrivilege(){
        $roleName = $this->getArnRoleName($this->lambda['Role']);
        
        ## In-lined Policies
        $results = $this->iamClient->listRolePolicies([
            'RoleName' => $roleName
        ]);
        foreach($results['PolicyNames'] as $name){
            $policy = $this->iamClient->getRolePolicy([
                'RoleName' => $roleName,
                'PolicyName' => $name
            ]);
            $doc = $policy->get('PolicyDocument');
            $doc = urldecode($doc);
            
            $pObj = new policy($doc);
            $pObj->inspectAccess();
            if($pObj->hasFullAccessToOneResource() == true){
                $this->results['lambdaFullAccessToResource'] = [-1, $this->functionName];
                return;
            }
            if($pObj->hasFullAccessAdmin() == true){
                $this->results['lambdaAdminAccess'] = [-1, $this->functionName];
                return;
            }
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
        $runtime = $this->lambda['Runtime'];
        if(in_array($runtime, self::DEPRECATED_RUNTIME)){
            $this->results['lambdaRuntimeDeprecate'] = [-1, $this->functionName];
        }else if(!in_array($runtime, self::LATEST_RUNTIME) && !in_array($runtime, self::CUSTOM_RUNTIME)){
            $this->results['lambdaRuntimeUpdate'] = [-1, $this->functionName];
        }
        
        return;
    }
    
    function getInvocationCount(){
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
            'StartTime' => strtotime('-'. self::CW_HISTORY_DAYS .' days'),
            'EndTime' => strtotime('now'),
            'Period' => self::CW_HISTORY_DAYS * 24 * 60 * 60,
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
            'StartTime' => strtotime('-'. self::CW_HISTORY_DAYS .' days'),
            'EndTime' => strtotime('now'),
            'Period' => self::CW_HISTORY_DAYS * 24 * 60 * 60,
            'Statistics' => ['SampleCount']
        ]);
        
        if(empty($results['Datapoints'])){
            $this->results['lambdaNotInUsed'] = [-1, $this->functionName];
        }
        
        return;
    }
    
}

?>
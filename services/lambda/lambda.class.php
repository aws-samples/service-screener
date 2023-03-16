<?php
## https://docs.aws.amazon.com/aws-sdk-php/v3/api/namespace-Aws.Lambda.html
use Aws\Lambda\LambdaClient;
use Aws\Support\SupportClient;
use Aws\Lambda\Exception;
use Aws\Iam\IamClient;

class lambda extends service{
    // const LAMBDA_SDK_VERSION = '2015-03-31';
    public $lambdaClient;
    
    function __construct($region){
        parent::__construct($region);
        
        $this->__AWS_OPTIONS['version'] = CONFIG::AWS_SDK['LAMBDACLIENT_VERS'];
        $this->lambdaClient = new LambdaClient($this->__AWS_OPTIONS);
        
        $this->__AWS_OPTIONS['version'] = CONFIG::AWS_SDK['IAMCLIENT_VERS'];
        $this->iamClient = new IamClient($this->__AWS_OPTIONS);
        
        $this->__loadDrivers();
    }
    
    function getResources(){
        $arr = [];
        $nextToken = '';
        do{
            if(!empty($nextToken)){
                $results = $this->lambdaClient->ListFunctions([
                    "Marker" => $nextToken
                ]);
            }else{
                $results = $this->lambdaClient->ListFunctions();
            }
            $arr = array_merge($arr, $results->get('Functions'));
            $nextToken = $results->get('NextMarker');
            
        }while(!empty($nextToken));
        if(empty($this->tags))
            return $arr;
        
        $filterLambda = [];
        foreach($arr as $key => $lambda){
            $results = $this->lambdaClient->listTags([
                'Resource' => $lambda['FunctionArn']
            ]);
            $tags = $results['Tags'];
            $this->resourceHasTags($tags);
            
            
            if($this->resourceHasTags($tags)){
                $filterLambda[] = $lambda;
            }
        }
        
        return $filterLambda;
    }
    
    function resourceHasTags($tags){
        foreach($this->tags as $tag){
            $key = str_replace('tag:', '', $tag['Name']);
            foreach($tag['Values'] as $v){
                $value = $v;
            }
            if(array_key_exists($key, $tags) && $tags[$key] == $value){
                return true;
            }
        }
        return false;
    }
    
    function advise(){
        $objs = [];
        $funcRoleMap = [];
        $region = $this->__AWS_OPTIONS['region'];
        
        $lambdas = $this->getResources();
        foreach($lambdas as $lambda){
            $funcRoleMap[] = $lambda['Role'];
        }
        $roleCount = array_count_values($funcRoleMap);
        
        $driver = 'lambda_common';
        foreach($lambdas as $lambda){
            if(class_exists($driver)){
                __info('... (Lambda) inspecting ' . $lambda['FunctionName']);
                $obj = new $driver($lambda, $this->lambdaClient, $this->iamClient, $roleCount);
                $obj->run();
                
                $objs['Lambda::'. $lambda['FunctionName']] = $obj->getInfo();
                unset($obj);
            }
        }
        
        return $objs;
        
    }
    
    function __loadDrivers(){
        $path = __DIR__ .'/drivers/';
        $files = scandir($path);
        foreach($files as $file){
            if ($file[0] == '.')
                continue;
            
            include_once($path . $file);
        }
    }
}
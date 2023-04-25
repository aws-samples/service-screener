<?php
## https://docs.aws.amazon.com/aws-sdk-php/v3/api/class-Aws.EKS.EKSClient.html
use Aws\EKS\EKSClient;
use Aws\Ec2\Ec2Client;
use Aws\Iam\IamClient;

class eks extends service{
    function __construct($region){
        parent::__construct($region);
        
        $this->__AWS_OPTIONS['version'] = CONFIG::AWS_SDK['EKSCLIENT_VERS'];
        $this->eksClient = new EKSClient($this->__AWS_OPTIONS);
        
        $this->__AWS_OPTIONS['version'] = CONFIG::AWS_SDK['EC2CLIENT_VERS'];
        $this->ec2Client = new Ec2Client($this->__AWS_OPTIONS);
        
        $this->__AWS_OPTIONS['version'] = CONFIG::AWS_SDK['IAMCLIENT_VERS'];
        $this->iamClient = new IamClient($this->__AWS_OPTIONS);
        
        $this->__loadDrivers();
    }
    
    function getCluster(){
        $arr = [];
        $nextToken = '';
        do{
            if(!empty($nextToken)){
                $results = $this->eksClient->ListClusters([
                    "nextToken" => $nextToken
                ]);
            }else{
                $results = $this->eksClient->ListClusters();
            }
            $arr = array_merge($arr, $results->get('clusters'));
            $nextToken = $results->get('nextToken');
        }while(!empty($nextToken));
        
        return $arr;
    }
    
    function describeCluster($clsuterName){
        $response = $this->eksClient->describeCluster([
            'name' => $clsuterName
        ]);
        
        return $response->get('cluster');
    }
    
    function advise(){
        $objs = [];
        $clusters = $this->getCluster();
        
        foreach($clusters as $cluster){
            __info('...(EKS:Cluster) inspecting ' . $cluster);
            $clusterInfo = $this->describeCluster($cluster);
            $tags = $clusterInfo['tags'];
            if(!$this->resourceHasTags($tags)){
                continue;
            }
            
            $clusterInfo = $this->describeCluster($cluster);
            if($clusterInfo['status'] == 'CREATING'){
                __warn(cluster + " cluster is creating. Skipped");
            }
            
            $driver = 'eks_common';
            $obj = new $driver($cluster, $clusterInfo, $this->eksClient, $this->ec2Client, $this->iamClient);
            $obj->run();
            $objs['Cluster::'.$cluster] = $obj->getInfo();
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
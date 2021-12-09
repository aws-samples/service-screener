<?php 
use Aws\S3\S3Client;
use AWS\S3\Exception;
use Aws\S3Control\S3ControlClient;

## https://docs.aws.amazon.com/aws-sdk-php/v3/api/class-Aws.S3.S3Client.html
class s3 extends service{
    private $s3Client;
    private $region;
    function __construct($region){
        parent::__construct($region);
        
        $this->region = $region;
    
        $this->__AWS_OPTIONS['version'] = CONFIG::AWS_SDK['S3CLIENT_VERS'];
        $this->s3Client = new S3Client($this->__AWS_OPTIONS);
        
        $this->__AWS_OPTIONS['version'] = CONFIG::AWS_SDK['S3CONTROL_VERS'];
        $this->s3Control = new S3ControlClient($this->__AWS_OPTIONS);
        
        $this->__loadDrivers();
    }
    
    ## Might need to loops
    function getResources(){
        global $CONFIG;
        $buckets = $CONFIG->get('s3::buckets', []);
        if(empty($buckets)){
            $buckets = [];
            $results = $this->s3Client->listBuckets();
            
            $arr = $results->get('Buckets');
            while($results->get('Maker') !== null){
                $results = $this->s3Client->listBuckets([
                    'Maker' => $results->get('Maker')
                ]);    
                $arr = array_merge($arr, $results->get('Buckets'));
            }
            
            foreach($arr as $ind => $bucket){
                $loc = $this->s3Client->getBucketLocation([
                    'Bucket' => $bucket['Name'] 
                ]);
                
                $reg = $loc->get('LocationConstraint');
                $buckets[$reg][] = $arr[$ind];
            }
            
            $CONFIG->set('s3::buckets', $buckets);
        }
        
        return $buckets[$this->region];
    }
    
    function advise(){
        global $GLOBALRESOURCES;
        $objs = [];
        $driver = 's3_control';
        if (class_exists($driver)){
            __info('... (S3Account) inspecting ');
            $obj = new $driver($this->s3Control);
            $obj->run();
            
            $objs["Account::Bucket"] = $obj->getInfo();
            $GLOBALRESOURCES = $objs;
            
            $objs = [];
            unset($obj);
        }
        
        $buckets = $this->getResources();
        foreach($buckets as $bucket){
            __info('... (S3Bucket) inspecting ' . $bucket['Name']);
            $driver = 's3_s3';
            if (class_exists($driver)){
                $obj = new $driver($bucket['Name'], $this->s3Client);
                $obj->run();
                
                $objs[$bucket['Name']] = $obj->getInfo();
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
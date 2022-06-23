<?php 
 use Aws\OpenSearchService\OpenSearchServiceClient;

## https://docs.aws.amazon.com/aws-sdk-php/v3/api/class-Aws.OpenSearchService.OpenSearchServiceClient.html
class aos extends service{
    private $aosClient;
    private $region;
    function __construct($region){
        parent::__construct($region);
        
        $this->region = $region;
    
        $this->__AWS_OPTIONS['version'] = CONFIG::AWS_SDK['AOSCLIENT_VERS'];
        $this->aosClient = new OpenSearchServiceClient($this->__AWS_OPTIONS);
        
        $this->__loadDrivers();
    }
    
    ## Might need to loop
    function getResources(){
        $domains = [];
        $results = $this->aosClient->listDomainNames();
        
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
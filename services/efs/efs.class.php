<?php 
 use Aws\Efs\EfsClient;

## https://docs.aws.amazon.com/aws-sdk-php/v3/api/class-Aws.Efs.EfsClient.html
class efs extends service{
    private $efsClient;
    private $region;
    function __construct($region){
        parent::__construct($region);
        
        $this->region = $region;
    
        $this->__AWS_OPTIONS['version'] = CONFIG::AWS_SDK['EFSCLIENT_VERS'];
        $this->efsClient = new EfsClient($this->__AWS_OPTIONS);
        
        $this->__loadDrivers();
    }
    
    function getResources(){
        $resources = $this->efsClient->DescribeFileSystems();
        $results = $resources['FileSystems'];
        
        if(empty($this->tags))
            return $results;
            
        $filteredResults = [];
        foreach($results as $ind => $efs){
            if($this->resourceHasTags($efs['Tags']))
                $filteredResults[] = $efs;
        }
        
        return $filteredResults;
    }
    
    function advise(){
        global $GLOBALRESOURCES;
        $objs = [];
        
        $efsList = $this->getResources();
        
        $driver = 'efs_efs';
        if (class_exists($driver)){
            foreach($efsList as $efs){
                __info('... (EFS) inspecting ' . $efs['FileSystemId']);
                $obj = new $driver($efs, $this->efsClient);
                $obj->run();
                
                $objs['EFS::' . $efs['FileSystemId']] = $obj->getInfo();
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
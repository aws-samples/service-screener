<?php 
use Aws\GuardDuty\GuardDutyClient;
use AWS\GuardDuty\Exception;

class guardduty extends service{
    private $guarddutyClient;
    
    
    function __construct($region){
        parent::__construct($region);
        $this->region = $region;
    
        $this->__AWS_OPTIONS['version'] = CONFIG::AWS_SDK['GUARDDUTYCLIENT_VERS'];
        $this->guarddutyClient = new GuardDutyClient($this->__AWS_OPTIONS);
        $this->__loadDrivers();
    }
    
    ## Might need to loops
    function getResources(){
        $results = $this->guarddutyClient->listDetectors();
        $arr = $results->get('DetectorIds');
        
        return $arr;
    }
    
    function advise(){
        $objs = [];
        $detectors = $this->getResources();
        foreach($detectors as $detector){
            __info('... (GuardDuty) inspecting ' . $detector);
            
            $driver = 'guardduty_detail';
            if (class_exists($driver)){
                $obj = new $driver($detector, $this->guarddutyClient, $this->region);
                $obj->run();
                
                $objs["Detector::" . $detector] = $obj->getInfo();
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
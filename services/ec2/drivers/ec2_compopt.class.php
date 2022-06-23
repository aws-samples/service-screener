<?php
class ec2_compopt extends evaluator{
    public $results = [];

    function __construct($compOptClient){
        $this->compOptClient = $compOptClient;
        $this->init();
    }
    
    function __checkComputeOptimizerEnabled(){
        $compOptClient = $this->compOptClient;
        
        
        ## User SSM to check service exist
        // /aws/service/global-infrastructure/regions/ap-southeast-1/services/compute-optimizer
        
        try{
            $result = $compOptClient ->getEnrollmentStatus();
            if($result['status'] != 'Active'){
                $this->results['ComputeOptimizerEnabled'] = [-1, $result['status']];
            }
        }catch(Exception $e){
            
            print_r('start');
            print_r($e->getCode());
            print_r('end');
        }
        
        
        
        
        return;
    }
    
}

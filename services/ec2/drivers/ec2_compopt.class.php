<?php
class ec2_compopt extends evaluator{
    public $results = [];

    function __construct($compOptClient){
        $this->compOptClient = $compOptClient;
        $this->init();
    }
    
    function __checkComputeOptimizerEnabled(){
        $compOptClient = $this->compOptClient;
        $result = $compOptClient ->getEnrollmentStatus();
        
        if($result['status'] != 'Active'){
            $this->results['ComputeOptimizerEnabled'] = [-1, $result['status']];
        }
        
        return;
    }
    
}

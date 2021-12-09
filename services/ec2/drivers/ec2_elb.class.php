<?php

class ec2_elb extends evaluator{
    public $results = [];
    const PORTWITHOUTENCRYPT = [
        21,
        80
    ];
    
    function __construct($elb, $elbClient){
        $this->elb = $elb;
        $this->elbClient = $elbClient;
        
        $this->init();
        
    }
    
    function __checkListenerPortEncrypt(){
        $elbArn = $this->elb['LoadBalancerArn'];
        $elbName = $this->elb['LoadBalancerName'];
        $result = $this->elbClient->DescribeListeners([
            'LoadBalancerArn' => $elbArn
        ]);
        
        $listeners = $result['Listeners'];
        foreach($listeners as $listener){
            if(in_array($listener['Port'], self::PORTWITHOUTENCRYPT)){
                $this->results['ELBListenerInsecure'] = ['-1', $listener['Port']];
                return;
            }
        }
        return;
    }
    
    function __checkSecurityGroupNo(){
        $elb = $this->elb;
        $elbName = $elb['LoadBalancerName'];
        
        if(isset($elb['SecurityGroups'])){
            if(sizeof($elb['SecurityGroups']) > 50){
                $this->results['ELBSGNumber'] = ['-1', sizeof($elb['SecurityGroups'])];
                return;
            }
        }
        
        return;
    }
    
    function __checkCrossZoneLB(){
        $elb = $this->elb;
        $arn = $elb['LoadBalancerArn'];
        
        $results = $this->elbClient->describeLoadBalancerAttributes([
            'LoadBalancerArn' => $arn
        ]);
        
        foreach($results['Attributes'] as $attr){
            if($attr['Key'] == 'load_balancing.cross_zone.enabled'){
                if($attr['Key'] == 'false'){
                    $this->results['ELBCrossZone'] = ['-1', $attr['Key']];
                }
            }
        }
        return;
    }
}
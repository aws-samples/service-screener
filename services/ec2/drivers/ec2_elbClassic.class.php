<?php

class ec2_elbClassic extends evaluator{
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
    
    function __checkClassicLoadBalancer(){
        $this->results['ELBClassicLB'] = ['-1', $this->elb['LoadBalancerName']];
    }
    
    function __checkListenerPortEncrypt(){
        $listeners = $this->elb['ListenerDescriptions'];
        
        foreach($listeners as $listener){
            if(in_array($listener['Listener']['Protocol'], ['HTTP', 'TCP'])){
                $this->results['ELBListenerInsecure'] = ['-1', $listener['Listener']['Protocol']];
            }
        }
        return;
    }
    
    function __checkSecurityGroupNo(){
        if(sizeof($this->elb['SecurityGroups']) > 50){
            $this->results['ELBSGNumber'] = ['-1', sizeof($this->elb['SecurityGroups'])];
        }
        
        return;
    }
    
    function __checkAttributes(){
        $results = $this->elbClient->describeLoadBalancerAttributes([
            'LoadBalancerName' => $this->elb['LoadBalancerName']
        ]);
        
        $attributes = $results['LoadBalancerAttributes'];
        
        if(isset($attributes['CrossZoneLoadBalancing']) && $attributes['CrossZoneLoadBalancing']['Enabled'] != 1){
            $this->results['ELBCrossZone'] = ['-1', $attributes['CrossZoneLoadBalancing']['Enabled']];
        }
        
        if(isset($attributes['ConnectionDraining']) && $attributes['ConnectionDraining']['Enabled'] != 1){
            $this->results['ELBConnectionDraining'] = ['-1', $attributes['ConnectionDraining']['Enabled']];
        }
        
        return;
    }
}
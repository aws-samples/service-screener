<?php
class ec2_asg extends evaluator{
    public $results = [];

    function __construct($asg, $asgClient, $elbClient, $elbClassicClient, $ec2Client){
        $this->asg = $asg;
        $this->asgClient = $asgClient;
        $this->elbClient = $elbClient;
        $this->elbClassicClient = $elbClassicClient;
        $this->ec2Client = $ec2Client;
        $this->init();
    }
    
    function __checkELBHealthCheckWithoutAssociation(){
        $asg = $this->asg;
        
        if($asg['HealthCheckType'] == 'ELB' && empty($asg['LoadBalancerNames']) && empty($asg['TargetGroupARNs'])){
            $this->results['ASGELBHealthCheckValidation'] = [-1, ''];
        }
        return;
    }
    
    function __checkELBHealthCheckEnabled(){
        $asg = $this->asg;
        
        if((!empty($asg['LoadBalancerNames']) || !empty($asg['TargetGroupARNs'])) && $asg['HealthCheckType'] != 'ELB'){
            $this->results['ASGELBHeaalthCheckEnabled'] = [-1, ''];
        }
        return;
    }
    
    function __checkTargetGroupInstancesRemoved(){
        $asg = $this->asg;
        
        $results = $this->asgClient->describeLoadBalancerTargetGroups([
            'AutoScalingGroupName' => $asg['AutoScalingGroupName']
        ]);
        
        $targetGroups = $results['LoadBalancerTargetGroups'];
        foreach($targetGroups as $group){
            if($group['State'] == 'Removed'){
                $this->results['ASGInstancesRemoved'] = [-1, $group['State']];
            }
        }
        
        return;
    }
    
    function __checkTargetGroupELBAssociation(){
        $asg = $this->asg;
        if(empty($asg['TargetGroupARNs'])){
            return;
        }
        
        $results = $this->elbClient->describeTargetGroups([
            'TargetGroupArns' => $asg['TargetGroupARNs']
        ]);
        
        foreach($results['TargetGroups'] as $group){
            if(empty($group['LoadBalancerArns'])){
                $this->results['ASGTargetGroupELBExist'] = [-1, ''];
                return;
            }
        }
        
        return;
    }
    
    function __checkClassicLBAssociation(){
        $asg = $this->asg;
        $lbNames = $asg['LoadBalancerNames'];
        if(empty($lb['LoadBalancerName'])){
            return;
        }
        
        ## API filter not working
        $result = $this->elbClassicClient->describeLoadBalancers();
        
        foreach($result['LoadBalancerDescriptions'] as $lb){
            if(in_array($lb['LoadBalancerName'], $lbNames)){
                return; 
            }
        }
        
        ## Classic Load balancer deleted
        $this->results['ASGClassicLBExist'] = [-1, ''];
        return;
    }
    
    function __checkAMIExist(){
        $asg = $this->asg;
        $imageId = '';
        if(isset($asg['LaunchConfigurationName'])){
            $launchConfig = $asg['LaunchConfigurationName'];
            
            $result = $this->asgClient->describeLaunchConfigurations([
                'LaunchConfigurationNames' => [$launchConfig]
            ]);
            
            foreach($result['LaunchConfigurations'] as $config){
                $imageId = $config['ImageId'];
            }
        }else if(isset($asg['MixedInstancesPolicy'])){
            $templateInfo = $asg['MixedInstancesPolicy']['LaunchTemplate']['LaunchTemplateSpecification'];
            $templateId = $templateInfo['LaunchTemplateId'];
            $templateVersion = $templateInfo['Version'];
            
            
            $templateResult = $this->ec2Client->describeLaunchTemplateVersions([
                'LaunchTemplateIds' => $templateId,
                'Versions' => [$templateVersion]
            ]);
            
            foreach($templateResult['LaunchTemplateVersions'] as $version){
                $imageId = $version['LaunchTemplateData']['ImageId'];
            }
        }
        
        if($imageId){
            
            try{
                $imgResult = $this->ec2Client->describeImages([
                    'ImageIds' => [$imageId]
                ]);
            }catch(Exception $e){
                if($e->getAwsErrorCode() == 'InvalidAMIID.NotFound'){
                    $this->results['ASGAMIExist'] = [-1, ''];
                }
            }
        }
        
        return;
    }
    
}

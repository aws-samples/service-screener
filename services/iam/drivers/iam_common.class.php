<?php 

class iam_common extends evaluator{
    static function getAgeInDay($dateTime){
        return self::getAge($dateTime, 60*60*24);
    }
    
    static function getAgeInHour($dateTime){
        return self::getAge($dateTime, 60*60);   
    }
    
    static function getAge($dateTime, $div=60*60*24){
        if($dateTime == 'N/A')
            return 999;
        
        $resultDate = date_create($dateTime);
        
        $now = time();
        $datediff = $now - $resultDate->getTimestamp();
        return floor($datediff / $div);
    }
    
    function evaluateManagePolicy($policies){
        global $CONFIG;
        $cachePrefix = 'iam::mpolicy::';
        
        if(!empty($policies)){
            $policyWithFullAccess = [];
            $hasFullAccess = -1;    ## instead of false/true, easier handling on cache checking using !empty
            foreach($policies as $policy){
                if($policy['PolicyName'] == 'AdministratorAccess'){
                    $this->results['FullAdminAccess'] = [-1, 'AdministratorAccess'];  
                    continue;
                }
                
                $cache = $CONFIG->get($cachePrefix . $policy['PolicyArn'], "");
                if($cache == 1){
                    $hasFullAccess = 1;
                    $policyWithFullAccess[] = $policy['PolicyName'];
                    continue;
                }else{
                    $versInfo = $this->iamClient->getPolicy(['PolicyArn' => $policy['PolicyArn']]);   
                    $vers = $versInfo->get('Policy');
                    $verId = $vers['DefaultVersionId'];
                    
                    $detail = $this->iamClient->getPolicyVersion([
                        'PolicyArn' => $policy['PolicyArn'],
                        'VersionId' => $verId
                    ]);
                    
                    $doc = $detail->get('PolicyVersion');
                    $doc = urldecode($doc['Document']);
                    $pObj = new policy($doc);
                    
                    if($pObj->hasFullAccessToOneResource() == true){
                        $hasFullAccess = 1;
                        $policyWithFullAccess[] = $policy['PolicyName'];
                    }
                }
            }
            
            $CONFIG->set($cachePrefix . $policy['PolicyArn'], $hasFullAccess);
        }
        
        if(!empty($policyWithFullAccess)){
            $this->results['ManagedPolicyFullAccessOneServ'] = [-1, implode("<br>", $policyWithFullAccess)];
        }   
    }
    
    function evaluateInlinePolicy($inlinePolicies, $identifier, $entityType){
        if(!empty($inlinePolicies)){
            $this->results['InlinePolicy'] = [-1, implode("<br>", $inlinePolicies)];
            $inlinePoliciesWithAdminAccess = $inlinePoliciesWithFullAccess = [];
            foreach($inlinePolicies as $policy){
                if($entityType=='user'){
                    $resp = $this->iamClient->getUserPolicy(['PolicyName' => $policy, 'UserName' => $identifier]);
                }else if($entityType=='group'){
                    $resp = $this->iamClient->getGroupPolicy(['PolicyName' => $policy, 'GroupName' => $identifier]);  
                }else{
                    $resp = $this->iamClient->getRolePolicy(['PolicyName' => $policy, 'RoleName' => $identifier]);
                }
                
                $doc = $resp->get('PolicyDocument');
                $doc = urldecode($doc);
                
                $pObj = new policy($doc);
                $pObj->inspectAccess();
                if($pObj->hasFullAccessToOneResource() == true)
                    $inlinePoliciesWithFullAccess[] = $policy;
                    
                if($pObj->hasFullAccessAdmin() == true){
                    $inlinePoliciesWithAdminAccess[] = $policy;
                }
            }
            
            if(!empty($inlinePoliciesWithFullAccess)){
                $this->results['InlinePolicyFullAccessOneServ'] = [-1, implode("<br>", $inlinePoliciesWithFullAccess)];   
            }
            
            if(!empty($inlinePoliciesWithAdminAccess)){
                $this->results['InlinePolicyFullAdminAccess'] = [-1, implode("<br>", $inlinePoliciesWithAdminAccess)];   
            }
        }   
    }
}
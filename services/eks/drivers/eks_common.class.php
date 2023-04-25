<?php

class eks_common extends evaluator{
    const OUTBOUNDSGMINIMALRULES = [
        'tcp' => [10250, 53, 443],
        'udp' => [53]
    ];
    
    function __construct($cluster, $clusterInfo, $eksClient, $ec2Client, $iamClient){
        $this->cluster = $cluster;
        $this->clusterInfo = $clusterInfo;
        $this->eksClient = $eksClient;
        $this->ec2Client = $ec2Client;
        $this->iamClient = $iamClient;
        
        $this->init();
    }
    
    function getNewerVersionCnt($clusterVersion){
        $newerVersionCnt = 0;
        $versionList = $this->getVersions();
        foreach($versionList as $version){
            if($clusterVersion < $version){
                $newerVersionCnt++;
            }
        }
        
        return $newerVersionCnt;
    }
    
    function getVersions(){
        global $CONFIG;
        
        $versionList = $CONFIG->get('EKSVersionList', []);
        if(!$versionList){
            $versionList = [];
            $addonList = [];
            
            $results = $this->eksClient->describeAddonVersions();
            $addonList = $results->get('addons');
            
            while($results->get('nextToken')){
                $results = $this->eksClient->describeAddonVersions([
                    'nextToken' => $results.get('nextToken')
                ]);
                
                $addonList[] = $results->get('addons');
            }
            
            foreach($addonList as $addon){
                foreach($addon['addonVersions'] as $addonVersion){
                    foreach($addonVersion['compatibilities'] as $compatibility){
                        $versionList[] = $compatibility['clusterVersion'];
                    }
                }
            }
            
            $uniqVersionList = array_unique($versionList);
            rsort($uniqVersionList);
            
            $CONFIG->set('EKSVersionList', $uniqVersionList);
            return $uniqVersionList;
        }else{
            return $versionList;
        }
    }
    
    function getLatestVersion(){
        $versionList = $this->getVersions();
        return $versionList[0];
    }
    
    function __checkClusterVersion(){
        $version = $this->clusterInfo['version'];
        $newVersionCnt = $this->getNewerVersionCnt($version);
        $latestVersion = $this->getLatestVersion();
        
        if($newVersionCnt >= 3){
            $this->results['eksClusterVersionEol'] = [-1, "Current: " . $version . ", Latest: " . $latestVersion];
        }else if($newVersionCnt > 0 && $newVersionCnt < 3){
            $this->results['eksClusterVersionUpdate'] = [-1, "Current: " . $version . ", Latest: " . $latestVersion];
        }
        
        return;
    }
    
    function clusterSGInboundRuleCheck($rule, $sgID, $accountID){
        if (sizeof($rule['UserIdGroupPairs']) == 0){
            return false;
        }else{
            foreach($rule['UserIdGroupPairs'] as $group){
                if($group['GroupId'] != $sgID || $group['UserId'] != $accountID){
                    return false;
                }
            }
        }
        
        return true;
    }
    
    function clusterSGOutboundRuleCheck($rule, $sgID, $accountID){
        $minimalPort = self::OUTBOUNDSGMINIMALRULES;
        
        if(sizeof($rule['UserIdGroupPairs']) == 0){
            return false;
        }else{
            ## EKS Cluster SG Outbound minimal requirement is listed in the minimal port
            if(isset($minimalPort[$rule['IpProtocol']]) && in_array($rule['FromPort'],$minimalPort[$rule['IpProtocol']])){
                ## Check is the only self SG assigned into the rules
                foreach($rule['UserIdGroupPairs'] as $group){
                    if($group['GroupId'] != $sgID || $group['UserId'] != $accountID){
                        return false;
                    }
                }
            }else{
                return false;
            }
        }
        
        return true;
    }
    
    function __checkClusterSecurityGroup(){
        global $CONFIG;
        
        $stsInfo = $CONFIG->get('stsInfo', []);
        if(empty($stsInfo) || !isset($stsInfo['Account'])){
            __warn("Unable to get Account ID, skipped EKS Cluster Security Group Restriction check");
            return;
        }
        $accountID = $stsInfo['Account'];
        
        
        if(!isset($this->clusterInfo['resourcesVpcConfig']) || !isset($this->clusterInfo['resourcesVpcConfig']['clusterSecurityGroupId'])){
            __warn('Cluster security group not found for cluster ' . $this->cluster . '. skipped Cluster Security Group check');
            return;
        }
        $sgID = $this->clusterInfo['resourcesVpcConfig']['clusterSecurityGroupId'];
        
        $response = $this->ec2Client->describeSecurityGroups([
            'GroupIds' => [$sgID]    
        ]);
        $sgInfos = $response['SecurityGroups'];
        
        foreach($sgInfos as $info){
            $inboundRules = $info['IpPermissions'];
            foreach($inboundRules as $rule){
                $result = $this->clusterSGInboundRuleCheck($rule, $sgID, $accountID);
                if(!$result){
                    $this->results['eksClusterSGRestriction'] = [-1, $sgID];
                    return;
                }
            }
            
            $outboundRules = $info['IpPermissionsEgress'];
            foreach($outboundRules as $rule){
                $result = $this->clusterSGOutboundRuleCheck($rule,$sgID, $accountID);
                if(!$result){
                    $this->results['eksClusterSGRestriction'] = [-1, $sgID];
                    return;
                }
            }
        }
        return;
    }
    
    function __checkPublicClusterEndpoint(){
        if(!isset($this->clusterInfo['resourcesVpcConfig']) || !isset($this->clusterInfo['resourcesVpcConfig']['endpointPublicAccess'])){
            __warn("Unable to get cluster public endpoint config, skipped EKS Endpoint Public Access check");
            return;
        }
        
        if($this->clusterInfo['resourcesVpcConfig']['endpointPublicAccess']){
            $this->results['eksEndpointPublicAccess'] = [-1, 'Enabled'];
        }
        
        return;
    }
    
    function __checkEnvelopeEncryption(){
        if(!isset($this->clusterInfo['encryptionConfig'])){
            $this->results['eksSecretsEncryption'] = [-1, 'Disabled'];
        }
        return;
    }
    
    function __checkClusterLogging(){
        if(!isset($this->clusterInfo['logging']) || !isset($this->clusterInfo['logging']['clusterLogging'])){
            __warn("Unable to get cluster logging config, skipped EKS Cluster Logging check");
            return;
        }
        
        foreach($this->clusterInfo['logging']['clusterLogging'] as $logConfig){
            if(!$logConfig['enabled']){
                $this->results['eksClusterLogging'] = [-1, 'Disabled'];
                return;
            }
        }
        return;
    }
    
    function inlinePolicyLeastPrivilege($roleName){
        $response = $this->iamClient->listRolePolicies([
            'RoleName' => $roleName
        ]);
        
        foreach($response['PolicyNames'] as $policyName){
            $policyResp = $this->iamClient->getRolePolicy([
                'RoleName' => $roleName,
                'PolicyName' => $policyName
            ]);
            
            $document = $policyResp['PolicyDocument'];
            
            $pObj = new policy($document);
            if($pObj->hasFullAccessToOneResource() || $pObj->hasFullAccessAdmin()){
                return false;
            }
        }
        
        return true;
    }
    
    function attachedPolicyLeastPrivilege($roleName){
        $response = $this->iamClient->listAttachedRolePolicies([
            'RoleName' => $roleName
        ]);
        
        foreach($response['AttachedPolicies'] as $policy){
            $policyInfoResp = $this->iamClient->getPolicy([
                'PolicyArn' => $policy['PolicyArn']    
            ]);
            
            if(!isset($policyInfoResp['Policy'])){
                __warn("Skipped. Unable to retrieve policy information for " . $policy['PolicyArn']);
                continue;
            }
            $policyInfo = $policyInfoResp['Policy'];
            $policyArn = $policyInfo['Arn'];
            $policyVersion = $policyInfo['DefaultVersionId'];
            
            $policyResp = $this->iamClient->getPolicyVersion([
                'PolicyArn' => $policyArn,
                'VersionId' => $policyVersion
            ]);
            
            if(!isset($policyResp['PolicyVersion'])){
                __warn("Skipped. Unable to retrieve policy permission for " . $policy.['PolicyArn'] . " version " . $policyVersion);
                continue;
            }
            
            $document = $policyResp['PolicyVersion']['Document'];
            $pObj = new policy($document);
            if($pObj->hasFullAccessToOneResource() || $pObj->hasFullAccessAdmin()){
                return false;
            }
        }
        return true;
    }
    
    function __checkRoleLeastPrivilege(){
        $roleName = substr($this->clusterInfo['roleArn'], strpos($this->clusterInfo['roleArn'], 'role/') + 5);
        
        if(!$this->inlinePolicyLeastPrivilege($roleName) || !$this->attachedPolicyLeastPrivilege($roleName)){
            $this->results['eksClusterRoleLeastPrivilege'] = [-1, $roleName];
        }
        
        return;
    }
    
}
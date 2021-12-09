<?php

class ec2_sg extends evaluator{
    public $results = [];
    const PORTWITHOUTENCRYPT = [
        21,
        80
    ];
    
    function __construct($sg, $ec2Client){
        $this->sg = $sg;
        $this->ec2Client = $ec2Client;
        
        $this->init();
        
    }
    
    function hasPort($port, $fromPort, $toPort){
        if(($port > $fromPort && $port < $toPort) || ($port == $fromPort) || ($port == $toPort)){
            return true;
        }else{
            return false;
        }
    }
    
    function checkPortOpenAll($ruleName, $inProtocol, $inPort){
        if($inProtocol == 'both'){
            $protocolArr = ['tcp', 'eudp'];
        }else if($inProtocol == 'all'){
            $protocolArr = [-1];
        }else{
            $protocolArr = [$inProtocol];
        }
        
        $group = $this->sg;
        
        foreach($group['IpPermissions'] as $perm){
            foreach($protocolArr as $protocol){
                if($perm['IpProtocol'] == $protocol){
                    if($protocol == -1){
                        foreach($perm['IpRanges'] as $range){
                            if($range['CidrIp'] == '0.0.0.0/0'){
                                $this->results[$ruleName] = [-1, $range['CidrIp']];
                                return;
                            }
                        }
                    }else{
                        foreach($inPort as $port){
                            if($this->hasPort($port, $perm['FromPort'], $perm['ToPort'])){
                                foreach($perm['IpRanges'] as $range){
                                    if($range['CidrIp'] == '0.0.0.0/0'){
                                        $this->results[$ruleName] = [-1, $range['CidrIp']];
                                        return;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        $this->results[$ruleName] = [1];
        return;
    }
    
    function checkAllPortOpen($ruleName, $inProtocol){
        if($inProtocol == 'all'){
            $inProtocol = -1;
        }
        
        $group = $this->sg;
        
        foreach($group['IpPermissions'] as $perm){
            if($perm['IpProtocol'] == $inProtocol){
                if($inProtocol == -1){
                    $this->results[$ruleName] = [-1, $perm['IpProtocol']];
                    return;
                }else{
                    if($perm['FromPort'] == 0 && $perm['ToPort'] == 65535){
                        $this->results[$ruleName] = [-1, $perm['IpProtocol']];
                        return;
                    }
                }
            }
        }
        
        
        $this->results[$ruleName] = [1];
        return;
        
    }
    
    function __checkDefaultSGInUsed(){
        $group = $this->sg;
        
        if($group['GroupName'] == 'default'){
            $this->results['SGDefaultInUsed'] = [-1, $group['GroupName']];
            return;
        }
        
        
        $this->results['SGDefaultInUsed'] = [1, $group['GroupName']];
        return;
    }
    
    function __checkSensitivePortOpenToAll(){
        $ruleName = 'SGSEnsitivePortOpenToAll';
        
        ## DNS Port
        $protocol = 'both';
        $port = [53];
        $this->checkPortOpenAll($ruleName, $protocol, $port);
        
        ## MongoDB Port
        $protocol = 'tcp';
        $port = [27017, 27018, 27019];
        $this->checkPortOpenAll($ruleName, $protocol, $port);
        
        ## MSSQL Port
        $protocol = 'tcp';
        $port = [1433];
        $this->checkPortOpenAll($ruleName, $protocol, $port);
        
        ## MySQL Port
        $protocol = 'tcp';
        $port = [3306];
        $this->checkPortOpenAll($ruleName, $protocol, $port);
        
        ## NFS Port
        $protocol = 'tcp';
        $port = [2049];
        $this->checkPortOpenAll($ruleName, $protocol, $port);
        
        ## Oracle DB Port
        $protocol = 'tcp';
        $port = [1521];
        $this->checkPortOpenAll($ruleName, $protocol, $port);
        
        ## PostgreSQL Port
        $protocol = 'tcp';
        $port = [5432];
        $this->checkPortOpenAll($ruleName, $protocol, $port);
        
        ## RDP Port
        $protocol = 'tcp';
        $port = [3389];
        $this->checkPortOpenAll($ruleName, $protocol, $port);
        
        ## SMTP Port
        $protocol = 'tcp';
        $port = [25];
        $this->checkPortOpenAll($ruleName, $protocol, $port);
        
        ## SMTPS Port
        $protocol = 'tcp';
        $port = [465];
        $this->checkPortOpenAll($ruleName, $protocol, $port);
        
        ## SSH Port
        $protocol = 'tcp';
        $port = [22];
        $this->checkPortOpenAll($ruleName, $protocol, $port);
        
        return;
    }
    
    function __checkTCPAllOpen(){
        $ruleName = 'SGTCPAllOpen';
        $portocol = 'tcp';
        $this-> checkAllPortOpen($ruleName, $portocol);
        
        return;
    }
    
    function __checkUDPOpenAll(){
        $ruleName = 'SGUDPAllOpen';
        $portocol = 'udp';
        $this-> checkAllPortOpen($ruleName, $portocol);
        
        return;
    }
    
    function __checkAllOpen(){
        $ruleName = 'SGAllOpen';
        $protocol = 'all';
        $this-> checkAllPortOpen($ruleName, $protocol);
        
        return;
    }
    
    function __checkAllOpenAll(){
        $port = [-1];
        $protocol = 'all';
        $ruleName = 'SGAllOpenToAll';
        $this->checkPortOpenAll($ruleName, $protocol, $port);
        
        return;
    }
    
    function __checkEncryptionInTransit(){
        $group = $this->sg;
        $ruleName = 'SGEncryptionInTransit';
        
        foreach($group['IpPermissions'] as $perm){
            if($perm['IpProtocol'] == '-1'){
                $this->results[$ruleName] = [-1, $perm['IpProtocol']];
                return;
            }
            
            $fromPort = $perm['FromPort'];
            $toPort = $perm['ToPort'];
            
            foreach(self::PORTWITHOUTENCRYPT as $prohibitPort){
                if($fromPort == $prohibitPort || $toPort == $prohibitPort || ($fromPort < $prohibitPort && $toPort > $prohibitPort)){
                    $this->results[$ruleName] = [-1, $prohibitPort];
                    return;
                }
            }
        }
        
        $this->results[$ruleName] = [1, ''];
        
        return;
    }
    
    function __checkSGRulesNumber(){
        $group = $this->sg;
        
        $ruleNum = sizeof($group['IpPermissions']) + sizeof($group['IpPermissionsEgress']);
        if($ruleNum >= 50){
            $this->results['SGRuleNumber'] = [-1, $ruleNum];
        }
        
        return;
    }
}
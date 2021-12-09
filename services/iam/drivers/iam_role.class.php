<?php

class iam_role extends iam_common{
    const MAXSESSIONDURATION = 3600;
    const MAXROLENOTUSEDDAYS = 14;
    function __construct($role, $iamClient){
        $this->role = $role;
        $this->iamClient = $iamClient;
        $this->__configPrefix = 'iam::role::';

        $this->init();
        $this->retrieveRoleDetail();
    }
    
    function retrieveRoleDetail(){
        $c = $this->iamClient;
        $result = $c->getRole(['RoleName' => $this->role['RoleName']]);
        
        $detail = $result->get('Role');
        $this->role['RoleLastUsed'] = $detail['RoleLastUsed'];
    }
    
    function __checkRoleOldAge(){
        $c = $this->iamClient;
        $now = new DateTime(Date('Y-m-d'));
        
        if(empty($this->role['RoleLastUsed']) || empty($this->role['RoleLastUsed']['LastUsedDate'])){
            $cdate = $this->role['CreateDate'];
            $diff = $cdate->diff($now);
            $days = $diff->format('%a');
            
            if($days > self::MAXROLENOTUSEDDAYS)
                $this->results['unusedRole'] = [-1, "<b>$days</b> days passed"];
                
            return;
        }
        
        $lastDate = $this->role['RoleLastUsed']['LastUsedDate'];
        $diff = $lastDate->diff($now);
        $days = $diff->format('%a');
        
        if($days > 30)
            $this->results['unusedRole'] = [-1, $days . ' days'];
    }
    
    function __checkLongSessionDuration(){
        if($this->role['MaxSessionDuration'] > self::MAXSESSIONDURATION)
            $this->results['roleLongSession'] = [-1, $this->role['MaxSessionDuration']];
    }
    
    function __checkRolePolicy(){
        $role = $this->role['RoleName'];
        ## Managed Policy   
        $resp = $this->iamClient->listAttachedRolePolicies(['RoleName' => $role]);
        $policies = $resp->get('AttachedPolicies');
        $this->evaluateManagePolicy($policies, 'role'); ## code in iam_common.class.php
        
        ## Inline Policy
        $resp = $this->iamClient->listRolePolicies(['RoleName' => $role]);
        $inlinePolicies = $resp->get('PolicyNames');
        $this->evaluateInlinePolicy($inlinePolicies, $role, 'role'); ## code in iam_common.class.php   
    }
}
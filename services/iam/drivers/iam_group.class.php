<?php

class iam_group extends iam_common{
    public $results = [];
    function __construct($group, $iamClient){
        $this->group = $group;
        $this->iamClient = $iamClient;
        $this->__configPrefix = 'iam::group::';

        $this->init();
    }
    
    function __checkGroupHasUsers(){
        $group = $this->group['GroupName'];
        $resp = $this->iamClient->getGroup(['GroupName' => $group]);
        
        $users = $resp->get('Users');
        if(sizeof($users) == 0){
            $this->results['groupEmptyUsers'] = [-1, 'No users'];   
        }
    }
    
    function __checkGroupPolicyPermission(){
        global $CONFIG;
        $group = $this->group['GroupName'];
        
        ##
        $results = $this->iamClient->listAttachedGroupPolicies(['GroupName' => $group]);
        $policies = $results->get('AttachedPolicies');
        $this->evaluateManagePolicy($policies); ## code in iam_common.class.php
        
        ## Group Inline Policy
        $resp = $this->iamClient->listGroupPolicies(['GroupName' => $group]);
        $inlinePolicies = $resp->get('PolicyNames');
        $this->evaluateInlinePolicy($inlinePolicies, $group, 'group'); ## code in iam_common.class.php

    }
}
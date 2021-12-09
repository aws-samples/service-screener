<?php
## https://docs.aws.amazon.com/aws-sdk-php/v3/api/class-Aws.Iam.IamClient.html
class iam_user extends iam_common{
    public $results = [];
    const ENUM_NO_INFO = ['not_supported', 'no_information'];
    
    function __construct($user, $iamClient){
        $this->user = $user;
        $this->iamClient = $iamClient;
        $this->__configPrefix = 'iam::user::';

        $this->init();
    }
    
    function __checkHasMFA(){
        $key = $this->user['user'] == '<root_account>' ? 'rootMfaActive' : 'mfaActive';
        if($this->user['mfa_active'] === 'false'){
            $this->results[$key] = [-1, 'Inactive'];
        }
    }
    
    function __checkConsoleLastAccess(){
        $key = '';
        
        if( in_array($this->user['password_last_used'], self::ENUM_NO_INFO))
            return;
        
        $daySinceLastAccess = self::getAgeInDay($this->user['password_last_used']);
        
        $key = $daySinceLastAccess > 365 ? "consoleLastAccess365"
                : ($daySinceLastAccess > 90 ? "consoleLastAccess90" : false);
        
        if($key !== false)
            $this->results[$key] = [-1, $daySinceLastAccess];
    }
    
    function __checkPasswordLastChange(){
        if( in_array($this->user['password_last_changed'], self::ENUM_NO_INFO))
            return;
        
        $daySinceLastChange = self::getAgeInDay($this->user['password_last_changed']);
        
        $key = $daySinceLastChange > 365 ? "passwordLastChange365" 
                : ($daySinceLastChange > 90 ? "passwordLastChange90" : false);
        
        if($key !== false)
            $this->results[$key] = [-1, $daySinceLastChange];
    }
    
    function __checkUserInGroup(){
        $user = $this->user['user'];
        if($user == '<root_account>')
            return; 
        
        $resp = $this->iamClient->listGroupsForUser(['UserName' => $user]);
        $groups = $resp->get('Groups');
        if(empty($groups)){
            $this->results['userNotUsingGroup'] = [-1, '-'];   
        }
    }
    
    function __checkUserPolicy(){
        global $CONFIG;
        $user = $this->user['user'];
        if($user == '<root_account>')
            return; 
            
        ## Managed Policy   
        $resp = $this->iamClient->listAttachedUserPolicies(['UserName' => $user]);
        $policies = $resp->get('AttachedPolicies');
        $this->evaluateManagePolicy($policies); ## code in iam_common.class.php
        
        ## Inline Policy
        $resp = $this->iamClient->listUserPolicies(['UserName' => $user]);
        $inlinePolicies = $resp->get('PolicyNames');
        $this->evaluateInlinePolicy($inlinePolicies, $user, 'user'); ## code in iam_common.class.php
    }
}
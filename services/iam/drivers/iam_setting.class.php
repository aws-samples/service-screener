<?php

class iam_setting extends evaluator{
    public $results = [];
    const PASSWORD_POLICY_MIN_SCORE = 4;
    
    function __construct($none, $iamClient){
        $this->iamClient = $iamClient;
        $this->__configPrefix = 'iam::settings::';

        $this->init();
    }
    
    function passwordPolicyScoring($policies){
        $score = 0;
        foreach($policies as $policy => $value){
            ## no score for this:
            if(in_array($policy, ['AllowUsersToChangePassword', 'ExpirePasswords']))
                continue;
            
            if($policy == 'MinimumPasswordLength' && $value >= 8){
                $score++;
                continue;
            }
            
            if($policy == 'MaxPasswordAge' && $value <= 90){
                $score++;
                continue;
            }
            
            if($policy == 'PasswordReusePrevention' && $value >= 3){
                $score++;
                continue;
            }
            
            if(!empty($value) && $value > 0)
                $score++;
        }
        
        return $score;
    }
    
    function __checkPasswordPolicy(){
        try{
            $resp = $this->iamClient->getAccountPasswordPolicy();
            $policies = $resp->get('PasswordPolicy');
            
            $score = $this->passwordPolicyScoring($policies);
            
            $currVal = [];
            if($score <= self::PASSWORD_POLICY_MIN_SCORE){
                foreach($policies as $policy => $num)
                    $currVal[]= "$policy=$num";   
                
                $this->results['passwordPolicyWeak'] = [-1, implode("<br>", $currVal)];
            }
            
        }catch(Aws\Iam\Exception\IamException $e){
            __info($e->getAwsErrorCode()); 
            if($e->getAwsErrorCode() == 'NoSuchEntity'){
                $this->results['passwordPolicy'] = [-1, $e->getAwsErrorCode()];   
            }
        }
    }
}
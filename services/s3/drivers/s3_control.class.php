<?php

class s3_control extends evaluator{
    public $results = [];
    
    
    function __construct($s3Control){
        $this->s3Control = $s3Control;
        $this->__configPrefix = 's3Control::';

        $this->init();
    }
    
    function __checkAccountPublicAccessBlock(){
        global $CONFIG;
        $this->results['S3AccountPublicAccessBlock'] = [-1,'Off'];
        try {
            $stsInfo = $CONFIG->get('stsInfo');
            if (empty($stsInfo)) {
                __warn("Unable to retrieve account information");
                $this->results['S3AccountPublicAccessBlock'] = [-1,'Insufficient info'];
                return;
            }
        } catch (exception $e) {
            __warn("Unable to retrieve account information");
            $this->results['S3AccountPublicAccessBlock'] = [-1,'Insufficient info'];
        }
        $resp = $this->s3Control->getPublicAccessBlock([
            'AccountId' => $stsInfo['Account']
        ]);
        #__pr($resp);
        // $param = $resp['PublicAccessBlockConfiguration'];
        // __pr($param);
        foreach ($resp['PublicAccessBlockConfiguration'] as $param)
            if($param != 1)
                return;
        $this->results['S3AccountPublicAccessBlock'] = [1,'On']; # TODO: how to return this?
    }
}
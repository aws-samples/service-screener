<?php

class s3_s3 extends evaluator{
    public $results = [];
    
    
    function __construct($bucket, $s3Client){
        $this->bucket = $bucket;
        $this->s3Client = $s3Client;
        $this->__configPrefix = 's3::';

        $this->init();
    }   
    
    function __checkEncrypted(){
        $this->results['ServerSideEncrypted'] = [1, 'On'];
        try{
            $resp = $this->s3Client->getBucketEncryption([
                'Bucket' => $this->bucket 
            ]);
        }catch(Aws\S3\Exception\S3Exception $e){
            #__pr($e->getAwsErrorMessage());   
            #__pr($e->getAwsErrorType());   
            #__pr($e->getAwsErrorCode());   
            #__pr($e->getAwsErrorMessage());   
            
            if($e->getAwsErrorCode() == 'ServerSideEncryptionConfigurationNotFoundError')
                $this->results['ServerSideEncrypted'] = [-1, 'Off'];
        }
        // __pr($this->results['ServerSideEncrypted']);
    }
    
    function __checkPublicAcessBlock(){
        $this->results['PublicAccessBlock'] = [-1,'Off'];
        try{
            $resp = $this->s3Client->getPublicAccessBlock([
                'Bucket' => $this->bucket
            ]);
        // __pr($resp);
        foreach ($resp['PublicAccessBlockConfiguration'] as $param)
            if($param != 1)
                return;
        }catch(Aws\S3\Exception\S3Exception $e){
            // __pr($e->getAwsErrorCode());
            if($e->getAwsErrorCode() == 'NoSuchPublicAccessBlockConfiguration')
                return;
        }
        $this->results['PublicAccessBlock'] = [1,'On'];
        // __pr($this->results['PublicAccessBlock']);
    }
    
    function __checkMfaDelete(){
        $this->results['MFADelete'] = [-1, 'Off'];
        $resp = $this->s3Client->getBucketVersioning([
            'Bucket' => $this->bucket
        ]);
        if($resp->get('Status')=="MFADelete")
            $this->results['MFADelete'] = [1, 'On'];
    }
    
    function __checkVersioning(){
        $this->results['BucketVersioning'] = [-1, 'Off'];
        $resp = $this->s3Client->getBucketVersioning([
            'Bucket' => $this->bucket
        ]);
        if($resp->get('Status')=="Enabled")
          $this->results['BucketVersioning'] = [1, 'On'];
    }
                    
    function __checkObjectLock(){
        $this->results['ObjectLock'] = [1, 'On'];
        try{
            $resp = $this->s3Client->getObjectLockConfiguration([
            'Bucket' => $this->bucket
            ]);
        } catch(Aws\S3\Exception\S3Exception $e){  
            if($e->getAwsErrorCode() == 'ObjectLockConfigurationNotFoundError')
                $this->results['ObjectLock'] = [-1, 'Off'];
        }
    }
    
    function __checkBucketReplication(){
        $this->results['BucketReplication'] = [1,'On'];
        try{
            $resp = $this->s3Client->getBucketReplication([
            'Bucket' => $this->bucket
            ]);
        } catch(Aws\S3\Exception\S3Exception $e){
            if($e->getAwsErrorCode() == 'ReplicationConfigurationNotFoundError')
                $this->results['BucketReplication'] = [-1,'Off'];
        }
    }
    
    function __checkLifecycle() {
        $this->results['BucketLifecycle'] = [1,'On'];
        try{
            $resp = $this->s3Client->getBucketLifecycle([
            'Bucket' => $this->bucket
            ]);
        } catch(Aws\S3\Exception\S3Exception $e){
            if($e->getAwsErrorCode() == 'NoSuchLifecycleConfiguration')
                $this->results['BucketLifecycle'] = [-1,'Off'];
        }
    }
    
    function __checkLogging(){
        $this->results['BucketLogging'] = [1,'On'];
        $resp = $this->s3Client->getBucketLogging([
            'Bucket' => $this->bucket
        ]);
        $ele = $resp->get('LoggingEnabled');
        if(!$ele)
            $this->results['BucketLogging'] = [-1,'Off'];
    }
    
    #TODO: try async
    function __checkIntelligentTiering() { # might take a while to run because iterates through each object. Maybe run async?
        $this->results['ObjectsInIntelligentTier'] = [1,'On']; # TODO: need to add "insufficient information" besides "success" and failure"
        $resp = $this->s3Client->listObjects([
            'Bucket' => $this->bucket,
            'MaxKeys' => 1000
        ]);
        if (empty($resp->get('Contents'))) 
            return;
        foreach ($resp->get('Contents') as $object) {
            if($object['StorageClass'] != "INTELLIGENTTIERING") {
                $this->results['ObjectsInIntelligentTier'] = [-1,'Off'];
                return; #TODO: check if this breaks the iteration over buckets
            }
        }
    }
    
    function __checkTls(){
        $this->results['TlsEnforced'] = [-1,'Off'];
        try {
            $resp = $this->s3Client->getBucketPolicy([
            'Bucket' => $this->bucket
            ]);
            # __pr(gettype($resp));
            $policy = json_decode($resp->get('Policy'));
            #__pr($policy);
            // __pr($policy->Statement);
            // __pr($policy->Statement);
            foreach ($policy->Statement as $obj) { # TODO: check how to make cleaner
                #__pr($obj->Condition);
                if(isset($obj->Condition) && $obj->Effect == "Deny")
                    foreach ($obj->Condition as $cond)
                        if($cond->{'aws:SecureTransport'} == "false") 
                            $this->results['TlsEnforced'] = [1,'On'];
            }
        } catch (Aws\S3\Exception\S3Exception $e) {
            return;
        }
    }
    
    // function __checkVersionAchiving() { # TODO: GLACIER check if needed & best practices
    //     try {
    //         $resp = $this->s3Client->getBucketLifecycleConfiguration([
    //         'Bucket' => $this->bucket
    //         ]);
    //         foreach ($resp->get('Rules') as $rule) {
    //             __pr($rule);
    //         }
    //         // __pr($resp);
    //         __pr('HELP');
    //     } catch (Aws\S3\Exception\S3Exception $e) {
    //         // $it = 
    //         __pr($e->getAwsErrorCode());
    //     }
    // }
    
    // function __checkMacie() {} # should be separate service; ignore for now

    
    // function __checkEndpoint(){} # put in EC2_VPC
}
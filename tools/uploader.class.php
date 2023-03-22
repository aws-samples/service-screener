<?php

use Aws\S3\S3Client;
use AWS\S3\Exception;


class Uploader {
    private $s3Client;
    private $bucket;
    private $region;

    public function __construct($region, $bucket) {
        $this->region = $region;
        $this->bucket = $bucket;

        $this->__AWS_OPTIONS['version'] = CONFIG::AWS_SDK['S3CLIENT_VERS'];
        $this->__AWS_OPTIONS['region'] = $region;
        $this->s3Client = new S3Client($this->__AWS_OPTIONS);

        if (!$this->_checkForBucket()) {
            $this->_createBucket();
        }
    }

    function _createBucket(): bool {
        try {
            $this->s3Client->createBucket([
                'Bucket' => $this->bucket,
                'LocationConstraint' => $this->region
            ]);
            return true;
        } catch (Exception $e) {
            __info('Amazon S3 create bucket is getting following error');
            __info($e->getAwsErrorMessage());
            return false;
        }
    }

    function _enableStaticWebsite() {
        $this->s3Client->putBucketWebsite([
            'Bucket' => $this->bucket,
            'WebsiteConfiguration' => [
                'IndexDocument' => [
                    'Suffix' => 'index.html'
                ]
            ]
        ]);
    }

    function _checkForBucket() {
        $buckets = $this->s3Client->listBuckets();
        $buckets = $buckets['Buckets'];

        foreach ($buckets as $bucket) {
            if ($bucket['Name'] == $this->bucket) {
                return true;
            }
        }

        return false;
    }

    function uploadFromFolder($folder): bool {
        try {
            $this->s3Client->uploadDirectory($folder, $this->bucket, null);

            // S3 list all objects and set ACL
            $objects = $this->s3Client->getIterator('ListObjects', [
                'Bucket' => $this->bucket
            ]);
            foreach ($objects as $object) {
                $this->s3Client->putObjectAcl([
                    'Bucket' => $this->bucket,
                    'Key' => $object['Key'],
                    'ACL' => 'public-read'
                ]);
            }
            $this->_enableStaticWebsite();

            return true;
        } catch (Exception $e) {
            __info('Upload failed. Amazon S3 upload is getting following error');
            __info($e->getAwsErrorMessage());
            return false;
        }
    }
    
    static function getConfirmationToUploadToS3($bucket): bool{
        $uploadToS3 = false;
        if ($bucket) {
            __info("You have specified a 'bucket' parameter, the report will be uploaded to S3.");
            __info("The report will be available through public internet, please ensure you understand the risk of exposing the report to the internet. You will be fully RESPONSIBLE on this data.");
    
            $attempt = 0;
            do {
                if($attempt > 0)
                    __warn("You have entered an invalid option. Please try again.");
                
                $confirm = strtolower(readline("Please enter 'y' for yes, 'n' for no, or 'c' to continue without uploading the report to S3 : "));    
                $attempt++;
            } while(!in_array($confirm, ['y', 'n', 'c']));
        
            if ($confirm == 'y') {
                $uploadToS3 = true;
            }
            
            if ($confirm == 'n') {
                __info("You have chosen not to upload the report to S3.");
            }
        
            if ($confirm == 'c') {
                __info("You have chosen not to upload the report to S3. Continuing...");
            }
        }
        
        return $uploadToS3;
    }
}
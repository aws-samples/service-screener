<?php

use Aws\S3\S3Client;
use AWS\S3\Exception;


class Uploader {
    private $s3Client;
    private $bucket;

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

    function _createBucket() {
        $this->s3Client->createBucket([
            'Bucket' => $this->bucket,
            'LocationConstraint' => $this->region
        ]);
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

    function uploadZip($file) {
        $zip = new ZipArchive;
        $res = $zip->open($file);

        if ($res === TRUE) {
            $zip->extractTo('/tmp');
            $zip->close();
            
            try {
                $this->s3Client->uploadDirectory('/tmp/html', $this->bucket, null);

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
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        } else {
            echo 'failed, code:' . $res;
        }
    }
}
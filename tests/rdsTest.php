<?php 
include_once(__DIR__ .'/../vendor/autoload.php');

use Aws\RDS\RDSClient;

$client = new RDSClient([
    'profile' => 'ss',
    'region' => 'ap-southeast-1',
    'version' => 'latest'
]);
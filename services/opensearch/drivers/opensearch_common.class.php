<?php

class opensearch_common extends evaluator{
    public $results = [];
    const NODES_LIMIT = 200;
    
    
    function __construct($domain, $opensearchClient){
        $this->domain = $domain;
        $this->opensearchClient = $opensearchClient;
        $this->__configPrefix = 'openSearch::';
        
        $this->attribute = $this->opensearchClient->describeDomain(
            ['DomainName' => $this->domain]
        );
        $this->cluster_config = $this->attribute['DomainStatus']['ClusterConfig'];
        $this->domain_config = $this->opensearchClient->describeDomainConfig(
            ['DomainName' => $this->domain]
        );
        $this->aos_versions = $this->opensearchClient->listVersions([
            'MaxResults' => 11,
        ]);
        $this->latest_version = $this->aos_versions['Versions'][0];

        $this->engine_version = $this->attribute['DomainStatus']['EngineVersion'];

        $this->instance_type_details = $this->opensearchClient->listInstanceTypeDetails([
            'EngineVersion' => $this->engine_version // REQUIRED
        ]);

        foreach($this->instance_type_details["InstanceTypeDetails"] as $idx => $details){
            $this->instance_type_list[$idx] = $details['InstanceType'];
        }

        $this->init();
    }
    
    // function __checkMock(){
    //     # print_r($this->domain);
    //     $this->results['MockTest'] = [1, 'mockTest'];
    // }
    function __getCloudWatchData($metric, $statistics = ['Average'], $timeAgo = '-5 minutes', $period = 300){
        global $CONFIG;
        global $CW;
        
        $stsInfo = $CONFIG->get('stsInfo');
        $clientId = $stsInfo['Account'];
        
        $cwClient = $CW->getClient();
        $dimensions = [
            [
                'Name' => 'ClientId',
                'Value'=> $clientId
            ],
            [
                'Name' => 'DomainName',
                'Value'=> $this->domain
            ]
        ];
        
        $stats = $cwClient->getMetricStatistics([
            'Dimensions' => $dimensions,
            'Namespace' => 'AWS/ES',
            'MetricName' => $metric,
            'StartTime' => strtotime($timeAgo),
            'EndTime' => strtotime('now'),
            'Period' => $period,
            'Statistics' => $statistics,
            # 'Unit' => 'None'
        ]);
        
        return $stats;
    }
    
    function __checkMasterNodes(){
        $enabled = $this->cluster_config['DedicatedMasterEnabled'];
        $nodes = $this->cluster_config['DedicatedMasterCount'];
        $this->results['DedicatedMasterNodes'] = [-1, 'No dedicated master nodes'];
        if($enabled){
            if($nodes < 3 )
                $this->results['DedicatedMasterNodes'] = [-1, 'Insufficient dedicated master nodes'];
                return;
            if($nodes%2 == 0)
                $this->results['DedicatedMasterNodes'] = [-1, 'Wrong number of dedicated master nodes'];
                return;
            $this->results['DedicatedMasterNodes'] = [1, 'Sufficient dedicated master nodes'];
        }
    }

    function __checkAvailabilityZones(){
        $enabled = $this->cluster_config['ZoneAwarenessEnabled'];
        $this->results['AvailabilityZones'] = [-1, 'Multi-AZ not enabled'];
        if($enabled){
            $this->results['AvailabilityZones'] = [1, 'Multi-AZ enabled'];
        }
    }

    function __checkServiceSoftwareVersion(){
        $this->results['ServiceSoftwareVersion'] = [1, 'Latest'];
        $resp = $this->attribute['DomainStatus']['ServiceSoftwareOptions']['UpdateAvailable'];
        if($resp)
            $this->results['ServiceSoftwareVersion'] = [-1, 'Upgrade to latest version'];
    }

    function __checkEngineVersion(){
        $this->results['EngineVersion'] = [1, 'Latest'];
        if ($this->engine_version != $this->latest_version)
            $this->results['EngineVersion'] = [-1, 'Later Engine Versions Available'];
    }

    // Open access is already restricted unconditionally. 
    // TODO: Look for ways/patterns to further restrict access policy

    // function __checkResourceAccessPolicy(){
        
        // $this->results['ResourceAccessPolicy'] = [-1, 'Allow All'];
        // $resp = $this->opensearchClient->describeDomainConfig([
        //     'DomainName' => $this->domain,
        // ]);
        // // $source = $resp['CompatibleVersions'][0]['SourceVersion'];
        // $target = $resp['CompatibleVersions'][0]['TargetVersions'];
        // // __pr($target);
        // // $version = $this->attribute['DomainStatus']['EngineVersion'];
        // if (count($target) == 0) {
        //     $this->results['ServiceSoftwareVersion'] = [1, 'Latest'];
        //     // __pr('Latest sw running');
        // } else {
        //     $this->results['ServiceSoftwareVersion'] = [-1, 'Upgrade to latest version'];
        //     // __pr('Later versions of sw available');
        // }
    // }

    function __checkFineGrainedAccessControl(){
        $this->results['FineGrainedAccessControl'] = [-1, 'Not enabled'];
        $resp = $this->attribute['DomainStatus']['AdvancedSecurityOptions']['Enabled'];
        if($resp)
            $this->results['FineGrainedAccessControl'] = [1,'Enabled'];
    }

    function __checkDomainWithinVPC(){
        $this->results['DomainWithinVPC'] = [1, 'Within VPC'];
        if(empty($this->attribute['DomainStatus']['VPCOptions']))
            $this->results['DomainWithinVPC'] = [-1, 'Public'];
    }

    function __checkInstanceVersion(){        
        $instanceType = $this->cluster_config['InstanceType'];
        $this->results['LatestInstanceVersion'] = [1, $instanceType];

        $instInfo = __aws_parseInstanceFamily($instanceType);

        $instancePrefixArr = $instInfo['prefixDetail'];
        $instancePrefixArr['version']++;
        $size = $instInfo['suffix'];
        $latestInstance = $instancePrefixArr['family'] . $instancePrefixArr['version'] . $instancePrefixArr['attributes'] .$size . '.search';

        if (in_array($latestInstance , $this->instance_type_list)) {
            $this->results['LatestInstanceVersion'] = [-1, $instanceType];
        }
    }

    function __checkTSeriesForProduction(){
        $instanceType = $this->cluster_config['InstanceType'];
        $typeArr = explode('.', $instanceType);
        $family = $typeArr[0];
        $familyChar = str_split($family);
        $this->results['TSeriesForProduction'] = [1, $instanceType];
        if ($familyChar[0] == 't') {
            $this->results['TSeriesForProduction'] = [-1, $instanceType];
        } 
    }

    function __checkEncyptionAtRest(){
        $this->results['Encryption at rest'] = [-1, 'Disabled'];
        if ($this->attribute['DomainStatus']['EncryptionAtRestOptions']['Enabled']) {
            $this->results['Encryption at rest'] = [1, 'Enabled'];
        }
    }

    function __checkNodeToNodeEncryption(){
        $this->results['Node to node encryption'] = [-1, 'Disabled'];
        if ($this->attribute['DomainStatus']['NodeToNodeEncryptionOptions']['Enabled']) {
            $this->results['Node to node encryption'] = [1, 'Enabled'];
        }
    }

    function __checkSearchSlowLogs(){
        $this->results['Search Slow logs'] = [-1, 'Disabled'];
        if (isset($this->attribute['DomainStatus']['LogPublishingOptions']['SEARCH_SLOW_LOGS'])) {
            $this->results['Search Slow logs'] = [1, 'Enabled'];
        }
    }

    function __checkAutoTune(){
        $this->results['Autotune'] = [-1, 'Disabled'];
        if ($this->attribute['DomainStatus']['AutoTuneOptions']['State'] == 'ENABLED') {
            $this->results['Autotune'] = [1, 'Enabled'];
        }
    }

    function __checkUltrawarmEnabled(){
        $this->results['UltraWarm'] = [-1, 'Disabled'];
        if ($this->cluster_config['WarmEnabled']) {
            $this->results['UltraWarm'] = [1, 'Enabled'];
        }
    }

    function __checkColdStorage(){
        $this->results['ColdStorage'] = [-1, 'Disabled'];
        if ($this->cluster_config['ColdStorageOptions']) {
            $this->results['ColdStorage'] = [1, 'Enabled'];
        }
    }

    function __checkEBSStorageUtilisation(){
        $metric = "FreeStorageSpace";
        $stats = $this->__getCloudWatchData($metric);
        
        $dp = $stats->get('Datapoints');
        $free_space = $dp[0]['Average'];
        try {
            $ebs_vol_size = $this->domain_config['DomainConfig']['EBSOptions']['Options']['VolumeSize'];
        } catch(exception $e) {
            __warn("Not EBSEnabled");
            $this->results['EBSStorageUtilisation'] = [-1,'Not EBSEnabled'];
            return;
        }
        if($free_space < 0.25*($ebs_vol_size*1000)){
            $this->results['EBSStorageUtilisation'] = [-1, $free_space.' out of '.$ebs_vol_size*1000 .' remaining'];
            return;
        }
        $this->results['EBSStorageUtilisation'] = [1, $free_space.' out of '.$ebs_vol_size*1000 .' remaining'];
    }    

    function __checkClusterStatus(){
        $metrics = array("ClusterStatus.red","ClusterStatus.yellow","ClusterStatus.green");
    
        foreach ($metrics as $metric) {
            $stats = $this->__getCloudWatchData($metric);
            $dp = $stats->get('Datapoints');
            if($dp && $metric=='ClusterStatus.green'){
                $this->results['ClusterStatus'] = [1,$metric];
            }elseif($dp){
                $this->results['ClusterStatus'] = [-1,$metric];
            }
        }
    }

    function __checkReplicaShard(){
        $this->results['ReplicaShard'] = [1, 'Enabled'];
        
        $active = 'Shards.active';
        $primary = 'Shards.activePrimary';
        
        $stats_active = $this->__getCloudWatchData($active);
        $dp_active = $stats_active->get('Datapoints')[0]['Average'];

        $stats_primary = $stats = $this->__getCloudWatchData($primary);
        $dp_primary = $stats_primary->get('Datapoints')[0]['Average'];

        if ($dp_active - $dp_primary){
            $this->results['ReplicaShard'] = [1, 'Enabled'];
        }
    }

    function __checkMasterNodeType(){   

        $map = [
            [
                'instance_count' => [
                    'min' => 1,
                    'max' => 10
                ],
                'type' => [
                    'min_vcpu' => 8,
                    'min_memoryInGiB' => 16
                ]
            ],
            [
                'instance_count' => [
                    'min' => 11,
                    'max' => 30
                ],
                'type' => [
                    'min_vcpu' => 2,
                    'min_memoryInGiB' => 8
                ]
            ],
            [
                'instance_count' => [
                    'min' => 31,
                    'max' => 75
                ],
                'type' => [
                    'min_vcpu' => 16,
                    'min_memoryInGiB' => 32
                ]
            ],
            [
                'instance_count' => [
                    'min' => 76,
                    'max' => 125
                ],
                'type' => [
                    'min_vcpu' => 8,
                    'min_memoryInGiB' => 64
                ]
            ],
            [
                'instance_count' => [
                    'min' => 126,
                    'max' => 200
                ],
                'type' => [
                    'min_vcpu' => 16,
                    'min_memoryInGiB' => 128
                ]
            ],
        ];
        
        // Get master node instance type
        $instanceType = $this->cluster_config['DedicatedMasterType'];

        //Parse instance
        $instInfo = __aws_parseInstanceFamily($instanceType);

        // Get data node count
        $nodes = $this->cluster_config['InstanceCount'];

        if($nodes < 0 || $nodes > self::NODES_LIMIT){
            __warn("$nodes not within the range of 0 & ".self::NODES_LIMIT);
        }
        
        foreach ($map as $row) {
            if ($row['instance_count']['min'] <= $nodes && $nodes <= $row['instance_count']['max']) {
                //Get instance attributes
                $cpu = $instInfo['specification']['vcpu'];
                $mem = $instInfo['specification']['memoryInGiB'];
                if ($row['type']['min_vcpu'] <= $cpu && $row['type']['min_memoryInGiB'] <= $mem) {
                    $this->results['MasterNodeType'] = [1, $instanceType];
                }
                else {
                    $this->results['MasterNodeType'] = [-1, $instanceType];
                }
            }
        }
    }
}
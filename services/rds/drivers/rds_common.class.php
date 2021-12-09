<?php

## TODO:
# Metrics monitoring: 
#   https://sysdig.com/blog/monitoring-amazon-rds/
#   https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/accessing-monitoring.html
class rds_common extends evaluator{
    protected $dbParams = [];
    
    // public $results = [
    //     'MultiAZ' => 1,
    //     'EngineVersionMajor' => 1,
    //     'EngineVersionMinor' => 1,
    //     'AutoMinorVersionUpgrade' => 1
    // ];
    public $results = [];
    
    
    function __construct($db, $rdsClient){
        $this->db = $db;
        $this->rdsClient = $rdsClient;
        $this->__configPrefix = 'rds::'.$db['Engine'] .'::'.$db['EngineVersion'] .'::';
        
        $this->init();
        $this->loadParameterInfo();
        
    }
    
    ##Overriding evaluator
    function showInfo(){
        echo "Identifier: " . $this->db['DBInstanceIdentifier'] . PHP_EOL;
        __pr($this->results);
    }
    
    ## remove empty value from parameters, save memory consumption
    protected function loadParameterInfo(){
        $arr = [];
        
        $paramGroupName = $this->db['DBParameterGroups'][0]['DBParameterGroupName'];
        $results = $this->rdsClient->describeDBParameters([
            'DBParameterGroupName' => $paramGroupName,
        ]);
        
        foreach($results->get('Parameters') as $param){
            #__pr($param['ParameterName'] . ' = ' . @$param['ParameterValue'] . ' || ' . $param['IsModifiable']);
            if($param['IsModifiable'] == 1 && isset($param['ParameterValue'])){
                $arr[$param['ParameterName']] = $param['ParameterValue'];
            }
        }
        
        while($results->get('Marker') !== null){
            $results = $this->rdsClient->describeDBParameters([
                'DBParameterGroupName' => $paramGroupName,
                'Marker' => $results->get('Marker')
            ]);
            
            foreach($results->get('Parameters') as $param){
                #__pr($param['ParameterName'] . ' = ' . @$param['ParameterValue'] . ' || ' . $param['IsModifiable']);
                if($param['IsModifiable'] == 1 && isset($param['ParameterValue'])){
                    $arr[$param['ParameterName']] = $param['ParameterValue'];
                }
            }
        }
        
        $this->dbParams = $arr;
        unset($arr);
    }
    
    ##Common Logic Belows
    ##All checks start from __check;
    
    protected function __checkHasMultiAZ(){
        $multiAZ = $this->db['MultiAZ'] === false ? -1 : 1;
        $this->results['MultiAZ'] = [$multiAZ, $multiAZ == -1 ? 'Off' : 'On'];
    }
    
    protected function __checkAutoMinorVersionUpgrade(){
        $flag = $this->db['AutoMinorVersionUpgrade'] === false ? -1 : 1;
        $this->results['AutoMinorVersionUpgrade'] = [$flag, $flag == -1 ? 'Off' : 'On'];
    }
    
    protected function __checkHasStorageEncrypted(){
        $flag = $this->db['StorageEncrypted'] === false ? -1 : 1;   
        $this->results['StorageEncrypted'] = [$flag, $flag == -1 ? 'Off' : 'On'];
    }
    
    protected function __checkHasPerformanceInsightsEnabled(){
        $flag = $this->db['PerformanceInsightsEnabled'] === false ? -1 : 1;
        $this->results['PerformanceInsightsEnabled'] = [$flag, $flag == -1 ? 'Off' : 'On'];
    }
    
    protected function __checkHasBackup(){
        $backupDay = $this->db['BackupRetentionPeriod'];
        if($backupDay == 0)
            $this->results['Backup'] = -1;
        else if($backupDay < 7)
            $this->results['BackupTooLow'] = -1;
            
        if($backupDay < 7)
            $this->results['Backup'] = [-1, $backupDay];
    }
    
    protected function __checkIsUsingDefaultParameterGroups(){
        $params = $this->db['DBParameterGroups'];
        foreach($params as $param){
            if( strpos($param['DBParameterGroupName'], 'default.') !== false){
                $this->results['DefaultParams'] = [-1, $param['DBParameterGroupName']];
            }
        }
    }
    
    protected function __checkHasEnhancedMonitoring(){
        $flag = isset($this->db['EnhancedMonitoringResourceArn']) ? 1 : -1;
        $this->results['EnhancedMonitor'] = [$flag, $flag == -1 ? 'Off' : 'On'];
    }
    
    protected function __checkDeleteProtection(){
        $flag = $this->db['DeletionProtection'] === false ? -1 : 1;
        $this->results['DeleteProtection'] = [$flag, $flag == -1 ? 'Off' : 'On'];
    }
    
    protected function __checkIsPublicAccessible(){
        $flag = $this->db['PubliclyAccessible'] === true ? -1 : 1;
        $this->results['PubliclyAccessible'] = [$flag, $flag == -1 ? 'Off' : 'On'];
    }
    
    protected function __checkSubnet3Az(){
        $subnets = $this->db['DBSubnetGroup']['Subnets'];
        
        $subnetName = [];
        foreach($subnets as $subnet){
            $subnetName[] = $subnet['SubnetAvailabilityZone']['Name'];
        }
        
        $flag = 1;
        if(sizeof($subnets) < 3)
            $flag = -1;
            
        $this->results['Subnets3Az'] = [$flag, implode(', ', $subnetName)];
    }
    
    protected function __checkIsInstanceLatestGeneration(){
        global $CONFIG;
        $key = $this->__configPrefix . 'orderableInstanceType';
        $instTypes = $CONFIG->get( $key, []);
        
        if(empty($instTypes)){
            $results = $this->rdsClient->describeOrderableDBInstanceOptions([
            #    'DBInstanceClass' => $this->db['DBInstanceClass'],
                'Engine' => $this->db['Engine'],
                'EngineVersion' => $this->db['EngineVersion'],
                'MaxRecords' => 20
            ]);
            
            $arr = [];
            foreach($results->get('OrderableDBInstanceOptions') as $instClass){
                $arr[] = $instClass['DBInstanceClass'];  
            }
            
            while($results->get('Marker') !== null){
                $results = $this->rdsClient->describeOrderableDBInstanceOptions([
                    'Engine' => $this->db['Engine'],
                    'EngineVersion' => $this->db['EngineVersion'],
                    'Marker' => $results->get('Marker')
                ]);
                
                foreach($results->get('OrderableDBInstanceOptions') as $instClass){
                    $arr[] = $instClass['DBInstanceClass'];  
                }
            }
            
            $instTypes = array_values(array_unique($arr));
            $CONFIG->set($key, $instTypes);
            
            $compressedLists = [];
            foreach($instTypes as $instType){
                $temp = explode('.', $instType);
                $compressedLists[ $temp[1][0] ] = substr($temp[1], 1, 1);
            }
            
            $CONFIG->set($key . '::zip', $compressedLists);
        }else{
            $compressedLists = $CONFIG->get($key . '::zip');
        }
        
        $dbInstClass = explode('.', $this->db['DBInstanceClass']);
        $dbInstFamily = $dbInstClass[1][0];
        $dbInstGeneration = $dbInstClass[1][1];
        
        if($dbInstFamily == 't'){
            $this->results['BurstableInstance'] = [-1, $this->db['DBInstanceClass']];   
        }
        
        if( $compressedLists[$dbInstFamily] > $dbInstGeneration)
            $this->results['LatestInstanceGeneration'] = [-1, $this->db['DBInstanceClass']];
    }
    
    ## Todo: cache engine
    protected function __checkHasPatches(){
        global $CONFIG;
        $engine = $this->db['Engine'];
        $engineVersion = $this->db['EngineVersion'];
        
        $key = $this->__configPrefix . 'engineVersions';
        $versions = $CONFIG->get( $key, []);
        if(empty($details)){
            $versions = $this->rdsClient->describeDBEngineVersions([
               'Engine' => $engine,
               'EngineVersion' => $engineVersion
            ]);
            
            $details = $versions->get('DBEngineVersions')[0];
            $CONFIG->set($key, $details);
        }

        $upgrades = $details['ValidUpgradeTarget'];
        if(empty($upgrades)){
            $this->results['EngineVersion'] = 1;
            return;
        }
        
        if($upgrades[0]['IsMajorVersionUpgrade'] == false)
            $this->results['EngineVersionMinor'] = [-1, $engineVersion];
            
        $lastInfo = end($upgrades);
        if($lastInfo['IsMajorVersionUpgrade'] == true)
            $this->results['EngineVersionMajor'] = [-1, $engineVersion];
    }
    
    protected function __checkClusterSize(){
        $cluster = $this->db['DBClusterIdentifier'] ?? null;
        if(empty($cluster))
            return;
        
        $resp = $this->rdsClient->describeDBClusters([
            'DBClusterIdentifier' => $cluster
        ]);
        
        $clusters = $resp->get('DBClusters');
        if(sizeof($clusters) < 2 or sizeof($clusters) > 7)
            $this->results['Aurora__ClusterSize'] = [-1, sizeof($clusters)];
    }
    
    protected function __checkOldSnapshots(){
        
        if(!empty($this->db['DBClusterIdentifier'])){
            $identifier = $this->db['DBClusterIdentifier'];
            $result = $this->rdsClient->describeDBClusterSnapshots([
                'DBClusterIdentifier' => $identifier,
                'SnapshotType' => 'manual'
            ]);
            
            $snapshots = $result->get('DBClusterSnapshots');
            while($result->get('Marker') !== null){
                $result = $this->rdsClient->describeDBClusterSnapshots([
                    'DBClusterIdentifier' => $identifier,
                    'SnapshotType' => 'manual',
                    'Marker' => $result->get('Marker')
                ]);
                
                $snapshots = array_merge($snapshots, $result->get('DBSnapshots'));
            }
        }else{
            $identifier = $this->db['DBInstanceIdentifier'];
            $result = $this->rdsClient->describeDBSnapshots([
                'DBInstanceIdentifier' => $identifier,
                'SnapshotType' => 'manual'
            ]);
            
            $snapshots = $result->get('DBSnapshots');
            while($result->get('Marker') !== null){
                $result = $this->rdsClient->describeDBSnapshots([
                    'DBInstanceIdentifier' => $identifier,
                    'SnapshotType' => 'manual',
                    'Marker' => $result->get('Marker')
                ]);
                
                $snapshots = array_merge($snapshots, $result->get('DBSnapshots'));
            }
        }
    
        if(empty($snapshots))
            return;
            
        $oldestCopy = end($snapshots);
        $oldestCopyDate = $oldestCopy['SnapshotCreateTime'];
        
        $now = new DateTime(Date('Y-m-d'));
        
        $diff = $oldestCopyDate->diff($now);
        $days = $diff->format('%a');
        
        if(sizeof($snapshots) > 5)
            $this->results['SnapshotTooMany'] = [-1, sizeof($snapshots)];
        
        if($days > 180)
            $this->results['SnapshotTooOld'] = [-1, $days];   
        
    }
    
    ## TODO
    ## remaining size less than 80%
    protected function __checkFreeStorage(){
        global $CW;
        $cwClient = $CW->getClient();
        
        if(!empty($this->db['DBClusterIdentifier'])){
            ## Aurora Volume auto increase until 128TB as of 23/Sep/2021
            return;
        }else{
            $metric = 'FreeStorageSpace';
            $dimensions = [
                [
                    'Name' => 'DBInstanceIdentifier',
                    'Value'=> $this->db['DBInstanceIdentifier']
                ]
            ];
        }
        
        $results = $cwClient->getMetricStatistics([
            'Dimensions' => $dimensions,
            'Namespace' => 'AWS/RDS',
            'MetricName' => $metric,
            'StartTime' => strtotime('-5 minutes'),
            'EndTime' => strtotime('now'),
            'Period' => 300,
            'Statistics' => ['Average'],
            # 'Unit' => 'None'
        ]);
        
        $GBYTES = 1024 * 1024 * 1024;
        # __pr($this->db['AllocatedStorage']);
        $dp = $results->get('Datapoints');
        $freesize = round($dp[0]['Average']/$GBYTES, 4);
        
        $ratio = ($freesize / $this->db['AllocatedStorage']);
        if($ratio < 0.2){
            $this->results['FreeStorage20pct'] = [-1, $ratio*100 . ' / ' . $freesize . '(GB)'];
        }
    }
}
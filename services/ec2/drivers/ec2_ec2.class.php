<?php 

class ec2_ec2 extends evaluator{
    function __construct($ec2, $ec2Client){
        $this->ec2 = $ec2;
        $this->ec2Client = $ec2Client;
        $this->init();
    }
    
    function __checkInstanceTypeGeneration(){
        $instance = $this->ec2['Instances'][0];
        $instanceType = $instance['InstanceType'];
        
        $typeArr = explode('.', $instanceType);
        $family = $typeArr[0];
        $size = $typeArr[1];
        $familyChar = str_split($family);
        
        foreach($familyChar as $idx => $char){
            if(is_numeric($char)){
                $familyChar[$idx]++; 
            }
        }
        $newFamily = implode($familyChar);

        try{
            $results = $this->ec2Client->describeInstanceTypes([
                'InstanceTypes' => [$newFamily.'.'.$size]
            ]);
        }catch(Exception  $e){
            $this->results['EC2NewGen'] = [1, $instance['InstanceType']];
            return;
        }
        
        $this->results['EC2NewGen'] = [-1, $instance['InstanceType']];
        return;
    }
    
    function __checkDetailedMonitoringEnable(){
        $instance = $this->ec2['Instances'][0];
        
        if($instance['Monitoring']['State'] == 'disabled'){
            $this->results['EC2DetailedMonitor'] = [-1, $instance['Monitoring']['State']];
        }else{
            $this->results['EC2DetailedMonitor'] = [1, $instance['Monitoring']['State']];
        }
        
        return;
    }
    
    function __checkIamProfileAssociate(){
        $instance = $this->ec2['Instances'][0];
        
        $instanceId = $instance['InstanceId'];
        $results = $this->ec2Client->describeIamInstanceProfileAssociations([
            "Filters" => [
                [
                    'Name' => 'instance-id',
                    'Values' => [$instanceId]
                ]    
            ]
        ]);
        
        if($this->verifyIamProfileAssociate($results)){
            ## KT commented, causing undefined error and 
            ##  no need to put [1]
            # $this->results['EC2IamProfile'] = [1, $results['IamInstanceProfileAssociations']['IamInstanceProfile']['Arn']];
        }else{
            $this->results['EC2IamProfile'] = [-1, ''];
        }
        
        return;
    }
    
    function verifyIamProfileAssociate($iamProfile){
        if(empty($iamProfile['IamInstanceProfileAssociations'])){
            return false;
        }else{
            return true;
        }
    }
    
    function __checkEIPInUsed(){
        $eipList = $this->ec2Client->describeAddresses();
        
        foreach($eipList['Addresses'] as $eip){
            if(!isset($eip['AssociationId'])){
                $this->results['EC2EIPInUsed'] = [-1, $eip['PublicIp']];
                return;
            }
        }
        return;
    }
    
    function __checkCWMemoryMetrics(){
        global $CW;
        $cwClient = $CW->getClient();
        $instance = $this->ec2['Instances'][0];
        
        $dimensions = [
            [
                'Name' => 'InstanceId',
                'Value' => $instance['InstanceId']
            ]
        ];
        
        $result = $cwClient->listMetrics([
            'MetricName' => 'mem_used_percent',
            'Namespace' => 'CWAgent',
            'Dimensions' => $dimensions
        ]);
        
        if(!empty($result['Metrics'])){
            return;
        }
        
         $result = $cwClient->listMetrics([
            'MetricName' => 'Memory % Committed Bytes In Use',
            'Namespace' => 'CWAgent',
            'Dimensions' => $dimensions
        ]);
        
        if(!empty($result['Metrics'])){
            return;
        }
        
        $this->results['EC2MemoryMonitor'] = [-1, 'Disabled'];
        return;
    }
    
    function __checkCWDiskMetrics(){
        global $CW;
        $cwClient = $CW->getClient();
        $instance = $this->ec2['Instances'][0];
        
        $dimensions = [
            [
                'Name' => 'InstanceId',
                'Value' => $instance['InstanceId']
            ]
        ];
        
        $result = $cwClient->listMetrics([
            'MetricName' => 'disk_used_percent',
            'Namespace' => 'CWAgent',
            'Dimensions' => $dimensions
        ]);
        
        if(!empty($result['Metrics'])){
            return;
        }
        
         $result = $cwClient->listMetrics([
            'MetricName' => 'LogicalDisk % Free Space',
            'Namespace' => 'CWAgent',
            'Dimensions' => $dimensions
        ]);
        
        if(!empty($result['Metrics'])){
            return;
        }
        
        $this->results['EC2DiskMonitor'] = [-1, 'Disabled'];
        return;
    }
    
    function __checkEC2Active(){
        global $CW;
        
        $verifyDay = 7;
        
        $cwClient = $CW->getClient();
        $instance = $this->ec2['Instances'][0];
        
        $launchTime = $instance['LaunchTime'];
        $launchDay = (strtotime('now') - strtotime($launchTime))/(60 * 60 * 24);
        if($launchDay < $verifyDay){
            return;
        }
        
        $dimensions = [
            [
                'Name' => 'InstanceId',
                'Value'=> $instance['InstanceId']
            ]
        ];
        
        $results = $cwClient->getMetricStatistics([
            'Dimensions' => $dimensions,
            'Namespace' => 'AWS/EC2',
            'MetricName' => 'CPUUtilization',
            'StartTime' => strtotime('-7 days'),
            'EndTime' => strtotime('now'),
            'Period' => $verifyDay * 24 * 60 * 60,
            'Statistics' => ['Average'],
            # 'Unit' => 'None'
        ]);
        
        if(empty($results['Datapoints'])){
            $this->results['EC2Active'] = [-1, 'Inactive'];
        }
        
        return;
    }
    
    function __checkSecurityGroupNo(){
        $instance = $this->ec2['Instances'][0];
        
        if(sizeof($instance['SecurityGroups']) > 50){
            $this->results['EC2SGNumber'] = [-1, sizeof($instance['SecurityGroups'])];
        }
        return;
    }
    
    function getEC2UtilizationMetrics($metricName, $verifyDay){
        global $CW;
        
        $cwClient = $CW->getClient();
        $instance = $this->ec2['Instances'][0];
        
        $dimensions = [
            [
                'Name' => 'InstanceId',
                'Value'=> $instance['InstanceId']
            ]
        ];
        
        $results = $cwClient->getMetricStatistics([
            'Dimensions' => $dimensions,
            'Namespace' => 'AWS/EC2',
            'MetricName' => $metricName,
            'StartTime' => strtotime('-'.$verifyDay.' days'),
            'EndTime' => strtotime('now'),
            'Period' => 24 * 60 * 60,
            'Statistics' => ['Average'],
            # 'Unit' => 'None'
        ]);
        
        return $results;
    }
    
    function checkMetricsLowUsage($mericName, $verifyDay, $thresholdDay, $thresholdValue){
        $result = $this->getEC2UtilizationMetrics($mericName, $verifyDay);
        
        $cnt = 0;
        if(sizeof($result['Datapoints']) < $verifyDay){
            ## Handling if EC2 is stopped
            $cnt = $verifyDay - sizeof($result['Datapoints']);
        }
        
        foreach($result['Datapoints'] as $datapoint){
            if($datapoint['Average'] < $thresholdValue){
                $cnt++;
            }
        }
        
        if($cnt < $thresholdDay){
            return false;
        }else{
            return true;
        }
    }
    
    function __checkEC2LowUtilization(){
        $instance = $this->ec2['Instances'][0];
        
        $verifyDay = 14;
        $thresholdDay = 4;
        
        $launchTime = $instance['LaunchTime'];
        $launchDay = (strtotime('now') - strtotime($launchTime))/(60 * 60 * 24);
        if($launchDay < $verifyDay){
            return;
        }
        
        $cpuThresholdPercent = 10;
        $cpuLowUsage = $this->checkMetricsLowUsage('CPUUtilization', $verifyDay, $thresholdDay, $cpuThresholdPercent);
        if(!$cpuLowUsage){
            return;
        }
        
        $networkThresholdByte = 5 * 1024 * 1024;
        $networkOutLowUsage = $this->checkMetricsLowUsage('NetworkOut', $verifyDay, $thresholdDay, $networkThresholdByte);
        if(!$networkOutLowUsage){
            return;
        }
        
        $networkInLowUsage = $this->checkMetricsLowUsage('NetworkIn', $verifyDay, $thresholdDay, $networkThresholdByte);
        if(!$networkInLowUsage){
            return;
        }
        
        $this->results['EC2LowUtilization'] = [-1, ''];
        return;
    }
    
    function checkMetricsHighUsage($mericName, $verifyDay, $thresholdDay, $thresholdValue){
        $result = $this->getEC2UtilizationMetrics($mericName, $verifyDay);
        
         if(sizeof($result['Datapoints']) < $verifyDay){
            return false;
        }
        
        $cnt = 0;
        foreach($result['Datapoints'] as $datapoint){
            if($datapoint['Average'] > $thresholdValue){
                $cnt++;
            }
        }
        
        if($cnt < $thresholdDay){
            return false;
        }else{
            return true;
        }
    }
    
    function __checkEC2HighUtilization(){
        $instance = $this->ec2['Instances'][0];
        
        $verifyDay = 14;
        $thresholdDay = 4;
        
        $launchTime = $instance['LaunchTime'];
        $launchDay = (strtotime('now') - strtotime($launchTime))/(60 * 60 * 24);
        if($launchDay < $verifyDay){
            return;
        }
        
        $cpuThresholdPercent = 90;
        $cpuHighUsage = $this->checkMetricsHighUsage('CPUUtilization', $verifyDay, $thresholdDay, $cpuThresholdPercent);
        if(!$cpuHighUsage){
            return;
        }

        $this->results['EC2HighUtilization'] = [-1, ''];
        return;
    }
}
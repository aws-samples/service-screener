<?php

class ec2_ebs extends evaluator{
    public $results = [];
    const OLDGENBLOCK = [
        'gp2',
        'io1'
    ];
    
    function __construct($ebs, $ec2Client){
        $this->block = $ebs;
        $this->ec2Client = $ec2Client;
        $this->init();
    }
    
    function __checkEncryptedBlock(){
        $block = $this->block;
        if($block['Encrypted']){
            $this->results['EBSEncrypted'] = [1, $block['Encrypted']];
        }else{
            $this->results['EBSEncrypted'] = [-1, $block['Encrypted']];
        }
        return;
    }
    
    function __checkNewGenBlock(){
        $block = $this->block;
        
        if(!in_array($block['VolumeType'], self::OLDGENBLOCK)){
            $this->results['EBSNewGen'] = [1, $block['VolumeType']];
        }else{
            $this->results['EBSNewGen'] = [-1, $block['VolumeType']];
        }
        return;
    }
    
    function __checkInUsedBlock(){
        $block = $this->block;
        
        if($block['State'] == 'available'){
            $this->results['EBSInUsed'] = [-1, $block['State']];
        }else{
            $this->results['EBSInUsed'] = [1, $block['State']];
        }
        
        return;
    }
    
    function __checkSnapshotBlock(){
        $block = $this->block;
        
        if($block['SnapshotId']){
            $this->results['EBSSnapshot'] = [1, $block['SnapshotId']];
        }else{
            $this->results['EBSSnapshot'] = [-1, $block['SnapshotId']];
        }
        
        return;
    }
    
    function __checkOutdatedSnapshot(){
        $block = $this->block;
        $volumeId = $block['VolumeId'];
        
        $result = $this->ec2Client->describeSnapshots([
            'Filters' => [
                [
                    'Name' => 'volume-id',
                    'Values' => [$volumeId]
                ]    
            ]
        ]);
        
        if(!empty($result['Snapshots'])){
            foreach($result['Snapshots'] as $snapshot){
                $startTime = $snapshot['StartTime'];
                
                $timeDiff = strtotime($startTime->__toString()) - strtotime('-7 days');
                if($timeDiff > 0){
                    return;
                }
            }
            
            $this->results['EBSUpToDateSnapshot'] = [-1, ''];
        }
        
        return;
    }
    
    function __checkLowEBSLowUtilization(){
        global $CW;
        
        $verifyDay = 7;
        $block = $this->block;
        
        $createTime = $block['CreateTime'];
        $createDay = (strtotime('now') - strtotime($createTime)) / (60 * 60 * 24);
        
        if($createDay < $verifyDay){
            return;
        }
        
        $cwClient = $CW->getClient();
        $blockId = $this->block['VolumeId'];
        
        $dimensions = [
            [
                'Name' => 'VolumeId',
                'Value'=> $blockId
            ]
        ];
        
        $results = $cwClient->getMetricStatistics([
            'Dimensions' => $dimensions,
            'Namespace' => 'AWS/EBS',
            'MetricName' => 'VolumeReadOps',
            'StartTime' => strtotime('-'.$verifyDay.' days'),
            'EndTime' => strtotime('now'),
            'Period' => 24 * 60 * 60,
            'Statistics' => ['Average'],
            # 'Unit' => 'None'
        ]);
        
        
        $cnt = 0;
        $readDatapoints = $results['Datapoints'];
        if(sizeof($readDatapoints) < $verifyDay){
            $cnt = $verifyDay - sizeof($readDatapoints);
        }
        
        foreach($readDatapoints as $data){
            if($data['Average'] < 1){
                $cnt++;
            }
        }
        
        if($cnt < $verifyDay){
            return;
        }
        
        $cnt = 0;
        $results = $cwClient->getMetricStatistics([
            'Dimensions' => $dimensions,
            'Namespace' => 'AWS/EBS',
            'MetricName' => 'VolumeWriteOps',
            'StartTime' => strtotime('-'.$verifyDay.' days'),
            'EndTime' => strtotime('now'),
            'Period' => 24 * 60 * 60,
            'Statistics' => ['Average'],
            # 'Unit' => 'None'
        ]);
        
        $writeDatapoints = $results['Datapoints'];
        if(sizeof($writeDatapoints) < $verifyDay){
            $cnt = $verifyDay - sizeof($writeDatapoints);
        }
        
        foreach($writeDatapoints as $data){
            if($data['Average'] < 1){
                $cnt++;
            }
        }
        
        if($cnt < $verifyDay){
            return;
        }
        
        $this->results['EBSLowUtilization'] = [-1, ''];
        return;
    }
    
    function __checkFastSnapshot(){
        $results = $this->ec2Client->describeFastSnapshotRestores();
        if(!empty($results['FastSnapshotRestores'])){
            $this->results['EBSFastSnapshot'] = [-1, ''];
        }
        
        return;
    }
}

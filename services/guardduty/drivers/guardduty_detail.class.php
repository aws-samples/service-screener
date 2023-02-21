<?php

## TODO:
# Metrics monitoring: 
#   https://sysdig.com/blog/monitoring-amazon-rds/
#   https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/accessing-monitoring.html
class guardduty_detail extends evaluator{
    public $results = [];
    
    function __construct($detectorId, $guarddutyClient, $region){
        global $CONFIG;
        $stsInfo = $CONFIG->get('stsInfo');
        $this->accountId = $stsInfo['Account'];
        
        $this->region = $region;
        $this->detectorId = $detectorId;
        $this->gdClient = $guarddutyClient;
        $this->init();
    }
    
    ##Finding Detail
    protected function __checkFindings(){
        $nextToken = null;
        do{
            $results = $this->gdClient->listFindings([
                'DetectorId' => $this->detectorId,
                'MaxResults' => 20,
                'NextToken' => $nextToken
            ]);
            
            $findingIds = $results->get('FindingIds');
            
            if(!empty($findingIds)){
                $findings = $this->gdClient->getFindings([
                    'DetectorId' => $this->detectorId,
                    'FindingIds' => $findingIds
                ]);
                
                foreach($findings->get('Findings') as $finding){
                    $type = $finding['Type'];
                    
                    $arr[$finding['Severity']][$type][] = [
                        'Id' => $finding['Id'],
                        'Count' => $finding['Service']['Count'],
                        'Title' => $finding['Title'],
                        'region' => $this->region
                    ];
                }
            }
            $nextToken = $results->get('NextToken');
        } while(false && !empty($nextToken));
        
        if(empty($arr))
            return;
            
        foreach($arr as $serv => $obj){
            foreach($obj as $type => $det){
                $arr[$serv][$type]['__'] = $this->__buildDocLinks($type);
            }
        }
        
        $this->results['Findings'] = [-1, $arr];
    }
    
    ##<TODO>Cost Summary
    protected function __checkUsageStatistics(){
        $nextToken = null;
        $results = $this->gdClient->getUsageStatistics([
            'DetectorId' => $this->detectorId,
            'MaxResults' => 50,
            'NextToken' => $nextToken,
            'UsageCriteria' => [
                'DataSources' => [
                    'FLOW_LOGS', 
                    'CLOUD_TRAIL', 
                    'DNS_LOGS', 
                    'S3_LOGS', 
                    'KUBERNETES_AUDIT_LOGS', 
                    'EC2_MALWARE_SCAN'
                ]
            ],
            'UsageStatisticType' => 'SUM_BY_DATA_SOURCE'
        ]);
        
        $tmp = $results->get('UsageStatistics');
        $arr = $tmp['SumByDataSource'];
        $this->results['UsageStat'] = [-1, $arr];
    }
    
    ##<TODO> Check FreeTrialPeriod, https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-guardduty-2017-11-28.html#getremainingfreetrialdays
    protected function __checkFreeTrialRemaining(){
        $results = $this->gdClient->getRemainingFreeTrialDays([
            'AccountIds' => [$this->accountId],
            'DetectorId' => $this->detectorId
        ]);
        
        $tmp = $results->get('Accounts');
        $arr = $tmp[0]['DataSources'];
        
        $this->results['FreeTrial'] = [-1, $arr];
    }
    
    ##<TODO> Check Settings, https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-guardduty-2017-11-28.html#getdetector
    protected function __checkGuardDutySettings(){
        $results = $this->gdClient->getDetector([
            'DetectorId' => $this->detectorId
        ]);
        
        $settings = $results->get('DataSources');
        $gdStatus = $results->get('Status');
        
        ## if suspsend, gdStatus shows DISABLE
        ## if disabled, no detector found
        $this->results['Settings'] = [-1, ['isEnabled'=> $gdStatus, 'Settings' => $settings]];
    }
    
    protected function __buildDocLinks($topic){
        $generalPage = "https://docs.aws.amazon.com/guardduty/latest/ug/guardduty_finding-types-active.html";
        $docPrefix = "https://docs.aws.amazon.com/guardduty/latest/ug/";
        
        
        $patterns = "/\w+/i";
        $result = preg_match_all($patterns, $topic, $output);
        
        $type = $output[0][1];
        
        ## Malware
        if($output[0][0] == 'Execution'){
            $type = 'Malware';
        }
        
        ## Need to validate if RDS links work properly, no sample.
        $types = [
            'EC2' => "guardduty_finding-types-ec2",
            'IAMUser' => "guardduty_finding-types-iam",
            'Kubernetes' => "guardduty_finding-types-kubernetes",
            'S3' => "guardduty_finding-types-s3",
            'Malware' => "findings-malware-protection",
            'RDS' => "findings-rds-protection"
        ];
        
        if(isset($types[$type])){
            $topic = $output[0][0].'-'.$output[0][1].'-'.$output[0][2];
            return $docPrefix.$types[$type].".html#".strtolower($topic);
        }else{
            return $generalPage."#suffix?screener=notfound&type=$type";
        }
    }
}
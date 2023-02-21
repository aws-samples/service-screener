<?php

## TODO:
# Metrics monitoring: 
#   https://sysdig.com/blog/monitoring-amazon-rds/
#   https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/accessing-monitoring.html
class guardduty_account extends evaluator{
    protected $dbParams = [];
    public $results = [];
    
    
    function __construct($detectorId, $guarddutyClient){
        $this->detectorId = $detectorId;
        $this->gdClient = $guarddutyClient;
        $this->init();
    }
    
    ##MockTest
    protected function __checkFindings(){
        $nextToken = null;
        do{
            $results = $this->gdClient->listFindings([
                'DetectorId' => $this->detectorId,
                'MaxResults' => 2,
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
                        'Title' => $finding['Title']
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
        
        $this->results['Placeholder'] = [-1, $arr];
    }
    
    protected function __buildDocLinks($topic){
        $generalPage = "https://docs.aws.amazon.com/guardduty/latest/ug/guardduty_finding-types-active.html";
        $docPrefix = "https://docs.aws.amazon.com/guardduty/latest/ug/guardduty_finding-types-";
        
        
        $patterns = "/\w+/i";
        $result = preg_match_all($patterns, $topic, $output);
        
        $type = $output[0][1];
        $types = [
            'EC2' => "ec2",
            'IAMUser' => "iam",
            'Kubernetes' => "kubernetes",
        ];
        
        if(isset($types[$type])){
            $topic = $output[0][0].'-'.$types[$type].'-'.$output[0][2];
            return $docPrefix.$types[$type].".html#".strtolower($topic);
        }else{
            return $generalPage."#suffix?screener=notfound&type=$type";
        }
    }
}
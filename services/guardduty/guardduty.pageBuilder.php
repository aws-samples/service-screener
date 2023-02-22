<?php

class guarddutyPageBuilder extends pageBuilder{
    const DATASOURCE = ['FlowLogs', 'CloudTrail', 'DnsLogs', 'S3Logs', ['Kubernetes', 'AuditLogs'], ['MalwareProtection', 'ScanEc2InstanceWithFindings']];
    const SERVICESUMMARY_DEFAULT = [
        'EC2' => 0, 
        'IAMUser' => 0,
        'Kubernetes' => 0,
        'S3' => 0,
        'Malware' => 0,
        'RDS' => 0
    ];
    public $statSummary = [];
    public $findings = [];
    public $settings = [];
    function init(){
        $this->template='default';
        $this->__gdProcess();
    }
    
    function __gdProcess(){
        $this->statSummary = [
            'services' => self::SERVICESUMMARY_DEFAULT
        ];
        
        $detail = $this->reporter->getDetail();
        foreach($detail as $region => $detectors){
            $findings = '';
            foreach($detectors as $detectorId => $detector){
                if(isset($detector['Findings']))
                    $findings = $this->__gdProcessFinding($detector['Findings']['value']);
                
                $this->settings[$region] = $this->__gdProcessGeneral($detector['FreeTrial']['value'], $detector['Settings']['value']['Settings'], $detector['UsageStat']['value']);
            }
            
            if(!empty($findings)){
                $this->findings[] = $findings['detail'];
                
                $this->statSummary[$region] = $findings['stat']['severity'];
                foreach($findings['stat']['services'] as $serv => $val)
                    $this->statSummary['services'][$serv] += $val;
            }
        }
    }
    
    function __gdProcessFinding($findings){
        ## Build Summary
        $arr['stat'] = [
            'severity' => [],
            'services' => self::SERVICESUMMARY_DEFAULT
        ];
        
        $__findings = [
            8 => [],
            5 => [],
            2 => []
        ];
        
        $arr['stat']['severity'] = [
            'HIGH' => sizeof($findings[8]),
            'MEDIUM' => sizeof($findings[5]),
            'LOW' => sizeof($findings[2]),
        ];
        
        $severityModes = [8, 5, 2];
        $patterns = "/\w+/i";
        foreach($severityModes as $severity){
            if(!isset($findings[$severity]))
                continue;
            
            foreach($findings[$severity] as $topic => $detail){
                $result = preg_match_all($patterns, $topic, $output);
                $type = $output[0][1];
                if($output[0][0] == 'Execution')
                    $type = 'Malware';
                
                $__findings[$severity][$type][$topic] = $detail;
            }
            
            foreach($__findings[$severity] as $service => $detail){
                $arr['stat']['services'][$service] += sizeof($__findings[$severity][$service]);
            }
        }
        
        $arr['detail'] = $__findings;
        return $arr;
    }
    
    function __gdProcessGeneral($freeTrial, $settings, $usageStat){
        // usage does not follows FreeTrial/Enable KEY
        $emptyArray = [
            'FreeTrial' => -1,
            'Enabled' => null,
            'Usage' => 0
        ];
        $MAPPED = [
            'FLOW_LOGS' => 'FlowLogs',
            'CLOUD_TRAIL' => 'CloudTrail',
            'DNS_LOGS' => 'DnsLogs',
            'S3_LOGS' => 'S3Logs',
            'KUBERNETES_AUDIT_LOGS' => 'Kubernetes:AuditLogs',
            'EC2_MALWARE_SCAN' => 'MalwareProtection:ScanEc2InstanceWithFindings'
        ];
        $arr = [];
        foreach(self::DATASOURCE as $ds){
            if( is_array($ds) ){
                $key = $ds[0].':'.$ds[1];
                $arr[$key] = $emptyArray;
                
                $arr[$key]['FreeTrial'] = $freeTrial[$ds[0]][$ds[1]]['FreeTrialDaysRemaining'] ?? 'N/A';
                
                if($ds[0] == 'MalwareProtection')
                    $arr[$key]['Enabled'] = $this->__generateEnabledIcon($settings[$ds[0]][$ds[1]]['EbsVolumes']['Status']);
                else
                    $arr[$key]['Enabled'] = $this->__generateEnabledIcon($settings[$ds[0]][$ds[1]]['Status']);
            }else{
                $arr[$ds] = $emptyArray;
                $arr[$ds]['FreeTrial'] = $freeTrial[$ds]['FreeTrialDaysRemaining'] ?? 'N/A';
                
                ## Inconsistent Naming Conversion
                $__ds = $ds;
                if($ds == 'DnsLogs') $__ds = 'DNSLogs';
                $arr[$ds]['Enabled'] = $this->__generateEnabledIcon($settings[$__ds]['Status']);
            }
        }
        
        $total = 0;
        foreach($usageStat as $stat){
            $amount = round($stat['Total']['Amount'], 4);
            $ds = $MAPPED[$stat['DataSource']];
            $arr[$ds]['Usage'] = $amount;
            
            $total += $amount;
        }
        
        $arr['Total'] = $total;
        return $arr;
    }
    
    protected function __generateEnabledIcon($status){
        $icon = $status == 'ENABLED' ? 'check-circle' : 'ban';
        return "<i class='nav-icon fas fa-$icon'></i>";
    }
    
    function buildContentSummary(){
        #### SUMMARY ROW ####
        ## High,Medium,Low STAT group by REGION
        $dataSets = [];
        $labels = ['HIGH', 'MEDIUM', 'LOW'];
        foreach($this->statSummary as $region => $stat){
            if($region == 'services') 
                continue;
                
            $dataSets[$region] = array_values($stat);
        }
        
        $items = [];
        $html = $this->generateBarChart($labels, $dataSets);
        $card = $this->generateCard($id=$this->getHtmlId('hmlStackedChart'), $html, $cardClass='warning', $title='By Criticality', '', $collapse=true);
        $items[] = [$card, ''];
        
        ## PieChart
        $html = $this->generateDonutPieChart($this->statSummary['services'], 'servDoughnut');
        $card = $this->generateCard($id=$this->getHtmlId('servChart'), $html, $cardClass='warning', $title='By Category', '', $collapse=true);
        $items[] = [$card, ''];
        
        $output[] = $this->generateRowWithCol($size=6, $items, "data-context='gdReport'");
        #### SUMMARY ROW ####
        
        
        #### Usage/Settings Table ####
        $map = self::DATASOURCE;
        $tab = [];
        $tab[] = "<table class='table table-sm'>";
        $tab[] = "<thead><tr><th>Region</th>";
        foreach($map as $idx => $val){
            if(is_array($val))
                $map[$idx] = implode(':', $val);
                
            $tab[] = "<th>" . str_replace(":", "<br>", $map[$idx]) . "</th>";
        }
        $tab[] = "<th>Total</th>";
        $tab[] = "</tr></thead>";
        
        $tab[] = "<tbody><tr>";
        foreach($this->settings as $region => $o){
            $tab[] = "<tr>";
            $tab[] = "<td>" . $region . "</td>";
            
            foreach($map as $type){
                $msg = "-";
                if(isset($o[$type])){
                    $d = $o[$type];
                    
                    $hasTrial = $d['FreeTrial'] > 0 ? "(".$d['FreeTrial']."D)" : "";
                    
                    $msg = $d['Enabled'] . ' $' . number_format($d['Usage'], 4) . $hasTrial;
                }
                $tab[] = "<td>" . $msg . "</td>";
            }
            $tab[] = "<td><b>$" . $o['Total'] . "</b></td>";
            $tab[] = "</tr>";
        }
        $tab[] = "</tbody>";
        $tab[] = "</table>";
        
        $html = implode('', $tab);
        unset($tab);
        $card = $this->generateCard($id=$this->getHtmlId('settingTable'), $html, $cardClass='info', $title='Current Settings', '', $collapse=true);
        $items = [[$card, '']];
        
        $output[] = $this->generateRowWithCol($size=12, $items, "data-context='settingTable'");
        
        #### Usage/Settings Table ####
        
        return $output;
    }
    
    function buildContentDetail(){
        $output = [];
        $__h = $__m = $__l = [];
        foreach($this->findings as $idx => $f){
            if(isset($f[8])) $__h[] = $f[8];
            if(isset($f[5])) $__m[] = $f[5];
            if(isset($f[2])) $__l[] = $f[2];
        }
        
        # $out = $this->__groupFindings($__h);
        if(!empty($__h)) $tab[] = $this->__buildFindingsList('High Severity', $__h);
        if(!empty($__m)) $tab[] = $this->__buildFindingsList('Medium Severity', $__m);
        if(!empty($__l)) $tab[] = $this->__buildFindingsList('Low Severity', $__l);
        
        # $tab[] = $this->__buildFindingsList($title, $items);
        
        $html = implode('', $tab);
        unset($tab);
        
        $card = $this->generateCard($id=$this->getHtmlId('findings'), $html, $cardClass='alert', $title='All findings', '', $collapse=true);
        $items[] = [$card, ''];
        
        $output[] = $this->generateRowWithCol($size=12, $items, "data-context='findings'");
        return $output;
    }
    
    function __groupFindings($items){
        $results = [];
        foreach($items as $group => $o){
            foreach($o as $serv => $item){
                if(!isset($results[$serv]))
                    $results[$serv] = [];
                    
                foreach($item as $topic => $detail){
                    
                    if(!isset($results[$serv][$topic]))
                        $results[$serv][$topic] = [];
                    
                    foreach($detail as $idx => $det){
                        if($idx == '__')
                            continue;
                        
                        $results[$serv][$topic]['items'][] = $det;
                        $results[$serv][$topic]['__'] = $detail['__'];
                    }
                }
            }
        }
        
        return $results;
    }
    
    function __buildFindingsList($title, $items){
        $out = $this->__groupFindings($items);
        $tab = [];
        $tab[] = "<h3>$title</h3>";
        foreach($out as $serv => $det){
            $cnt = 0;
            $tab[] = "<ul><li>$serv";
            foreach($det as $topic => $arrayItem){
                $tab[] = "<ul><li><a href='".$arrayItem['__']."'>$topic</a><ul>";
                foreach($arrayItem['items'] as $it){
                    $tab[] = "<li>" . $it['region'] .': (' . $it['Count'].'), '. $it['Title'] . ' | <small>'.$it['Id'] . "</small></li>";
                }
                $tab[] = "</ul>";   #findings
                $tab[] = "</li></ul>"; #topic
            }
            $tab[] = "</li></ul>";  #Service
        }
        
        return implode('', $tab);
    }
}
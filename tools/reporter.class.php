<?php
global $DASHBOARD;

class reporter{
    private $summary = [];
    private $summaryRegion = [];
    private $detail = [];
    private $config = [];
    
    function __construct($service){
        $this->service = $service;
        $serviceReporterJsonPath = SERVICE_DIR . '/' . $service .'/' . $service .'.reporter.json';
        if(!file_exists($serviceReporterJsonPath)){
            echo "[Fatal] $serviceReporterJsonPath not found";
        }
        
        $this->config = json_decode(file_get_contents($serviceReporterJsonPath), true);
        if(empty($this->config)){
            trigger_error("$serviceReporterJsonPath does not contain valid JSON", E_USER_ERROR);   
        }
        
        $generalConfig = json_decode(file_get_contents(GENERAL_CONF_PATH), true);
        $this->config = array_merge($this->config, $generalConfig);
    }
    
    function process($serviceObjs){
        global $DASHBOARD;
        foreach($serviceObjs as $region => $objs){
            foreach($objs as $identifier => $results){
                $this->__process($region, $identifier, $results);
            }
            $DASHBOARD['SERV'][$this->service][$region] = ['Total'=> sizeof($objs), 'H' => 0];
        }
        
        return $this;
    }
    
    function getDetail(){
        return $this->detail;   
    }
    
    function __process($region, $identifier, $results){
        global $DEBUG;
        foreach($results as $key => $info){
            if($info[0] == -1){
                ## Register summary info
                $this->summaryRegion[$key][$region][] = $identifier;
                $this->summary[$key][] = $identifier;
                $this->detail[$region][$identifier][$key] = $info[1];
            }
        }
    }
    
    function __getConfigValue($check, $field){
        if(!array_key_exists($check, $this->config)){
            __info("<$check> not exists in ".$this->service.".reporter.json");
            return null;
        }
        
        if(!array_key_exists($field, $this->config[$check])){
            __info("<$check>::<$field> not exists in ".$this->service.".reporter.json");
            return null;
        }
        
        return $this->config[$check][$field];
    }
    
    function __checkCriticality($check){
        return $this->__getConfigValue($check, 'criticality') ?? 'X';
    }
    
    function __checkCategory($check){
        return $this->__getConfigValue($check, 'category') ?? 'X';
    }
    
    function getSummary(){
        global $DASHBOARD;
        foreach($this->summaryRegion as $check => $dataSet){
            foreach($dataSet as $region => $obj){
                #check criticality
                $critical = $this->__checkCriticality($check);
                if(empty($DASHBOARD['CRITICALITY'][$region][$critical]))
                    $DASHBOARD['CRITICALITY'][$region][$critical] = 0;
                $DASHBOARD['CRITICALITY'][$region][$critical]+= sizeof($obj);
                
                if($critical == 'H'){
                    $DASHBOARD['SERV'][$this->service][$region]['H'] += sizeof($obj);
                }
                
                #check category
                $category = $this->__checkCategory($check);
                $mainCategory = $category[0];
                if(empty($DASHBOARD['CATEGORY'][$region][$mainCategory]))
                    $DASHBOARD['CATEGORY'][$region][$mainCategory] = 0;
                    
                $DASHBOARD['CATEGORY'][$region][$mainCategory]+= sizeof($obj);
            }
        }
        
        $this->cardSummary = [];
        
        $service = $this->service;
        
        ksort($this->summary);
        foreach($this->summary as $check => $items){
            if(!array_key_exists($check, $this->config)){
                __info("<$check> not exists in ".$service.".reporter.json");
                continue;
            }
            
            $this->cardSummary[$check] = $this->config[$check];
            
            ## Process Field by Field:
            #   Process description
            $desc = $this->__getConfigValue($check, '^description');
            if($desc){
                $COUNT = sizeof($items);
                $COUNT = "<strong><u>$COUNT</u></strong>";
                eval("\$x = \"$desc\";");
                
                $this->cardSummary[$check]['^description'] = $x;
            }
            
            #   Process category
            $category = $this->__getConfigValue($check, 'category');
            if($category){
                $this->cardSummary[$check]['__categoryMain'] = $category[0];
                if(strlen($category) > 1)
                    $this->cardSummary[$check]['__categorySub'] = substr($category, 1);
                
                unset($this->cardSummary[$check]['category']);
            }
            
            #   Process ref
            $ref = $this->__getConfigValue($check, 'ref');
            if($ref && is_array($ref)){
                $links = [];
                foreach($ref as $link){
                    preg_match('/\[(.*)\]<(.*)>/', $link, $output);
                    if(empty($output))
                        continue;
                    
                    $links[] = "<a href='{$output[2]}'>{$output[1]}</a>";
                }
                
                $this->cardSummary[$check]['__links'] = $links;
                unset($this->cardSummary[$check]['ref']);
            }
            
            $resourceByRegion = [];
            foreach($this->summaryRegion[$check] as $region => $insts){
                $resourceByRegion[$region] = $insts;
            }
            
            $this->cardSummary[$check]['__affectedResources'] = $resourceByRegion;
        }
        
        unset($this->summaryRegion);
        unset($this->summary);
        
        # __pr($this->cardSummary);
        return $this;
    }
    
    function getDetails(){
        foreach($this->detail as $region => $detail){
            foreach($detail as $identifier => $checks){
                $htmlAttribute = "data-resource='".$identifier."' data-region='$region'";
                ksort($checks);
                unset($this->detail[$region][$identifier]);
                foreach($checks as $key => $info){
                    $arr = $this->getDetailAttributeByKey($key);
                    $arr['value'] = $info;
                    $this->detail[$region][$identifier][$key] = $arr;
                }
            }
        }
        
        unset($this->config);
    }
    
    function getDetailAttributeByKey($key){
        static $config = [];
        if(empty($config[$key])){
            $arr = [                
                'category' => $this->__getConfigValue($key, 'category'),
                'criticality' => $this->__getConfigValue($key, 'criticality'),
                'shortDesc' => $this->__getConfigValue($key, 'shortDesc')  
            ];
            
            $category = $arr['category'];
            if($category){
                $arr['__categoryMain'] = $category[0];
                if(strlen($category) > 1)
                    $arr['__categorySub'] = substr($category, 1);
                
                unset($arr['category']);
            }
            
            $config[$key] = $arr;
        }
        
        return $config[$key];
    }
}



if(isset($_GET['test'])){
    global $DEBUG;
    $DEBUG=1;
    include_once(__DIR__.'/../bootstrap.inc.php');
    include_once(SERVICE_DIR.'/pageBuilder.class.php');
    include_once(SERVICE_DIR.'/rds/rds.pageBuilder.php');
    
    $regions = ['ap-southeast-1'];
    $services = ['rds' => 2,'ec2' => 3, 'iam' => 20];
    $obj = [
        'ap-southeast-1' => [
            'postgres::g2gtest' => [
                'MultiAZ' => [-1, 'Off'],
                'EngineVersionMajor' => [1, 'On']
            ],
            'mysql::mysql-5' => [
                'MultiAZ' => [-1, 'Off'],
                'EngineVersionMajor' => [-1, 'Off']
            ],
            'mysql::mysql-bad' => [
                'MultiAZ' => [-1, 'Off'],
                'EngineVersionMajor' => [-1, 'Off']
            ]
        ],
        'us-east-1' => [
            'oracle::oracletest' => [
                'MultiAZ' => [-1, 'Off'],
                'EngineVersionMajor' => [1, 'On']
            ]
        ]
    ];
    $reporter = new reporter('rds');   
    $reporter->process($obj)
        ->getSummary()
        ->getDetails()
        ;
        
    $pageBuilder = new rdsPageBuilder('rds', $reporter, $services, $regions);
    $pageBuilder->buildPage();
}

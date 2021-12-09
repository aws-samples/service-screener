<?php

if(!file_exists(__DIR__.'/tools/console-table/src/LucidFrame/Console/ConsoleTable.php')){
    exit("ConsoleTable module not found, run \"git submodule init\"" . PHP_EOL);   
}

require_once(__DIR__.'/tools/console-table/src/LucidFrame/Console/ConsoleTable.php');
include_once(__DIR__.'/bootstrap.inc.php');

const PILLARLOOKUP = [
    "O" => "Operation Excellence",
    "S" => "Security",
    "R" => "Reliability",
    "P" => "Performance Efficiency",
    "C" => "Cost Optimization"
];

const CRITICALITY = [
    "I" => "Informational",    
    "L" => "Low Risk",    
    "M" => "Medium Risk",    
    "H" => "High Risk"
];

$serviceItems = scandir(SERVICE_DIR);

$folders = $reporterJsons = [];
foreach($serviceItems as $item){
    if(!in_array($item, [".", ".."]) && is_dir(SERVICE_DIR."/".$item)){
        $folders[] = $item;
    }
}



foreach($folders as $folder){
    $jsonPath = SERVICE_DIR."/".$folder."/".$folder.".reporter.json";
    if(file_exists($jsonPath)){
        $reporterJsons[$folder] = $jsonPath;
    }
}

printPillarSummary($reporterJsons);
echo "\n";
printCriticalitySummary($reporterJsons);

function addPad($inp, $sep = ".", $lr = STR_PAD_LEFT){
    ## Odd numbers look cleaner
    return str_pad($inp, 7, $sep, $lr);   
}

function printPillarSummary($reporterJsons){
    $ts = 0;
    $total = [
        'C' => 0,
        'P' => 0,
        'S' => 0,
        'R' => 0,
        'O' => 0
    ];
    $pillarTable = new LucidFrame\Console\ConsoleTable();
    $pillarTable->addHeader('Service');
    
    foreach(PILLARLOOKUP as $pillar => $v)
        $pillarTable->addHeader( addPad($pillar, '_', STR_PAD_BOTH));
    
    $pillarTable->addHeader(addPad('TOTAL', '_', STR_PAD_BOTH));
    
    foreach($reporterJsons as $service => $jsonPath){
        $pillarSumm = pillarSummary($jsonPath);
        
        $pillarTable->addRow();
        $pillarTable->addColumn($service);
        
        foreach(PILLARLOOKUP as $code => $desc){
            if(isset($pillarSumm[$code])){
                $pillarTable->addColumn( addPad($pillarSumm[$code]) );
            }else{
                $pillarTable->addColumn( ' / ');
            }
        }
        
        $s = array_sum($pillarSumm);
        $ts += $s;
        $pillarTable->addColumn(addPad($s));
        
        foreach($pillarSumm as $k => $v){
            $total[$k] += $v;   
        }
    }
    
    $totalServ = sizeof($reporterJsons);
    
    $pillarTable->addRow();
    $pillarTable->addColumn('--------');
    $pillarTable->addRow();
    $pillarTable->addColumn('Total');
    foreach(PILLARLOOKUP as $code => $desc){
        $pillarTable->addColumn( addPad($total[$code]) );
    }
    
    $pillarTable->addColumn( addPad($ts));
    
    $pillarTable->addRow();
    $pillarTable->addColumn('Mean');
    foreach(PILLARLOOKUP as $code => $desc){
        $pillarTable->addColumn( addPad(round($total[$code] / $totalServ, 2)));
    }
    
    $pillarTable->addColumn( addPad(round($ts / $totalServ, 2)));
    
    echo "Service Rules Pillar Count:";
    echo "\n";
    $pillarTable->display();
    
    return;
}

function printCriticalitySummary($reporterJsons){
    $ts = 0;
    $total = [
        'I' => 0,
        'L' => 0,
        'M' => 0,
        'H' => 0
    ];
    
    $critcalityTable = new LucidFrame\Console\ConsoleTable();
    $critcalityTable->addHeader('Service');
    
    foreach(CRITICALITY as $criticality => $v)
        $critcalityTable->addHeader(addPad($criticality, '_', STR_PAD_BOTH));
    
    $critcalityTable->addHeader(addPad('TOTAL', '_', STR_PAD_BOTH));
    
    foreach($reporterJsons as $service => $jsonPath){
        $criticalitySumm = criticalitySummary($jsonPath);
        
        $critcalityTable->addRow();
        $critcalityTable->addColumn($service);
        
        
        foreach(CRITICALITY as $code => $desc){
            if(isset($criticalitySumm[$code])){
                $critcalityTable->addColumn(addPad($criticalitySumm[$code]));
            }else{
                $critcalityTable->addColumn( ' / ');
            }
        }
        
        $s = array_sum($criticalitySumm);
        $ts += $s;
        $critcalityTable->addColumn(addPad($s));
        
        foreach($criticalitySumm as $k => $v){
            $total[$k] += $v;   
        }
    }
    
    $totalServ = sizeof($reporterJsons);
    
    $critcalityTable->addRow();
    $critcalityTable->addColumn('--------');
    $critcalityTable->addRow();
    $critcalityTable->addColumn('Total');
    foreach(CRITICALITY as $code => $desc){
            $critcalityTable->addColumn( addPad($total[$code]));
    }
    
    $critcalityTable->addColumn( addPad($ts));
    
    $critcalityTable->addRow();
    $critcalityTable->addColumn('Mean');
    foreach(CRITICALITY as $code => $desc){
        $critcalityTable->addColumn( addPad(round($total[$code] / $totalServ, 2)));
    }
    
    $critcalityTable->addColumn( addPad(round($ts / $totalServ, 2)));
    
    echo "Service Rules Criticality Count:";
    echo "\n";
    $critcalityTable->display();
    
    return;
}



function pillarSummary($jsonPath){
    $rules = json_decode(file_get_contents($jsonPath), true);
    $pillarCnt = [];
    
    foreach($rules as $rule => $info){
        $category = $info['category'];
        
        $firstCat = substr($category, 0, 1);
        if(!isset($pillarCnt[$firstCat])){
            $pillarCnt[$firstCat] = 1;
        }else{
            $pillarCnt[$firstCat]++;
        }
    }
    
    return $pillarCnt;
    
}

function criticalitySummary($jsonPath){
    $rules = json_decode(file_get_contents($jsonPath), true);
    $criticalityCnt = [];
    
    foreach($rules as $rule => $info){
        $criticality = $info['criticality'];
        
        if(!isset($criticalityCnt[$criticality])){
            $criticalityCnt[$criticality] = 1;
        }else{
            $criticalityCnt[$criticality]++;
        }
    }
    
    return $criticalityCnt;
}

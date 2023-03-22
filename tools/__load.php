<?php 
include_once(__DIR__ .'/config.php');
include_once(__DIR__ .'/aws-sdk-custom-credential-provider.php');
include_once(__DIR__ .'/arguParser.class.php');
include_once(__DIR__ .'/pageBuilder.class.php');
include_once(__DIR__ .'/reporter.class.php');
include_once(__DIR__ .'/feedback.class.php');
include_once(__DIR__ .'/policy.class.php');
include_once(__DIR__ .'/uploader.class.php');
include_once(__DIR__ .'/excelBuilder.class.php');

function __pr($o){
    global $DEBUG;
    
    if($DEBUG){
        print_r($o);
        echo PHP_EOL;
    }
}

function __info($o){
    __printStatus("info", $o);
}

function __warn($o){
    __printStatus("\033[1;41m__!! WARNING !!__\033[0m", $o);
}

function __printStatus($status, $o){
    $o = "[$status] " . $o;
    __pr($o);
}

function __formatException($e){
    $o = $e->getTrace();
    #$o = array_reverse($o);
    $msg = ["[ERROR]: ".$e->getMessage()."\n"];
    foreach($o as $ind => $item){
        $cls = '';
        if(!empty($item['class'])) $cls = $item['class'].'::';
    
        $func = $item['function'];
        $line = $item['line'];
        $file = $item['file'];
        
        $args = [];
        foreach($item['args'] as $arg){
            if(is_object($arg)) $args[] = "CLASS=".get_class($arg);
            else if (is_array($arg)){
                array_walk($arg, function(&$v, $k){
                    $val = $v;
                    if(is_array($v)) $val = 'ARRAY(...)';
                    else if(is_object($v)) $val = 'OBJECT(...)';
                    $v = "{$k}:$val";
                });
        
                $args[] = implode(', ', $arg);
            }else $args[] = $arg;
        }
    
        $args = implode(', ', $args);
        $indent = "";
        for($i=1;$i<=$ind;$i++)
            $indent .= "\t";
    
        $msg[] = $indent . "[$cls$func($args)]: $file ($line)\n";
    }
    
    return implode('', $msg);
}

function __aws_parseInstanceFamily($instanceFamilyInString){
    $arr = explode('.', $instanceFamilyInString);
    if(sizeof($arr) > 3 || sizeof($arr) == 1){
        return $instanceFamilyInString;
    }
    
    if(sizeof($arr) == 3 && strtolower($arr[0]) =="db"){
        $p = $arr[1];
        $s = $arr[2];
    }else{
        $p = $arr[0];
        $s = $arr[1];
    }
    
    $patterns = "/([a-zA-Z]+)(\d+)([a-zA-Z]*)/i";
        $result = preg_match(
        $patterns, 
        $p, 
        $output
    );
    
    $result = [
        "full" => $instanceFamilyInString,
        "prefix" => $p,
        "suffix" => $s,
        "prefixDetail" => [
            "family" => $output[1],
            "version" => $output[2],
            "attributes" => $output[3]
        ]
    ];
    
    return $result;
}
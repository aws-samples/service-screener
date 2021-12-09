<?php
# include_once(__DIR__ .'/../bootstrap.inc.php');
# $services = ['ec2','rds'];
# $regions = ['ap-southeast-1'];
# feedback::send($services, $regions);

class feedback{
    const IPLOCATION_CACHE_TTL_SEC = 60*60;
    const TEMP_CACHE_TTL_SEC = 60*10;
    const SESS_EXPIRED = 60*10;
    
    const API_URL=[
        'getIp' => [
            'method' => 'GET',
            'url' => 'https://b3vj78hfp6.execute-api.ap-southeast-1.amazonaws.com/prod/feedback/ip'
        ],
        'checkLocationInfoFromDDB' => [
            'method' => 'GET',
            'url' => 'https://b3vj78hfp6.execute-api.ap-southeast-1.amazonaws.com/prod/feedback/location'
        ],
        'getLocationInfoFromPublicDB' => [
            'method' => 'GET',
            'url' => 'http://ip-api.com/json/'
        ],
        'setLocationInfoByIp' => [
            'method' => 'POST',
            'url' => 'https://b3vj78hfp6.execute-api.ap-southeast-1.amazonaws.com/prod/feedback/location'
        ],
        'sendFeedback' => [
            'method' => 'POST',
            'url' => 'https://b3vj78hfp6.execute-api.ap-southeast-1.amazonaws.com/prod/feedback/send',
        ]
    ];
    
    static function send($services, $regions){
        $sesspath = FORK_DIR.'/'.SESSUID_FILENAME;
        $date = new DateTime();
        if(!file_exists($sesspath) || $date->getTimestamp() - filemtime($sesspath) > self::SESS_EXPIRED){
            $servInStr = implode(',', $services);
            $regInStr = implode(',', $regions);
            
            $key = md5($servInStr . '::' . $regInStr);
            
            $dstr = $date->format('siHdmY');
            $sesskey = uniqid($dstr . $key);
            file_put_contents($sesspath, $sesskey);
        }else{
            $sesskey = file_get_contents($sesspath);
            file_put_contents($sesspath, $sesskey); ## trigger filemtime;
        }
        
        $myIp = self::__sendApi('getIp');
        $locationInfo = self::__sendApi('checkLocationInfoFromDDB', ['ipaddr' => $myIp]);
        $info = json_decode($locationInfo, true);
        if($info['Count']==0){
            $result = self::__sendApi('getLocationInfoFromPublicDB', $myIp);
            $result = json_decode($result, true);
            $result['ipaddr'] = $result['query'];
            unset($result['query']);
            unset($result['status']);
            
            $result['expTime'] = time() + (self::IPLOCATION_CACHE_TTL_SEC);
            
            # print_r($result); die();
            $result = self::__buildDDBItems($result);
            $resp = self::__sendApi('setLocationInfoByIp', $result);
            # print_r($resp);
        }else{
            $result = $info['Items'][0];   
        }
        
        foreach($result as $key => $value){
            $result[$key] = array_values($value)[0];   
        }
        
        $ddb = [
            'sesskey' => $sesskey,
            'countryCode' => $result['countryCode'],
            'ipaddr' => $result['ipaddr'],
            'city'  => $result['city'],
            'services' => implode(",", $services),
            'regions' => implode(",", $regions),
            'date' => $date->format('Y-m-d'),
            'version' => CONFIG::ADVISOR['VERSION'],
            'exptime' => time() + (self::TEMP_CACHE_TTL_SEC)
        ];
        
        $ddb = self::__buildDDBItems($ddb);
        $resp = self::__sendApi('sendFeedback', $ddb);
        # print_r($resp);
    }
    
    static function __sendApi($api, $body=''){
        $ch = curl_init();
        
        $apiConfig = self::API_URL[$api];
        $url = $apiConfig['url'];
        if(!is_array($body)){
            $url .= $body;
        }else{
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $apiConfig['method'] );
        
        if($apiConfig['method']=='POST'){
            curl_setopt($ch, CURLOPT_POST, 1);
        }
        
        $output = curl_exec($ch);
        curl_close($ch);
        
        return $output;
    }
    
    static function __buildDDBItems($arr){
        foreach($arr as $key => $value){
            ## Not using shorthand, keep for future customization
            $datatype = "S";
            if($key == 'expTime' || is_numeric($value)){
                $datatype = "N";
            }
            
            if(empty(trim($value))){
                unset($arr[$key]);
            }else{ 
                $arr[$key] = [$datatype => strval($value)];
            }
        }
        
        return $arr;
    }
}
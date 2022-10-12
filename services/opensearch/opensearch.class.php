<?php 
use Aws\OpenSearchService\OpenSearchServiceClient;
use AWS\OpenSearchService\Exception;

class opensearch extends service{
    private $openSearchClient;
    function __construct($region){
        parent::__construct($region);
    
        $this->__AWS_OPTIONS['version'] = CONFIG::AWS_SDK['AOSCLIENT_VERS'];
        
        $this->openSearchClient = new OpenSearchServiceClient($this->__AWS_OPTIONS);
        $this->__loadDrivers();

        ##
        #$result = $this->openSearchClient->describeDomain(['DomainName' => 'es-log-search']);
        #__pr($result);
        #die();
    }
    
    function getResources(){
        $arr = array();
        try{
            $results = $this->openSearchClient->listDomainNames();
        }catch(AWS\OpenSearchService\Exception\OpenSearchServiceException $e){ 
            __pr($e->getAwsErrorCode());   
            
        }
        
        ## TODO; if need to listDomainNames by pagination
        $domains = $results->get('DomainNames');
        return $domains;

        // $rows = $results->get('Content');
        // $row = explode("\n", $rows);
        // $fields = explode(',', $row[0]);
        // unset($row[0]);
        // foreach($row as $temp){
        //     $arr[] = array_combine($fields, explode(',', $temp));
        // }
        
        return $arr;
    }
    
    function advise(){
        $domains = $this->getResources();
        $objs = [];
        
        $driver = 'opensearch_common';
        if(class_exists($driver)){
            __info('... (OpenSearch::Common) inspecting');
            
            foreach($domains as $d){
                $domainName = $d['DomainName'];
                $obj = new $driver($domainName, $this->openSearchClient);
                $obj->run();
               
                #$objs['OpenSearch::Common'] = $obj->getInfo();
                $objs['OpenSearch::' .$domainName] = $obj->getInfo();
            }
        }
        

        return $objs;
    }
    
    function __loadDrivers(){
        $path = __DIR__ .'/drivers/';
        $files = scandir($path);
        foreach($files as $file){
            if ($file[0] == '.')
                continue;
            
            include_once($path . $file);
        }
    }
}
<?php

class opensearch_common extends evaluator{
    public $results = [];
    
    
    function __construct($domain, $opensearchClient){
        $this->domain = $domain;
        $this->opensearchClient = $opensearchClient;
        $this->__configPrefix = 'openSearch::';
        
        $this->attribute = $this->opensearchClient->describeDomain(
            ['DomainName' => $this->domain]
        );
        $this->cluster_config = $this->attribute['DomainStatus']['ClusterConfig'];

        $this->init();
    }
    
    // function __checkMock(){
    //     # print_r($this->domain);
    //     $this->results['MockTest'] = [1, 'mockTest'];
    // }

    function __checkMasterNodes(){
        // __pr($this->domain);
        // $resp = $this->opensearchClient->describeDomain([
        //     'DomainName' => $this->domain]);
        $enabled = $this->cluster_config['DedicatedMasterEnabled'];
        $nodes = $this->cluster_config['DedicatedMasterCount'];
        $this->results['DedicatedMasterNodes'] = [0, 'No dedicated master nodes'];
        if($enabled){
            if($nodes < 3 )
                $this->results['DedicatedMasterNodes'] = [-1, 'Insufficient dedicated master nodes'];
                return;
            if($nodes%2 == 0)
                $this->results['DedicatedMasterNodes'] = [-1, 'Wrong number of dedicated master nodes'];
                return;
            $this->results['DedicatedMasterNodes'] = [1, 'Sufficient dedicated master nodes'];
        }
    }

    function __checkAvailabilityZones(){
        $enabled = $this->cluster_config['ZoneAwarenessEnabled'];
        $this->results['AvailabilityZones'] = [-1, 'Multi-AZ not enabled'];
        if($enabled){
            $this->results['AvailabilityZones'] = [1, 'Multi-AZ enabled'];
        }
    }

    function __checkServiceSoftwareVersion(){
        $this->results['ServiceSoftwareVersion'] = [1, 'Latest'];
        $resp = $this->attribute['DomainStatus']['ServiceSoftwareOptions']['UpdateAvailable'];
        if($resp)
            $this->results['ServiceSoftwareVersion'] = [-1, 'Upgrade to latest version'];
        // __pr($this->results);
        // $resp = $this->opensearchClient->getCompatibleVersions([
        //     'DomainName' => $this->domain,
        // ]);
        // // $source = $resp['CompatibleVersions'][0]['SourceVersion'];
        // $target = $resp['CompatibleVersions'][0]['TargetVersions'];
        // // __pr($target);
        // // $version = $this->attribute['DomainStatus']['EngineVersion'];
        // if (count($target) == 0) {
        //     $this->results['ServiceSoftwareVersion'] = [1, 'Latest'];
        //     // __pr('Latest sw running');
        // } else {
        //     $this->results['ServiceSoftwareVersion'] = [-1, 'Upgrade to latest version'];
        //     // __pr('Later versions of sw available');
        // }
    }

    // Open access is already restricted unconditionally. TODO: In the future, look for ways/patterns to further restrict access policy

    // function __checkResourceAccessPolicy(){
        
        // $this->results['ResourceAccessPolicy'] = [-1, 'Allow All'];
        // $resp = $this->opensearchClient->describeDomainConfig([
        //     'DomainName' => $this->domain,
        // ]);
        // // $source = $resp['CompatibleVersions'][0]['SourceVersion'];
        // $target = $resp['CompatibleVersions'][0]['TargetVersions'];
        // // __pr($target);
        // // $version = $this->attribute['DomainStatus']['EngineVersion'];
        // if (count($target) == 0) {
        //     $this->results['ServiceSoftwareVersion'] = [1, 'Latest'];
        //     // __pr('Latest sw running');
        // } else {
        //     $this->results['ServiceSoftwareVersion'] = [-1, 'Upgrade to latest version'];
        //     // __pr('Later versions of sw available');
        // }
    // }

    function __checkFineGrainedAccessControl(){
        $this->results['FineGrainedAccessControl'] = [-1, 'Not enabled'];
        // __pr($this->domain);
        // __pr($this->attribute['DomainStatus']['AdvancedSecurityOptions']['Enabled']);
        $resp = $this->attribute['DomainStatus']['AdvancedSecurityOptions']['Enabled'];
        if($resp)
            $this->results['FineGrainedAccessControl'] = [1,'Enabled'];
    }

    function __checkDomainWithinVPC(){
        $this->results['DomainWithinVPC'] = [1, 'Within VPC'];
        // __pr($this->domain);
        if(empty($this->attribute['DomainStatus']['VPCOptions']))
            $this->results['DomainWithinVPC'] = [-1, 'Public'];
        // $resp = $this->attribute['DomainStatus']['AdvancedSecurityOptions']['Enabled'];
        // if($resp)
        //     $this->results['DomainWithinVPC'] = [1,'Enabled'];
    }

}
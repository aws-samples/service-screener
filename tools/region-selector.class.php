<?php

use Aws\Account\AccountClient;

class AwsRegionSelector {

    static function getAllEnabledRegions(bool $minimal = false){
        global $DEBUG;
        if(!$minimal && self::promptConfirmGetAllRegions() == false){
           die('__SCRIPT HALT__, user decided not to proceed');
        }
        
        $arr['region'] = 'us-east-1';
        $arr['version'] = CONFIG::AWS_SDK['ACCOUNTCLIENT_VERS'];
        $acct = new AccountClient($arr);
        
        $results = [];
        $regions = [];
        do{
            $params['RegionOptStatusContains'] = ['ENABLED', 'ENABLED_BY_DEFAULT'];
            $params['MaxResults'] = 20;
            if(!empty($results) && !empty($results->get('NextToken')))
                $params['NextToken'] = $results->get('NextToken');
            
            $results = $acct->listRegions($params);
            foreach($results->get('Regions') as $info){
                $regions[] = $info['RegionName'];
            }
        }while(!empty($results->get('NextToken')));
        
        if($DEBUG && !$minimal){
            __pr("The following region(s) are enabled/opt-in");
            __pr('[' . sizeof($regions) . "] | " .implode(', ', $regions));
        }
        
        return $regions;
    }
    
    static function promptConfirmGetAllRegions(){
        echo PHP_EOL;
        __warn("You specify --region as ALL. It will loop through all ENABLED/OPT-IN regions and it is going to take sometime to complete.");
    
        $attempt = 0;
        do {
            if($attempt > 0)
                __warn("You have entered an invalid option. Please try again.");
            
            $confirm = strtolower(readline("Do you want to process? Please enter 'y' for yes, 'n' for no: "));    
            $attempt++;
        } while(!in_array($confirm, ['y', 'n']));
    
        if ($confirm == 'y') {
            return true;
        }
        
        return false;
    }

    /**
     * @throws InvalidArgumentException
     * @return string
     */
    static function promptForRegion(): string | bool {
        $regions = self::getAllEnabledRegions(minimal: true); # Reuse existing function

        echo "--------------------------------------" . PHP_EOL;
        __info("Available regions:");
        foreach ($regions as $region) {
            __info($region) . PHP_EOL;
        }
        echo "--------------------------------------" . PHP_EOL;

        $selectedRegions = readline("Select regions to scan (comma separated): ");
        if (empty($selectedRegions)) {
            return False;
        }

        $selectedRegions = array_map('trim', array_map('strtolower', explode(',', str_replace(' ', '', $selectedRegions))));
        foreach ($selectedRegions as $region) {
            /**
             * Quality of life (QoL) improvements:
             * trim() - remove leading and trailing spaces
             * strtolower() - convert to lowercase
             */
            if (!in_array(trim(strtolower($region)), $regions)) {
                __warn("Region $region is not valid. Skipping..."); # Don't exit, just skip. Best practices.
                unset($selectedRegions[array_search($region, $selectedRegions)]);
            }
        }

        # Convert back to comma separated string
        return implode(',', $selectedRegions);
    }

}

<?php

use Aws\Ec2\Ec2Client;

class AwsRegionSelector {

    /**
     * @throws InvalidArgumentException
     * @return string
     */
    static function selectRegion(): string | bool {
        $regions = [];

        $ec2Client = new Ec2Client([
            'version' => 'latest',
            'region' => 'us-east-1'
        ]);

        $regions = $ec2Client->describeRegions()->get('Regions');

        echo "--------------------------------------" . PHP_EOL;
        __info("Available regions:");
        foreach ($regions as $region) {
            __info($region['RegionName']) . PHP_EOL;
            $regions[] = trim(strtolower($region['RegionName']));
        }
        echo "--------------------------------------" . PHP_EOL;

        $selectedRegions = readline("Select regions to scan (comma separated): ");
        if (empty($selectedRegions)) {
            return False;
        }

        $selectedRegions = explode(',', $selectedRegions);
        foreach ($selectedRegions as $region) {
            /**
             * Quality of life (QoL) improvements:
             * trim() - remove leading and trailing spaces
             * strtolower() - convert to lowercase
             */
            if (!in_array(trim(strtolower($region)), array_column($regions, 'RegionName'))) {
                throw new \InvalidArgumentException(sprintf('Invalid region "%s"', $region));
            }
        }

        # Convert back to comma separated string
        return implode(',', $selectedRegions);
    }

}

<?php

class AwsRegionSelector {

    /**
     * @throws InvalidArgumentException
     * @return string
     */
    static function selectRegion(): string | bool {
        $regions = __getAllEnabledRegions(minimal: true); # Reuse existing function

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

        $selectedRegions = explode(',', $selectedRegions);
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

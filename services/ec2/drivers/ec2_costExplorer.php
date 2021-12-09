<?php

class ec2_costExplorer extends evaluator{
    public $results = [];
    
    function __construct($explorerClient){
        $this->explorerClient = $explorerClient;
        
        $this->init();
        
    }
    
    function __checkReservedInstanceRecommend(){
        
        try{
            $results = $this->explorerClient->getReservationPurchaseRecommendation([
                'Service' => 'Amazon Elastic Compute Cloud - Compute'
            ]);
        }catch(Exception $e){
            __info('Cost Explorer Reserved Instance inspection is getting following error:');
            __info($e->getAwsErrorMessage());
        }
        
        if(!empty($results['Recommendations'])){
            $this->results['CEReservedInstance'] = ['-1', ''];
        }
        return;
    }
    
    function __checkSavingPlansRecommend(){
        
        try{
            $results = $this->explorerClient->getSavingsPlansPurchaseRecommendation([
                'LookbackPeriodInDays' => 'THIRTY_DAYS',
                'PaymentOption' => 'NO_UPFRONT',
                'SavingsPlansType' => 'COMPUTE_SP',
                'TermInYears' => 'ONE_YEAR'
                
            ]);
        }catch(Exception $e){
            __info('Cost Explorer Savings Plans inspection is getting following error:');
            __info($e->getAwsErrorMessage());
        }
        
        if(isset($results['SavingsPlansPurchaseRecommendation']) && !empty($results['SavingsPlansPurchaseRecommendation']['SavingsPlansPurchaseRecommendationDetails'])){
            $this->results['CESavingsPlans'] = ['-1', ''];
        }
        return;
    }
}
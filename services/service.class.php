<?php 

class service{
    protected $__AWS_OPTIONS;
    protected $RULESPREFIX;
    function __construct($region){
        global $CONFIG, $DEBUG;
        global $PHPSDK_CRED_PROVIDER;
        
        $classname = get_class($this);
        
        $suffix = !in_array($classname, CONFIG::GLOBAL_SERVICES) ? " on region <" . $region . ">" : '';
        __info("Scanning " . $classname . $suffix);
        
        $this->RULESPREFIX = $classname.'::rules';
        $this->__AWS_OPTIONS = $CONFIG->get("__AWS_OPTIONS");
        $this->__AWS_OPTIONS['region'] = $region;
        
        if(isset($PHPSDK_CRED_PROVIDER))
            $this->__AWS_OPTIONS['credentials'] = $PHPSDK_CRED_PROVIDER;
    }
    
    function setRules($rules){
        global $CONFIG;
        
        $rules = explode('^', strtolower($rules));
        $CONFIG->set( $this->RULESPREFIX, $rules);
    }
    
    function __destruct(){
        global $CONFIG;
        $CONFIG->set( $this->RULESPREFIX, []);
    }
}
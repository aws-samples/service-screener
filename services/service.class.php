<?php 

class service{
    protected $__AWS_OPTIONS;
    protected $RULESPREFIX;
    protected $tags = [];
    
    CONST TAGS_SEPARATOR='%';
    CONST KEYVALUE_SEPARATOR='=';
    CONST VALUES_SEPARATOR=',';
    function __construct($region){
        global $CONFIG, $DEBUG;
        global $PHPSDK_CRED_PROVIDER, $PHPSDK_CRED_PROFILE;
        
        $classname = get_class($this);
        
        $suffix = !in_array($classname, CONFIG::GLOBAL_SERVICES) ? " on region <" . $region . ">" : '';
        __info("Scanning " . $classname . $suffix);
        
        $this->RULESPREFIX = $classname.'::rules';
        $this->__AWS_OPTIONS = $CONFIG->get("__AWS_OPTIONS");
        $this->__AWS_OPTIONS['region'] = $region;
        
        if(isset($PHPSDK_CRED_PROVIDER))
            $this->__AWS_OPTIONS['credentials'] = $PHPSDK_CRED_PROVIDER;
        else if(isset($PHPSDK_CRED_PROFILE))
            $this->__AWS_OPTIONS['profile'] = $PHPSDK_CRED_PROFILE;
    }
    
    function setRules($rules){
        global $CONFIG;
        
        $rules = explode('^', strtolower($rules));
        $CONFIG->set( $this->RULESPREFIX, $rules);
    }
    
    ## Support --filters
    function setTags($tags){
        $rawTags = [];
        if(empty($tags))
            return;
        
        $result = [];
        $t = explode(self::TAGS_SEPARATOR, $tags);
        foreach($t as $tag){
            list($k, $v) = explode(self::KEYVALUE_SEPARATOR, $tag);
            $rawTags = [$k => explode(self::VALUES_SEPARATOR, $v)];
            $result[] = ["Name"=>"tag:".$k, "Values"=>explode(self::VALUES_SEPARATOR, $v)];
        }
        
        $this->__tags = $rawTags;
        $this->tags = $result;
    }
    
    function __destruct(){
        global $CONFIG;
        $CONFIG->set( $this->RULESPREFIX, []);
    }
    
    function resourceHasTags($tags){
        if(empty($this->__tags))
            return true;
        
        if(empty($tags))
            return false;
        
        $formattedTags = [];
        foreach($tags as $tag){
            $formattedTags[$tag['Key']] = $tag['Value'];
        }
        
        $filteredTags = $this->__tags;
        
        foreach($filteredTags as $key => $value){
            if(!array_key_exists($key, $formattedTags))
                return false;
            
            $cnt = 0;
            foreach($value as $val){
                if($formattedTags[$key] == $val){
                    $cnt++;
                    break;
                }
            }
            
            if($cnt == 0)
                return false;
        }
        
        return true;
    }
}
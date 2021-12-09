<?php 

class policy {
    public $fullAccessList = [
        'oneService' => false,
        'fullAdmin' => false
    ];
    
    function __construct($document){
        $this->doc = json_decode($document, true);
    }
    
    function inspectAccess(){
        $doc = $this->doc;
        foreach($doc['Statement'] as $statement){
            if($statement['Effect'] != 'Allow')
                continue;
                
            $actions = $statement['Action'];
            $actions = is_array($actions) ? $actions : [$actions];
            
            foreach($actions as $action){
                $perm = explode(':', $action);
                
                if(sizeof($perm) != 1){
                    list($serv, $perm) = $perm;
                }else{
                    $serv = $perm = '*';   
                }
                
                if($perm == '*'){
                    $this->fullAccessList['oneService'] = true;
                }
                
                if($perm == '*' && $serv == '*'){
                    $this->fullAccessList['fullAdmin'] = true;
                    return;
                }
            }
        }
        
        return false;
    }
    
    public function hasFullAccessToOneResource(){
        return $this->fullAccessList['oneService'];
    }
    
    public function hasFullAccessAdmin(){
        return $this->fullAccessList['fullAdmin'];
    }
}
<?php 
## All services extends this:
class evaluator{
    protected $results = [];
    protected function init(){
        $this->classname = get_class($this);
    }
    
    public function run(){
        global $CONFIG;
        $servClass = explode('_', $this->classname);
        $rulePrefix = $servClass[0] . '::rules';
        $rules = $CONFIG->get($rulePrefix, []);
        
        $ecnt = $cnt = 0;
        $emsg = [];
        foreach(get_class_methods($this) as $method){
            if(substr($method, 0, 7) == '__check'){
                if(empty($rules) || in_array(strtolower(substr($method, 7)), $rules)){
                    try{
                        $this->$method();
                        $cnt++;
                    }catch(Exception $e){
                        $ecnt++;
                        $emsg[] = __formatException($e);   
                    }
                }
            }
        }
        
        if(!empty($emsg)){
            __warn("Catch: $ecnt exception(s)");
            file_put_contents(FORK_DIR .'/error.txt', implode("\n\n", $emsg), FILE_APPEND | LOCK_EX);
        }
        
        $scanned = $CONFIG->get('scanned');
        $CONFIG->set('scanned', [
            'resources' => $scanned['resources'] + 1, 
            'rules' => $scanned['rules'] + $cnt,
            'exceptions' => $scanned['exceptions'] + $ecnt
        ]);
    }
    
    public function showInfo(){
        echo "Class: ". $this->classname;
        __pr($this->getInfo());
    }
    
    public function getInfo(){
        return $this->results;
    }
}
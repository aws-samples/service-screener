<?php 
use Aws\Rds\RdsClient;
use AWS\Rds\Exception;

class rds extends service{
    private $rdsClient;
    
    const engineDriver = [
        'mysql' => 'mysql',
        'aurora-mysql' => 'mysql_aurora',
        'postgres' => 'postgres',
        'aurora-postgresql' => 'postgres_aurora'
    ];
    
    function __construct($region){
        parent::__construct($region);
    
        $this->__AWS_OPTIONS['version'] = CONFIG::AWS_SDK['RDSCLIENT_VERS'];
        $this->rdsClient = new RdsClient($this->__AWS_OPTIONS);
        $this->__loadDrivers();
    }
    
    ## Might need to loops
    function getResources(){
        $results = $this->rdsClient->describeDBInstances(
            // ['DBInstanceIdentifier' => 'mysql-bad']
           # ['DBInstanceIdentifier' => 'mysql-5']
        );
        
        $arr = $results->get('DBInstances');
        while($results->get('Maker') !== null){
            $results = $this->ec2Client->describeDBInstances([
                'Maker' => $results->get('Maker')
            ]);    
            $arr = array_merge($arr, $results->get('DBInstances'));
        }
        
        return $arr;
    }
    
    function advise(){
        $objs = [];
        $instances = $this->getResources();
        foreach($instances as $instance){
            __info('... (RDS) inspecting ' . $instance['DBInstanceIdentifier']);
            
            $engine = $instance['Engine'];
            $engine = self::engineDriver[$engine];
                        
            $driver = 'rds_' . $engine;
            if (class_exists($driver)){
                $obj = new $driver($instance, $this->rdsClient);
                $obj->run();
                
                $objs[$instance['Engine'] . '::' .$instance['DBInstanceIdentifier']] = $obj->getInfo();
                unset($obj);
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
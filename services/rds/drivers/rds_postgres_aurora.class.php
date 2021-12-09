<?php

class rds_postgres_aurora extends rds_postgres{
    protected $rdsClient;
    
    function __construct($db, $rdsClient){
        parent::__construct($db, $rdsClient);
        $this->loadParameterInfo();
    }
    
}
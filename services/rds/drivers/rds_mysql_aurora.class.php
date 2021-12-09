<?php

class rds_mysql_aurora extends rds_mysql{
    protected $rdsClient;
    
    function __construct($db, $rdsClient){
        parent::__construct($db, $rdsClient);
        $this->loadParameterInfo();
    }
}

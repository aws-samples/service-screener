<?php

class rds_postgres extends rds_common{
    protected $rdsClient;
    
    function __construct($db, $rdsClient){
        parent::__construct($db, $rdsClient);
        $this->loadParameterInfo();
    }
    
    protected function __checkPostgresParam(){
        $params = $this->dbParams;
        
        $idleTimeout = $params['idle_in_transaction_session_timeout'] ?? false;
        if($idleTimeout == false || $idleTimeout == 0)
            $this->results['PG__param_idleTransTimeout'] = [-1, $idleTimeout === false ? 'null' : $idleTimeout];
        
        $statementTimeout = $params['statement_timeout'] ?? false;
        if($statementTimeout == 0 || empty($statementTimeout))
            $this->results['PG__param_statementTimeout'] = [-1, $statementTimeout === false ? 'null' : $statementTimeout];
        
        $logTempFiles = $params['log_temp_files'] ?? false;
        if($logTempFiles <= 0 || empty($logTempFiles))
            $this->results['PG__param_logTempFiles'] = [-1, $logTempFiles === false ? 'null' : $logTempFiles];
        
        $tempFileLimit = $params['temp_file_limit'] ?? false;
        if($tempFileLimit <= 0 || empty($tempFileLimit))
            $this->results['PG__param_tempFileLimit'] = [-1, $tempFileLimit === false ? 'null' : $tempFileLimit];
            
        $alevel = $params['rds.force_autovacuum_logging_level'] ?? false;
        if($alevel == false || strtolower($alevel) != 'warning')
            $this->results['PG__param_rdsAutoVacuum'] = [-1, $alevel === false ? 'null' : $alevel];
            
        $adlevel = $params['log_autovacuum_min_duration'] ?? false;
        if($adlevel <= 0 || empty($adlevel))
            $this->results['PG__param_autoVacDuration'] = [-1, $adlevel === false ? 'null' : $adlevel];
            
        $trackIo = $params['track_io_timing'] ?? false;
        if($trackIo <= 0 || empty($trackIo))
            $this->results['PG__param_trackIoTime'] = [-1, $trackIo === false ? 'null' : $adlevel];
            
        $logStatement = $params['log_statement'] ?? false;
        if(!empty($logStatement) && in_array($logStatement, ['mod', 'all']))
            $this->results['PG__param_logStatement'] = [-1, $logStatement === false ? 'none' : $logStatement];
    }
}
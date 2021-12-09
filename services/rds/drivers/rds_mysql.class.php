<?php

class rds_mysql extends rds_common{
    protected $rdsClient;
    
    function __construct($db, $rdsClient){
        parent::__construct($db, $rdsClient);
        $this->loadParameterInfo();
    }
    
    protected function __checkEnableLogs(){
        $logsExports = $this->db['EnabledCloudwatchLogsExports'] ?? [];
        
        if( !in_array('error', $logsExports))
            $this->results['MYSQL__LogsGeneral'] = [-1, 'ALL'];
        else if( !in_array('error', $logsExports))
            $this->results['MYSQL__LogsErrorEnable'] = [-1, 'Disabled'];
    }
    
    
    
    ## Todo: 
    # https://aws.amazon.com/blogs/database/best-practices-for-configuring-parameters-for-amazon-rds-for-mysql-part-2-parameters-related-to-replication/
    # https://aws.amazon.com/blogs/database/best-practices-for-configuring-parameters-for-amazon-rds-for-mysql-part-1-parameters-related-to-performance/
    
    protected function __checkParamSyncBinLog(){
        $sync_binLog = $this->dbParams['sync_binlog'] ?? false;
        if($sync_binLog != 1)
            $this->results['MYSQL__param_syncBinLog'] = [-1, $sync_binLog === false ? 'null' : $sync_binLog];
    }
    
    protected function __checkParamInnoDbFlushTrxCommit(){
        $flushCommit = $this->dbParams['innodb_flush_log_at_trx_commit'] ?? false;
        if($flushCommit === 0 || $flushCommit == 2)
            $this->results['MYSQL__param_innodbFlushTrxCommit'] = [-1, $flushCommit === false ? 'null' : $flushCommit];
    }
    
    protected function __checkParamPerfSchema(){
        $ps = $this->dbParams['performance_schema'];
        if($ps == false){
            $this->results['MYSQL__PerfSchema'] = [-1, $ps];   
        }
    }
}
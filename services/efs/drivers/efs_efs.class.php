<?php

class efs_efs extends evaluator{
    public $results = [];
    
    
    function __construct($efs, $efsClient){
        $this->efs = $efs;
        $this->efsClient = $efsClient;
        $this->__configPrefix = 'efs::';

        $this->init();
    }
    
    function __checkEncrypted(){
        $this->results['EncryptedAtRest'] = [1, 'Enabled'];
        if($this->efs['Encrypted'] != '1'){
           $this->results['EncryptedAtRest'] = [-1, 'Disabled']; 
        }
    }
    
    function __checkLifecycleConfiguration(){
        $this->results['Lifecycle'] = [1, 'Enabled'];
        $efsId = $this->efs['FileSystemId'];
        
        $lifeCycle = $this->efsClient->describeLifecycleConfiguration([
            'FileSystemId' => $efsId   
        ]);
        
        if(sizeof($lifeCycle['LifecyclePolicies']) == 0){
            $this->results['EnabledLifecycle'] = [-1, 'Disabled'];
        }
    }
    
    function __checkBackupPolicy(){
        $this->results['AutomatedBackup'] = [1, 'Enabled'];
        $efsId = $this->efs['FileSystemId'];
        
        $backup = $this->efsClient->describeBackupPolicy([
            'FileSystemId' => $efsId   
        ]);
        
        if($backup['BackupPolicy']['Status'] == 'DISABLED'){
            $this->results['AutomatedBackup'] = [-1, 'Disabled'];
        }
    }
}
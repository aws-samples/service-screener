<?php 
use Aws\Iam\IamClient;
use AWS\Iam\Exception;

class iam extends service{
    private $iamClient;
    function __construct($region){
        parent::__construct($region);
    
        $this->__AWS_OPTIONS['version'] = CONFIG::AWS_SDK['IAMCLIENT_VERS'];
        
        $this->iamClient = new IamClient($this->__AWS_OPTIONS);
        $this->__loadDrivers();
    }
    
    function getGroups(){
        $arr = array();
        $results = $this->iamClient->listGroups();
        $arr = $results->get('Groups');
        
        while($results->get('Marker') !== null){
            $results = $this->iamClient->listGroups([
                'Marker' => $results->get('Marker')
            ]);    
            $arr = array_merge($arr, $results->get('Groups'));
        }
        
        return $arr;
    }
    
    function getResources(){
        $arr = array();
        try{
            $results = $this->iamClient->getCredentialReport();
        }catch(AWS\Iam\Exception\IamException $e){ 
            __pr($e->getAwsErrorCode());   
            if($e->getAwsErrorCode()=='ReportNotPresent'){
                $resp = $this->iamClient->generateCredentialReport();
                __info('Generating IAM Credential Report...');
                sleep(1);
                
                $results = $this->iamClient->getCredentialReport();    
            }
        }
        
        $rows = $results->get('Content');
        $row = explode("\n", $rows);
        $fields = explode(',', $row[0]);
        unset($row[0]);
        foreach($row as $temp){
            $arr[] = array_combine($fields, explode(',', $temp));
        }
        
        return $arr;
    }
    
    function roleFilterByName($rn){
        $keywords = [
            'AmazonSSMRole',
            'DO-NOT-DELETE',
            'Isengard',
            'AWSReservedSSO_',
            'GatedGarden',
            'PVRE-SSMOnboarding',
            'PVRE-Maintenance'
        ];
        
        foreach($keywords as $kw){
            if( strpos($rn, $kw) !== false )
                return false;
        }
        return true;
    }
    
    function getRoles(){
        # $arr = []; $arr[] = ['RoleName' => 'itadmin']; return $arr;
        
        $arr = array();
        $results = $this->iamClient->listRoles();
        foreach($results->get('Roles') as $v){
            if(
                ($v['Path'] != '/service-role/' && substr($v['Path'], 0, 18) != '/aws-service-role/')
                && ( $this->roleFilterByName($v['RoleName']))
            )
                $arr[] = $v;
        }
        
        while($results->get('Marker') !== null){
            $results = $this->iamClient->listRoles([
                'Marker' => $results->get('Marker')
            ]);    
            
            foreach($results->get('Roles') as $v){
                if(
                    ($v['Path'] != '/service-role/' && substr($v['Path'], 0, 18) != '/aws-service-role/')
                && ( $this->roleFilterByName($v['RoleName']))
                )
                    $arr[] = $v;
            }
        }
        
        return $arr;
    }
    
    function advise(){
        $roles = $this->getRoles();
        $objs = [];
        
        $driver = 'iam_setting';
        if(class_exists($driver)){
            __info('... (IAM::Account) inspecting');
            $obj = new $driver(null, $this->iamClient);
            $obj->run();
            
            $objs['Account::Config'] = $obj->getInfo();
        }
        
        $driver = 'iam_user';
        if (class_exists($driver)){
            $users = $this->getResources();
            foreach($users as $user){
                __info('... (IAM::User) inspecting ' . $user['user']);
                $obj = new $driver($user, $this->iamClient);
                $obj->run();
                
                $identifier = $user['user'] == '<root_account>' ? '<b>root_id</b>' : $user['user'];
                
                $objs['User::'.$identifier] = $obj->getInfo();
                unset($obj);
            }
        }
        
        $driver = 'iam_group';
        if(class_exists($driver)){
            $groups = $this->getGroups();
            foreach($groups as $group){
                __info('... (IAM::Group) inspecting ' . $group['GroupName']);
                $obj = new $driver($group, $this->iamClient);
                $obj->run();
                
                $objs['Group::'.$group['GroupName']] = $obj->getInfo();
                unset($obj);
            }
        }
        
        $driver = 'iam_role';
        if(class_exists($driver)){
            $roles = $this->getRoles();
            foreach($roles as $role){
                __info('... (IAM::Role) inspecting ' . $role['RoleName']);
                $obj = new $driver($role, $this->iamClient);
                $obj->run();
                
                $objs['Role::'.$role['RoleName']] = $obj->getInfo();
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
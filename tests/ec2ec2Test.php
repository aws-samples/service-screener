<?php
use PHPUnit\Framework\TestCase;
use Aws\Ec2\Ec2Client;

class ec2ec2Test extends TestCase{
    public function setup(){
        $region = 'ap-southeast-1';
        $this->driver = new ec2($region);
        $this->client = $this->driver->ec2Client;
        $this->engine = 'ec2_ec2';
        
    }
    
    public function EC2PositiveDataProvider(){
        $input = [
            'Instances' =>[
                [
                    'InstanceType' => 't3.micro',
                    'InstanceId' => 'i-05226d121fab6d233',
                    'Monitoring' => [
                        'State' => 'enabled'
                    ],
                    'SubnetId' => 'subnet-29f62161',
                    'VpcId' => 'vpc-c8a85aae'
                ]
            ]
        ];
        
        return[
            [$input]
        ];
    }
    
    /**
     * @dataProvider EC2PositiveDataProvider
     */
    public function testInstanceTypePositive($ec2){
        $driver = $this->engine;
        $obj = new $driver($ec2, $this->client);
        $obj->__checkInstanceTypeGeneration();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('EC2NewGen', $results);
        $this->assertEquals($results['EC2NewGen'][0],1);
    }
    
    public function InstanceTypeNegativeDataProvider(){
        $input = [
            'Instances' =>[
                [
                    'InstanceType' => 't2.micro',
                    'InstanceId' => 'i-05226d121fab6d233',
                    'Monitoring' => [
                        'State' => 'enabled'
                    ],
                    'SubnetId' => 'subnet-29f62161',
                    'VpcId' => 'vpc-c8a85aae'
                ]
            ]
        ];
        
        return[
            [$input]
        ];
    }
    
    /**
     * @dataProvider InstanceTypeNegativeDataProvider
     */
    public function testInstanceTypeNegative($ec2){
        $driver = $this->engine;
        $obj = new $driver($ec2, $this->client);
        $obj->__checkInstanceTypeGeneration();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('EC2NewGen', $results);
        $this->assertEquals($results['EC2NewGen'][0],-1);
    }
    
    /**
     * @dataProvider EC2PositiveDataProvider
     */
    public function testDetailedMonitoringPositive($ec2){
        $driver = $this->engine;
        $obj = new $driver($ec2, $this->client);
        $obj->__checkDetailedMonitoringEnable();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('EC2DetailedMonitor', $results);
        $this->assertEquals($results['EC2DetailedMonitor'][0],1);
    }
    
    public function DetailedMonitoringNegativeDataProvider(){
        $input = [
            'Instances' =>[
                [
                    'InstanceType' => 't3.micro',
                    'InstanceId' => 'i-05226d121fab6d233',
                    'Monitoring' => [
                        'State' => 'disabled'
                    ],
                    'SubnetId' => 'subnet-29f62161',
                    'VpcId' => 'vpc-c8a85aae'
                ]
            ]
        ];
        
        return[
            [$input]
        ];
    }
    
    /**
     * @dataProvider DetailedMonitoringNegativeDataProvider
     */
    public function testDetailedMonitoringNegative($ec2){
        $driver = $this->engine;
        $obj = new $driver($ec2, $this->client);
        $obj->__checkDetailedMonitoringEnable();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('EC2DetailedMonitor', $results);
        $this->assertEquals($results['EC2DetailedMonitor'][0],-1);
    }
    
    public function EC2IamProfilePositiveDataProvider(){
        $ec2Input = [
            'Instances' =>[
                [
                    'InstanceType' => 't3.micro',
                    'InstanceId' => 'i-05226d121fab6d233',
                    'Monitoring' => [
                        'State' => 'enabled'
                    ],
                    'SubnetId' => 'subnet-29f62161',
                    'VpcId' => 'vpc-c8a85aae'
                ]
            ]
        ];
        
        $iamInput = [
            'IamInstanceProfileAssociations' =>[
                [
                    'AssociationId' => 'iip-assoc-0db249b1f25fa24b8',
                    'IamInstanceProfile' => [
                        'Arn' => 'arn:aws:iam::123456789012:instance-profile/admin-role',
                        'Id' => 'AIPAJVQN4F5WVLGCJDRGM',
                    ],
                    'InstanceId' => 'i-09eb09efa73ec1dee',
                    'State' => 'associated',
                ],
            ]
        ];
        
        return[
            [$ec2Input, $iamInput]
        ];
    }
    
    /**
     * @dataProvider EC2IamProfilePositiveDataProvider
     */
    public function testIamProfilePositive($ec2, $iamProfile){
        $driver = $this->engine;
        $obj = new $driver($ec2, $this->client);
        $results = $obj->verifyIamProfileAssociate($iamProfile);
        unset($obj);
        
        $this->assertTrue($results);
    }
    
    public function EC2IamProfileNegativeDataProvider(){
        $ec2Input = [
            'Instances' =>[
                [
                    'InstanceType' => 't3.micro',
                    'InstanceId' => 'i-05226d121fab6d233',
                    'Monitoring' => [
                        'State' => 'enabled'
                    ],
                    'SubnetId' => 'subnet-29f62161',
                    'VpcId' => 'vpc-c8a85aae'
                ]
            ]
        ];
        
        $iamInput = [
            'IamInstanceProfileAssociations' =>[]
        ];
        
        return[
            [$ec2Input, $iamInput]
        ];
    }
    
    /**
     * @dataProvider EC2IamProfileNegativeDataProvider
     */
    public function testIamProfileNegative($ec2, $iamProfile){
        $driver = $this->engine;
        $obj = new $driver($ec2, $this->client);
        $results = $obj->verifyIamProfileAssociate($iamProfile);
        unset($obj);
        
        $this->assertFalse($results);
    }
}
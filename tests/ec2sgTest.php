<?php
use PHPUnit\Framework\TestCase;
use Aws\Ec2\Ec2Client;

class ec2sgTest extends TestCase{
    
    public function setup(){
        ## Double Check with KT
        $region = 'ap-southeast-1';
        $this->driver = new ec2($region);
        $this->client = $this->driver->ec2Client;
        $this->engine = 'ec2_sg';
        
    }
    
    public function positiveDataProvider(){
        $input = [
            'GroupName' => 'PositiveGroup',
            'IpPermissions' => [
                [
                    'FromPort' => 27017,
                    'IpProtocol' => 'udp',
                    'IpRanges' => [
                        ['CidrIp' => '172.168.0.0/16'],
                        ['CidrIp' => '192.168.0.0/16']
                    ],
                    'ToPort' => 27017
                ],
                [
                    'FromPort' => 22,
                    'IpProtocol' => 'tcp',
                    'IpRanges' => [
                        ['CidrIp' => '172.168.0.0/16']
                    ],
                    'ToPort' => 22
                ]
            ]
        ];
        
        return[
            [$input]
        ];
    }
    
    public function defaultSGFailDataProvider(){
        $input = [
            'GroupName' => 'default',
            'IpPermissions' => [
                [
                    'FromPort' => 27017,
                    'IpProtocol' => 'udp',
                    'IpRanges' => [
                        ['CidrIp' => '172.168.0.0/16'],
                        ['CidrIp' => '192.168.0.0/16']
                    ],
                    'ToPort' => 27017
                ],
                [
                    'FromPort' => 22,
                    'IpProtocol' => 'tcp',
                    'IpRanges' => [
                        ['CidrIp' => '172.168.0.0/16']
                    ],
                    'ToPort' => 22
                ]
            ]
        ];
        
        return[
            [$input]
        ];
    }
    
    /**
     * @dataProvider positiveDataProvider
     */
    public function testDefaultSGInUsedPositive($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkDefaultSGInUsed();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGDefaultInUsed', $results);
        $this->assertEquals($results['SGDefaultInUsed'][0],1);
    }
    
    /**
     * @dataProvider defaultSGFailDataProvider
     */
    public function testDefaultSGInUsedNegative($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkDefaultSGInUsed();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGDefaultInUsed', $results);
        $this->assertEquals($results['SGDefaultInUsed'][0],-1);
    }
    
    
    public function DNSFailDataProvider(){
        $input = [
            'GroupName' => 'fail group',
            'IpPermissions' => [
                [
                    'FromPort' => 53,
                    'IpProtocol' => 'tcp',
                    'IpRanges' => [
                        [
                            'CidrIp' => '0.0.0.0/0'
                        ]
                    ],
                    'ToPort' => 53
                ]
            ]
        ];
        
        return [
            [$input]    
        ];
    }
    
    /**
     * @dataProvider positiveDataProvider
     */
    public function testDNSOpenToAllPositive($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkDNSOpenToAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGDNSOpenToAll', $results);
        $this->assertEquals($results['SGDNSOpenToAll'][0],1);
    }
    
    /**
     * @dataProvider DNSFailDataProvider
     */
    public function testDNSOpenToAllNegative($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkDNSOpenToAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGDNSOpenToAll', $results);
        $this->assertEquals($results['SGDNSOpenToAll'][0],-1);
    }
    
    public function MongoDBFailDataProvider(){
        $input1 = [
            'GroupName' => 'fail group',
            'IpPermissions' => [
                [
                    'FromPort' => 27017,
                    'IpProtocol' => 'tcp',
                    'IpRanges' => [
                        [
                            'CidrIp' => '0.0.0.0/0'
                        ]
                    ],
                    'ToPort' => 27017
                ]
            ]
        ];
        
        $input2 = [
            'GroupName' => 'fail group',
            'IpPermissions' => [
                [
                    'FromPort' => 27018,
                    'IpProtocol' => 'tcp',
                    'IpRanges' => [
                        [
                            'CidrIp' => '0.0.0.0/0'
                        ]
                    ],
                    'ToPort' => 27018
                ]
            ]
        ];
        
        $input3 = [
            'GroupName' => 'fail group',
            'IpPermissions' => [
                [
                    'FromPort' => 27019,
                    'IpProtocol' => 'tcp',
                    'IpRanges' => [
                        [
                            'CidrIp' => '0.0.0.0/0'
                        ]
                    ],
                    'ToPort' => 27019
                ]
            ]
        ];
        
        $input4 = [
            'GroupName' => 'fail group',
            'IpPermissions' => [
                [
                    'FromPort' => 27017,
                    'IpProtocol' => 'tcp',
                    'IpRanges' => [
                        [
                            'CidrIp' => '0.0.0.0/0'
                        ]
                    ],
                    'ToPort' => 27019
                ]
            ]
        ];
        
        return [
            [
                $input1,
                $input2,
                $input3,
                $input4
            ]    
        ];
    }
    
    
    /**
     * @dataProvider positiveDataProvider
     */
    public function testMongoDBOpenToAllPositive($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkMongoDBOpenToAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGMongoDBOpenToAll', $results);
        $this->assertEquals($results['SGMongoDBOpenToAll'][0],1);
    }
    
    /**
     * @dataProvider MongoDBFailDataProvider
     */
    public function testMongoDBOpenToAllNegative($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkMongoDBOpenToAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGMongoDBOpenToAll', $results);
        $this->assertEquals($results['SGMongoDBOpenToAll'][0],-1);
    }
    
    public function MSSQLFailDataProvider(){
        $input = [
            'GroupName' => 'fail group',
            'IpPermissions' => [
                [
                    'FromPort' => 1433,
                    'IpProtocol' => 'tcp',
                    'IpRanges' => [
                        [
                            'CidrIp' => '0.0.0.0/0'
                        ]
                    ],
                    'ToPort' => 1433
                ]
            ]
        ];
        
        return [
            [$input]    
        ];
    }
    
    /**
     * @dataProvider positiveDataProvider
     */
    public function testMSSQLOpenToAllPositive($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkMSSQLOpenToAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGMSSQLOpenToAll', $results);
        $this->assertEquals($results['SGMSSQLOpenToAll'][0],1);
    }
    
    /**
     * @dataProvider MSSQLFailDataProvider
     */
    public function testMSSQLOpenToAllNegative($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkMSSQLOpenToAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGMSSQLOpenToAll', $results);
        $this->assertEquals($results['SGMSSQLOpenToAll'][0],-1);
    }
    
    public function MySQLFailDataProvider(){
        $input = [
            'GroupName' => 'fail group',
            'IpPermissions' => [
                [
                    'FromPort' => 3306,
                    'IpProtocol' => 'tcp',
                    'IpRanges' => [
                        [
                            'CidrIp' => '0.0.0.0/0'
                        ]
                    ],
                    'ToPort' => 3306
                ]
            ]
        ];
        
        return [
            [$input]    
        ];
    }
    
    /**
     * @dataProvider positiveDataProvider
     */
    public function testMySQLOpenToAllPositive($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkMySQLOpenToAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGMySQLOpenToAll', $results);
        $this->assertEquals($results['SGMySQLOpenToAll'][0],1);
    }
    
    /**
     * @dataProvider MySQLFailDataProvider
     */
    public function testMySQLOpenToAllNegative($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkMySQLOpenToAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGMySQLOpenToAll', $results);
        $this->assertEquals($results['SGMySQLOpenToAll'][0],-1);
    }
    
    public function NFSFailDataProvider(){
        $input = [
            'GroupName' => 'fail group',
            'IpPermissions' => [
                [
                    'FromPort' => 2049,
                    'IpProtocol' => 'tcp',
                    'IpRanges' => [
                        [
                            'CidrIp' => '0.0.0.0/0'
                        ]
                    ],
                    'ToPort' => 2049
                ]
            ]
        ];
        
        return [
            [$input]    
        ];
    }
    
    /**
     * @dataProvider positiveDataProvider
     */
    public function testNFSOpenToAllPositive($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkNFSOpenToAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGNFSBOpenToAll', $results);
        $this->assertEquals($results['SGNFSBOpenToAll'][0],1);
    }
    
    /**
     * @dataProvider NFSFailDataProvider
     */
    public function testNFSOpenToAllNegative($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkNFSOpenToAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGNFSBOpenToAll', $results);
        $this->assertEquals($results['SGNFSBOpenToAll'][0],-1);
    }
    
    public function OracleFailDataProvider(){
        $input = [
            'GroupName' => 'fail group',
            'IpPermissions' => [
                [
                    'FromPort' => 1521,
                    'IpProtocol' => 'tcp',
                    'IpRanges' => [
                        [
                            'CidrIp' => '0.0.0.0/0'
                        ]
                    ],
                    'ToPort' => 1521
                ]
            ]
        ];
        
        return [
            [$input]    
        ];
    }
    
    /**
     * @dataProvider positiveDataProvider
     */
    public function testOracleOpenToAllPositive($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkOracleDBOpenToAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGOracleOpenToAll', $results);
        $this->assertEquals($results['SGOracleOpenToAll'][0],1);
    }
    
    /**
     * @dataProvider OracleFailDataProvider
     */
    public function testOracleOpenToAllNegative($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkOracleDBOpenToAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGOracleOpenToAll', $results);
        $this->assertEquals($results['SGOracleOpenToAll'][0],-1);
    }
    
    public function PostgreSQLFailDataProvider(){
        $input = [
            'GroupName' => 'fail group',
            'IpPermissions' => [
                [
                    'FromPort' => 5432,
                    'IpProtocol' => 'tcp',
                    'IpRanges' => [
                        [
                            'CidrIp' => '0.0.0.0/0'
                        ]
                    ],
                    'ToPort' => 5432
                ]
            ]
        ];
        
        return [
            [$input]    
        ];
    }
    
    /**
     * @dataProvider positiveDataProvider
     */
    public function testPostgreSQLOpenToAllPositive($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkPostgreSQLOpenToAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGPostgreSQLOpenToAll', $results);
        $this->assertEquals($results['SGPostgreSQLOpenToAll'][0],1);
    }
    
    /**
     * @dataProvider PostgreSQLFailDataProvider
     */
    public function testPostgreSQLOpenToAllNegative($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkPostgreSQLOpenToAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGPostgreSQLOpenToAll', $results);
        $this->assertEquals($results['SGPostgreSQLOpenToAll'][0],-1);
    }
    
    public function RDPFailDataProvider(){
        $input = [
            'GroupName' => 'fail group',
            'IpPermissions' => [
                [
                    'FromPort' => 3389,
                    'IpProtocol' => 'tcp',
                    'IpRanges' => [
                        [
                            'CidrIp' => '0.0.0.0/0'
                        ]
                    ],
                    'ToPort' => 3389
                ]
            ]
        ];
        
        return [
            [$input]    
        ];
    }
    
    /**
     * @dataProvider positiveDataProvider
     */
    public function testRDPOpenToAllPositive($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkRDPOpenToAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGRDPBOpenToAll', $results);
        $this->assertEquals($results['SGRDPBOpenToAll'][0],1);
    }
    
    /**
     * @dataProvider RDPFailDataProvider
     */
    public function testRDPOpenToAllNegative($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkRDPOpenToAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGRDPBOpenToAll', $results);
        $this->assertEquals($results['SGRDPBOpenToAll'][0],-1);
    }
    
    public function SMTPFailDataProvider(){
        $input = [
            'GroupName' => 'fail group',
            'IpPermissions' => [
                [
                    'FromPort' => 25,
                    'IpProtocol' => 'tcp',
                    'IpRanges' => [
                        [
                            'CidrIp' => '0.0.0.0/0'
                        ]
                    ],
                    'ToPort' => 25
                ]
            ]
        ];
        
        return [
            [$input]    
        ];
    }
    
    /**
     * @dataProvider positiveDataProvider
     */
    public function testSMTPOpenToAllPositive($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkSMTPOpenToAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGSMTPOpenToAll', $results);
        $this->assertEquals($results['SGSMTPOpenToAll'][0],1);
    }
    
    /**
     * @dataProvider SMTPFailDataProvider
     */
    public function testSMTPOpenToAllNegative($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkSMTPOpenToAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGSMTPOpenToAll', $results);
        $this->assertEquals($results['SGSMTPOpenToAll'][0],-1);
    }
    
    public function SSHFailDataProvider(){
        $input = [
            'GroupName' => 'fail group',
            'IpPermissions' => [
                [
                    'FromPort' => 22,
                    'IpProtocol' => 'tcp',
                    'IpRanges' => [
                        [
                            'CidrIp' => '0.0.0.0/0'
                        ]
                    ],
                    'ToPort' => 22
                ]
            ]
        ];
        
        return [
            [$input]    
        ];
    }
    
    /**
     * @dataProvider positiveDataProvider
     */
    public function testSSHOpenToAllPositive($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkSSHOpenToAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGSSHOpenToAll', $results);
        $this->assertEquals($results['SGSSHOpenToAll'][0],1);
    }
    
    /**
     * @dataProvider SSHFailDataProvider
     */
    public function testSSHOpenToAllNegative($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkSSHOpenToAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGSSHOpenToAll', $results);
        $this->assertEquals($results['SGSSHOpenToAll'][0],-1);
    }
    
    public function TCPFailDataProvider(){
        $input = [
            'GroupName' => 'fail group',
            'IpPermissions' => [
                [
                    'FromPort' => 0,
                    'IpProtocol' => 'tcp',
                    'IpRanges' => [
                        [
                            'CidrIp' => '172.168.0.0/16'
                        ]
                    ],
                    'ToPort' => 65535
                ]
            ]
        ];
        
        return [
            [$input]    
        ];
    }
    
    /**
     * @dataProvider positiveDataProvider
     */
    public function testTCPAllOpenPositive($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkTCPAllOpen();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGTCPAllOpen', $results);
        $this->assertEquals($results['SGTCPAllOpen'][0],1);
    }
    
    /**
     * @dataProvider TCPFailDataProvider
     */
    public function testTCPAllOpenNegative($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkTCPAllOpen();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGTCPAllOpen', $results);
        $this->assertEquals($results['SGTCPAllOpen'][0],-1);
    }
    
    public function UDPFailDataProvider(){
        $input = [
            'GroupName' => 'fail group',
            'IpPermissions' => [
                [
                    'FromPort' => 0,
                    'IpProtocol' => 'udp',
                    'IpRanges' => [
                        [
                            'CidrIp' => '172.168.0.0/16'
                        ]
                    ],
                    'ToPort' => 65535
                ]
            ]
        ];
        
        return [
            [$input]    
        ];
    }
    
    /**
     * @dataProvider positiveDataProvider
     */
    public function testUDPAllOpenPositive($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkUDPOpenAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGUDPAllOpen', $results);
        $this->assertEquals($results['SGUDPAllOpen'][0],1);
    }
    
    /**
     * @dataProvider UDPFailDataProvider
     */
    public function testUDPAllOpenNegative($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkUDPOpenAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGUDPAllOpen', $results);
        $this->assertEquals($results['SGUDPAllOpen'][0],-1);
    }
    
    public function AllOpenFailDataProvider(){
        $input = [
            'GroupName' => 'fail group',
            'IpPermissions' => [
                [
                    'IpProtocol' => -1,
                    'IpRanges' => [
                        [
                            'CidrIp' => '172.168.0.0/16'
                        ]
                    ]
                ]
            ]
        ];
        
        return [
            [$input]    
        ];
    }
    
    /**
     * @dataProvider positiveDataProvider
     */
    public function testAllPortOpenPositive($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkAllOpen();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGAllOpen', $results);
        $this->assertEquals($results['SGAllOpen'][0],1);
    }
    
    /**
     * @dataProvider AllOpenFailDataProvider
     */
    public function testAllPortOpenNegative($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkAllOpen();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGAllOpen', $results);
        $this->assertEquals($results['SGAllOpen'][0],-1);
    }
    
     public function AllOpenAllFailPositiveProvider(){
        $input1 = [
            'GroupName' => 'PositiveGroup',
            'IpPermissions' => [
                [
                    'FromPort' => 27017,
                    'IpProtocol' => 'udp',
                    'IpRanges' => [
                        ['CidrIp' => '172.168.0.0/16'],
                        ['CidrIp' => '192.168.0.0/16']
                    ],
                    'ToPort' => 27017
                ],
                [
                    'FromPort' => 22,
                    'IpProtocol' => 'tcp',
                    'IpRanges' => [
                        ['CidrIp' => '172.168.0.0/16']
                    ],
                    'ToPort' => 22
                ]
            ]
        ];
         
        $input2 = [
            'GroupName' => 'Positive group',
            'IpPermissions' => [
                [
                    'IpProtocol' => -1,
                    'IpRanges' => [
                        [
                            'CidrIp' => '172.168.0.0/16'
                        ]
                    ]
                ]
            ]
        ];
        
        return [
            [$input1, $input2]    
        ];
    }
    
    public function AllOpenAllFailNegativeProvider(){
        $input = [
            'GroupName' => 'Positive group',
            'IpPermissions' => [
                [
                    'IpProtocol' => -1,
                    'IpRanges' => [
                        [
                            'CidrIp' => '0.0.0.0/0'
                        ]
                    ]
                ]
            ]
        ];
        
        return [
            [$input]    
        ];
    }
    
    /**
     * @dataProvider AllOpenAllFailPositiveProvider
     */
    public function testAllPortOpenToAllPositive($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkAllOpenAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGAllOpenToAll', $results);
        $this->assertEquals($results['SGAllOpenToAll'][0],1);
    }
    
    /**
     * @dataProvider AllOpenAllFailNegativeProvider
     */
    public function testAllPortOpenToAllOpenNegative($sg){
        $driver = $this->engine;
        $obj = new $driver($sg, $this->client);
        $obj->__checkAllOpenAll();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('SGAllOpenToAll', $results);
        $this->assertEquals($results['SGAllOpenToAll'][0],-1);
    }
}
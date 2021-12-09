<?php
use PHPUnit\Framework\TestCase;
use Aws\Ec2\Ec2Client;

class ec2ebsTest extends TestCase{
    public function setup(){
        $region = 'ap-southeast-1';
        $this->driver = new ec2($region);
        $this->client = $this->driver->ec2Client;
        $this->engine = 'ec2_ebs';
        
    }
    
    public function positiveDataProvider(){
        $input = [
            'Encrypted' => '1',
            'VolumeType' => 'gp3',
            'State' => 'in-use',
            'SnapshotId' => 'snap-0621a2887001ab129'
            
        ];
        
        return[
            [$input]
        ];
    }
    
    public function EncryptedBlockFailDataProvider(){
        $input = [
            'Encrypted' => '',
            'VolumeType' => 'gp3',
            'State' => 'in-use',
            'SnapshotId' => 'snap-0621a2887001ab129'
            
        ];
        
        return[
            [$input]
        ];
    }
    
    /**
     * @dataProvider positiveDataProvider
     */
    public function testEncryptedBlockPositive($block){
        $driver = $this->engine;
        $obj = new $driver($block, $this->client);
        $obj->__checkEncryptedBlock();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('EBSEncrypted', $results);
        $this->assertEquals($results['EBSEncrypted'][0],1);
    }
    
    /**
     * @dataProvider EncryptedBlockFailDataProvider
     */
    public function testEncryptedBlockNegative($block){
        $driver = $this->engine;
        $obj = new $driver($block, $this->client);
        $obj->__checkEncryptedBlock();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('EBSEncrypted', $results);
        $this->assertEquals($results['EBSEncrypted'][0],-1);
    }
    
    public function NewGenBlockFailDataProvider(){
        $input1 = [
            'Encrypted' => '1',
            'VolumeType' => 'gp2',
            'State' => 'in-use',
            'SnapshotId' => 'snap-0621a2887001ab129'
            
        ];
        
        $input2 = [
            'Encrypted' => '1',
            'VolumeType' => 'io1',
            'State' => 'in-use',
            'SnapshotId' => 'snap-0621a2887001ab129'
            
        ];
        
        return[
            [$input1, $input2]
        ];
    }
    
    /**
     * @dataProvider positiveDataProvider
     */
    public function testNewGenBlockPositive($block){
        $driver = $this->engine;
        $obj = new $driver($block, $this->client);
        $obj->__checkNewGenBlock();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('EBSNewGen', $results);
        $this->assertEquals($results['EBSNewGen'][0],1);
    }
    
    /**
     * @dataProvider NewGenBlockFailDataProvider
     */
    public function testNewGenBlockNegative($block){
        $driver = $this->engine;
        $obj = new $driver($block, $this->client);
        $obj->__checkNewGenBlock();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('EBSNewGen', $results);
        $this->assertEquals($results['EBSNewGen'][0],-1);
    }
    
    public function InUsedBlockFailDataProvider(){
        $input = [
            'Encrypted' => '1',
            'VolumeType' => 'gp3',
            'State' => 'available',
            'SnapshotId' => 'snap-0621a2887001ab129'
            
        ];
        
        return[
            [$input]
        ];
    }
    
    /**
     * @dataProvider positiveDataProvider
     */
    public function testInUsedBlockPositive($block){
        $driver = $this->engine;
        $obj = new $driver($block, $this->client);
        $obj->__checkInUsedBlock();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('EBSInUsed', $results);
        $this->assertEquals($results['EBSInUsed'][0],1);
    }
    
    /**
     * @dataProvider InUsedBlockFailDataProvider
     */
    public function testInUsedBlockNegative($block){
        $driver = $this->engine;
        $obj = new $driver($block, $this->client);
        $obj->__checkInUsedBlock();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('EBSInUsed', $results);
        $this->assertEquals($results['EBSInUsed'][0],-1);
    }
    
    public function SnapshotBlockFailDataProvider(){
        $input = [
            'Encrypted' => '1',
            'VolumeType' => 'gp3',
            'State' => 'in-use',
            'SnapshotId' => ''
            
        ];
        
        return[
            [$input]
        ];
    }
    
    /**
     * @dataProvider positiveDataProvider
     */
    public function testSnapshotBlockPositive($block){
        $driver = $this->engine;
        $obj = new $driver($block, $this->client);
        $obj->__checkSnapshotBlock();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('EBSSnapshot', $results);
        $this->assertEquals($results['EBSSnapshot'][0],1);
    }
    
    /**
     * @dataProvider SnapshotBlockFailDataProvider
     */
    public function testSnapshotBlockNegative($block){
        $driver = $this->engine;
        $obj = new $driver($block, $this->client);
        $obj->__checkSnapshotBlock();
        $results = $obj->getInfo();
        unset($obj);
        
        $this->assertArrayHasKey('EBSSnapshot', $results);
        $this->assertEquals($results['EBSSnapshot'][0],-1);
    }
}
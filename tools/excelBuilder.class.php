<?php 

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelBuilder{
    private $obj;
    private $sheetIndex = 1;
    private $recommendations = [];
    
    const XLSX_FILENAME = 'workItem.xlsx';
    const XLSX_CREATOR = "Service Screener - AWS Malaysia";
    const XLSX_TITLE = "Service Screener WorkList";
    const SHEET_HEADER = [
        'Region',
        'Check',
        'Type',
        'ResourceID',
        'Severity',
        'Status'
    ];
    function __construct($accountId, $ssParams){
        $this->accountId = $accountId;
        $this->ssParams = $ssParams;
        
        $this->obj = new Spreadsheet();
        $this->__setExcelInfo();
        $this->__generateInfo();
        
    }
    
    function generateWorkSheet($service, $raw){
        $service = strtoupper($service);
        
        $this->obj->createSheet();
        $sh = $this->obj->setActiveSheetIndex($this->sheetIndex);
        $sh->setTitle($service);
        
        $data = $this->__formatReporterDataToArray($service, $raw);
        
        $sh->fromArray(self::SHEET_HEADER, NULL, 'A1');
        $sh->fromArray($data, NULL, 'A2');
        
        $sh->getStyle('1:1')->getFont()->setBold(true);
        
        $vObj = $sh->getCell('F2')->getDataValidation();
        $this->__validation_status($vObj);
        $vObj->setSqref('F2:G1048576');
        
        $this->__setAutoSize($sh);
        
        $this->sheetIndex++;
    }
    
    function generateRecommendationSheet(){
        $this->obj->createSheet();
        $sh = $this->obj->setActiveSheetIndex($this->sheetIndex);
        $sh->setTitle('Appendix');
        
        $header = [
            'Service',
            'Check',
            'Short Description',
            'Recommendation'
        ];
        
        $data = [];
        foreach($this->recommendations as $service => $det){
            foreach($det as $check => $info){
                $data[] = [
                    $service,
                    $check,
                    $info[0],
                    $this->__formatHyperlink($info[1])
                ];
            }
        }
        
        $sh->fromArray($header, NULL, 'A1');
        $sh->fromArray($data, NULL, 'A2');
        $sh->getStyle('1:1')->getFont()->setBold(true);
        $sh->getStyle('D2:D' . $sh->getHighestRow())->getAlignment()->setWrapText(true);
        $this->__setAutoSize($sh);
        
        $this->sheetIndex++;
    }
    
    function __generateInfo(){
        $sh = $this->obj->getActiveSheet();
        $sh->setTitle("Info");
        
        $info = [
            ['AccountId', "'".$this->accountId],
            ['Generated on', date('Y/m/d H:i:s')],
            ['Parameters', $this->ssParams]
        ];
        
        $sh->fromArray($info, NULL, 'A1');
        $this->__setAutoSize($sh);
    }
    
    function __setExcelInfo(){
        $this->obj->getProperties()
            ->setCreator(self::XLSX_CREATOR)
            ->setLastModifiedBy(self::XLSX_CREATOR)
            ->setSubject(self::XLSX_TITLE)
            ->setDescription( $this->__getXLSXDescription($this->ssParams) )
            ;
    }
    
    ##Sheet
    function __setAutoSize(&$sh){
        foreach($sh->getColumnIterator() as $col){
            $sh->getColumnDimension($col->getColumnIndex())->setAutoSize(true);
        }
    }
    
    function __getXLSXDescription($ssParams){
        return date('Y/m/d H:i:s') . " | " . $ssParams;
    }
    
    function __validation_status(&$vObj){
        $vObj->setType(DataValidation::TYPE_LIST)
            ->setFormula1('"New, Suppressed, Resolved"')
            ->setAllowBlank(false)
            ->setShowDropDown(true)
            ->setShowInputMessage(true);
    }
    
    ## <TODO>
    ## <ACCID><SS><VERS><YYMMDD_His>.xlsx
    function __getFileName($folderPath){
        return $folderPath . self::XLSX_FILENAME;
    }
    
    function __save($folderPath=''){
        if(!empty($this->recommendations)){
            $this->generateRecommendationSheet();
        }
        
        $writer = new Xlsx($this->obj);
        $writer->save( $this->__getFileName($folderPath));
    }
    
    function __formatReporterDataToArray($service, $cardSummary){
        $arr = [];
        foreach($cardSummary as $check => $detail){
            $this->recommendations[$service][$check] = [$detail['shortDesc'], $detail['__links']];
            foreach($detail['__affectedResources'] as $region => $resources){
                foreach($resources as $resource){
                    $arr[] = [
                        $region,
                        $check,
                        $this->__getPillarName($detail['__categoryMain']),
                        $resource,
                        $this->__getCriticallyName($detail['criticality']),
                        'New'
                    ];
                }
            }
        }
        
        return $arr;
    }
    
    function __getPillarName($category){
        $mapped = [
            'T' => 'Text',
            'O' => 'Operation Excellence',
            'P' => 'Performance Efficiency',
            'S' => 'Security',
            'R' => 'Reliability',
            'C' => 'Cost Optimization'
        ];
        
        return $mapped[$category];
    }
    
    function __getCriticallyName($criticality){
        $mapped = [
            'H' => 'High',
            'M' => 'Medium',
            'L' => 'Low',
            'I' => 'Informational'
        ];
        
        return $mapped[$criticality];
    }
    
    function __formatHyperlink($arr){
        $recomm = [];
        foreach($arr as $p){
            $o = strpos($p, "href='");
            $e = strpos($p, "'>");
            $r = substr($p, $o+6, $e-$o-6);
            $w = substr($p, $e+2, -4);
            $recomm[] = "$w, $r";
        }
        return implode("\n", $recomm);
    }
}

<?php

class dashboardPageBuilder extends pageBuilder{
    function init(){
        $this->isHome = true;
        $this->template='dashboard';
    }
    
    function buildContentSummary_dashboard(){
        global $DASHBOARD;
        $output = $items = [];
        $dataSets = [
            'S' => 0,
            'R' => 0,
            'C' => 0,
            'P' => 0,
            'O' => 0
        ];
        
        $hriSets = [
            'H' => 0,
            'M' => 0,
            'L' => 0,
            'I' => 0
        ];
        
        $total = 0;
        if(empty($DASHBOARD['CRITICALITY'])){
            __warn("0 recommendations detected, expecting empty report");
            return;
        }
        
        foreach($DASHBOARD['CRITICALITY'] as $region => $details){
            foreach($details as $cat => $cnt){
                $hriSets[$cat] += $cnt;   
                $total += $cnt;
            }
        }
        
        foreach($hriSets as $cat => $count){
            $items[] = $this->getHRIInfo($cat, $count, $total);   
        }
        
        foreach($DASHBOARD['CATEGORY'] as $region => $details){
            foreach($details as $cat => $cnt)
                $dataSets[$cat] += $cnt;
        }
        
        
        $html = "<dl class='row'>" . implode("\n", $items) . "</dl>";
        $card = $this->generateCard($id=$this->getHtmlId('criticalityCount'), $html, $cardClass='danger', $title='No. Criticality', '', $collapse=false);
        $securityBox = $this->generateSecurityBigBox($dataSets['S']);
        
        $customHtml = <<<EOL
<div class="row">
    <div class="col-sm-8">
        $card
    </div>
    $securityBox
</div>
EOL;
        $output[] = $customHtml;
        
        
        foreach($dataSets as $cat => $total){
            if($cat == 'S')
                continue;
                
            $items[] = [$this->getDashboardCategoryTiles($cat, $total), ''];
        }
        
        $output[] = $this->generateRowWithCol($size=3, $items, "data-context='pillars'");
        return $output;
    }
    
    function buildContentDetail_dashboard(){
        ## Chart - Categorise by Services, Stacked by Region
        global $DASHBOARD;
        $items = [];
        $serviceLabels = $regionLabels = [];
        $donutL = $donutR = $dataSetsL = $dataSetsR = [];
        $regions = $this->regions;
        $services = $this->services;
        
        foreach($services as $service => $cnt){
            $serviceLabels[] = $service;   
            $dataSetsR[$service] = [];
            $donutR[$service] = 0;
        }
        
        foreach($regions as $region){
            $regionLabels[] = $region;
            $dataSetsL[$region] = [];
            $donutL[$region] = 0;
        }
        
        foreach($DASHBOARD['SERV'] as $serv => $attrs){
            foreach($regions as $region){
                $hri = $cnt = 0;
                if(isset($attrs[$region])){
                    $cnt = $attrs[$region]['Total'];
                    $hri = $attrs[$region]['H'];
                }
                
                $dataSetsL[$region][] = $cnt;
                $dataSetsR[$serv][] = $cnt;
                $donutL[$region] += $hri;
                $donutR[$serv] += $hri;
            }
        }
        
        $html = $this->generateDonutPieChart($donutL, 'hriByRegion', 'doughnut');
        $card = $this->generateCard($id=$this->getHtmlId('chartServRegion'), $html, $cardClass='warning', $title='High Risk - Group by Region', '', $collapse=true);
        $items[] = [$card, ''];
        
        $html = $this->generateDonutPieChart($donutR, 'hriByService', 'pie');
        $card = $this->generateCard($id=$this->getHtmlId('pieHriByService'), $html, $cardClass='warning', $title='High Risk - Group by Service', '', $collapse=true);
        $items[] = [$card, ''];
        
        $output[] = $this->generateRowWithCol($size=6, $items, "data-context='chartHRICount'");
        
        $items = [];
        $html = $this->generateBarChart($serviceLabels, $dataSetsL, 'csr');
        $card = $this->generateCard($id=$this->getHtmlId('chartServRegion'), $html, $cardClass='info', $title='Chart by Serv by Region', '', $collapse=true);
        $items[] = [$card, ''];
        
        
        $html = $this->generateBarChart($regionLabels, $dataSetsR, 'crs');
        $card = $this->generateCard($id=$this->getHtmlId('chartRegionServ'), $html, $cardClass='info', $title='Chart by Region by Serv', '', $collapse=true);
        $items[] = [$card, ''];
        
        $output[] = $this->generateRowWithCol($size=6, $items, "data-context='chartCount'");
        
        $output[] = "<h6>Report generated at <u>".date("Y-m-d H:i:s")."</u>, timezone setting: ".date_default_timezone_get()."</h6>";
        return $output;
    }
    
    function getDashboardCategoryTiles($key, $cnt){
        $colorArr = [
            'S' => ['danger', 'Security', 'cog'],
            'R' => ['fuchsia', 'Reliability', 'globe'],
            'C' => ['warning', 'Cost Optimisation', 'dollar-sign'],
            'P' => ['success', 'Performance Efficieny', 'seedling'],
            'O' => ['navy', 'Operation Excellence', 'wrench']
        ];
        
        list($colorClass, $title, $icon) = $colorArr[$key];
        
        $style = ($key == 'O') ? "style='color: #dfdfdf'" : "";
        
        $output = <<<EOL
<div class="small-box bg-$colorClass">
  <div class="inner">
    <h3>$cnt</h3>
    <p>$title</p>
  </div>
  <div class="icon">
    <i $style class="fas fa-$icon"></i>
  </div>
</div>
EOL;
        return $output;
    }
    
    function getHRIInfo($cat, $cnt, $total){
        $attrArr = [
            'H' => ['danger', 'High', 'ban'],
            'M' => ['warning', 'Medium', 'exclamation-triangle'],
            'L' => ['info', 'Low', 'eye'],
            'I' => ['primary', 'Informational', 'info-circle']
        ];
        
        list($colorClass, $title, $icon) = $attrArr[$cat];
        
        $percentile = round($cnt * 100 / $total);
        
        $output = <<<EOL
<dt class="col-sm-4"><i class="fas fa-$icon"></i> $title</dt>
<dd class="col-sm-8" style='text-align: right'>$cnt</dd>
<dt class="col-sm-12">
<div class="progress mb-3">
  <div class="progress-bar bg-$colorClass" role="progressbar" aria-valuenow="$percentile" aria-valuemin="0"
	   aria-valuemax="100" style="width: $percentile%">
	<span>($percentile%)</span>
  </div>
</div>
</dt>    
EOL;
        return $output;
    }
    
    function generateSecurityBigBox($cnt){
        $output = <<<EOL
<div class="col-sm-4">
	<div class="small-box bg-danger" style='height: 357px'>
	  <div class="inner">
		<h3>$cnt</h3>
		<p>Security</p>
	  </div>
	  <div class="icon">
		<i style='color: #dfdfdf' class="fas fa-skull-crossbones"></i>
	  </div>
	</div>
</div>
EOL;
        return $output;
    }
}
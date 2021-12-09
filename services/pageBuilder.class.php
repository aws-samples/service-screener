<?php
class pageBuilder{
    const serviceIcon = [
        'ec2' => 'server',
        'rds' => 'database',
        's3' => 'hdd',
        'iam' => 'users'
    ];
    
    protected $pageTemplate = [
        'header.precss' => 'header.precss.template.html',
        'header.postcss' => 'header.postcss.template.html',
        'sidebar.precustom' => 'sidebar.precustom.template.html',
        'sidebar.postcustom' => 'sidebar.postcustom.template.html',
        'breadcrumb' => 'breadcrumb.template.html',
        'footer.prejs' => 'footer.prejs.template.html',
        'footer.postjs' => 'footer.postjs.template.html',
    ];
    
    protected $isHome = false;
    
    private $js = [];
    private $jsLib = [];
    private $cssLib = [];
    
    function __construct($service, $reporter, $services, $regions){
        $this->service = $service;
        $this->services = $services;
        $this->regions = $regions;
        $this->reporter = $reporter;
        
        $this->idPrefix = $this->service.'-';
    }
    
    function getHtmlId($el=''){
        $el = $el ?? substr(uniqid(rand(10000,99999)), 0, 11);
        return $this->idPrefix . $el;
    }
    
    function buildPage(){
        $this->init();        
        
        $output = [];
        $output[] = $this->buildHeader();
        $output[] = $this->buildNav();
        $output[] = $this->buildBreadcrumb();
        $output[] = $this->buildContentSummary();
        $output[] = $this->buildContentDetail();
        $output[] = $this->buildFooter();
        
        $finalHTML = "";
        foreach($output as $arrayOfText){
            if(!empty($arrayOfText))
                $finalHTML .= implode("\n", $arrayOfText);
        }
        
        file_put_contents(HTML_DIR.'/'.$this->service.'.html', $finalHTML);
    }
    
    function init(){
        $this->template = 'default';   
    }
    
    function buildContentSummary(){
        $method = 'buildContentSummary_' . $this->template;
        if(method_exists($this, $method)){
            return $this->$method();
        }else{
            $cls = get_class($this);
            __info("[$cls] Template for ContentSummary not found: " . $method);
        }
    }
    
    function buildContentDetail(){
        $method = 'buildContentDetail_' . $this->template;
        if(method_exists($this, $method)){
            return $this->$method();
        }else{
            $cls = get_class($this);
            __info("[$cls] Template for ContentDetail not found: " . $method);
        }
    }
    
    function generateRowWithCol($size=12, $items, $rowHtmlAttr=''){
        $output = [];
        $output[] = "<div class='row' $rowHtmlAttr>";
        
        $__size = $size;
        foreach($items as $ind => $item){
            if(is_array($size)){
                $i = $ind % sizeof($size);
                $__size = $size[$i];
            }
            $output[] = $this->generateCol($__size, $item);
        }
        $output[] = "</div>";
        
        return implode("\n", $output);
    }
    
    function generateCol($size=12, $item){
        $output = [];
        if(empty($item)){
            $output[] = "</div><div class='row'>";   
        }else{
            list($html, $divAttr) = $item;
            
            $output[] = "<div class='col-md-$size' $divAttr>";
            $output[] = $html;
            $output[] = "</div>";
        }
        
        return implode("\n", $output);
    }
    
    function generateCard($id, $html, $cardClass='warning', $title='', $titleBadge='',$collapse=false, $noPadding=false){
        $output = [];
        
        $lteCardClass = empty($cardClass) ? '' : "card-$cardClass";
        $defaultCollapseClass = ($collapse === 9) ? "collapsed-card" : "";
        $defaultCollapseIcon = ($collapse === 9) ? "plus" : "minus";
        
        $output[] = "<div id='$id' class='card $lteCardClass $defaultCollapseClass'>";
        
        if(!empty($title)){
            $output[] = "<div class='card-header'><h3 class='card-title'>$title</h3>";
            
            if($collapse)
                $output[] = "<div class='card-tools'><button type='button' class='btn btn-tool' data-card-widget='collapse'><i class='fas fa-$defaultCollapseIcon'></i></button></div>";
                
            if(!empty($titleBadge))
                $output[] = $titleBadge;
                
            $output[] = "</div>";
        }
        
        $noPadClass = $noPadding ? 'p-0' : '';   
        
        $output[] = "<div class='card-body $noPadClass'>";
        $output[] = $html;
        $output[] = "</div>";
        $output[] = "</div>";
        return implode("\n", $output);
    }
    
    function generateCategoryBadge($category, $addtionalHtmlAttr){
        $validCategory = ['R', 'S', 'O', 'P', 'C'];
        $colorByCategory = ['info', 'danger', 'primary', 'success', 'warning'];
        $nameByCategory = ['Reliability', 'Security', 'Operation Excellence', 'Performance Efficiency', 'Cost Optimization'];
        if( !in_array($category, $validCategory)){
            $category = 'X';
            $color = 'info';
            $name = 'Suggestion';
        }else{
            $indexOf = array_search($category, $validCategory);
            $color = $colorByCategory[$indexOf];
            $name = $nameByCategory[$indexOf];
        }
        
        return "<span class='badge badge-{$color}', $addtionalHtmlAttr>$name</span>";
    }
    
    function generatePriorityPrefix($criticality, $addtionalHtmlAttr){
        $validCategory = ['I', 'L', 'M', 'H'];
        $colorByCategory = ['info', 'primary', 'warning', 'danger'];
        $iconByCategory = ['info-circle', 'eye', 'exclamation-triangle', 'ban'];
        
        $criticality = in_array($criticality, $validCategory) ? $criticality : $validCategory[0];
        
        $indexOf = array_search($criticality, $validCategory);
        $color = $colorByCategory[$indexOf];
        $icon = $iconByCategory[$indexOf];
        
        # return "<span class='badge badge-{$color} img-circle elevation-2' $addtionalHtmlAttr>$criticality</span>";
        return "<span class='badge badge-{$color}' $addtionalHtmlAttr><i class='icon fas fa-$icon'></i></span>";
    }
    
    function generateSummaryCardContent($summary){
        $output = [];
        
        
        $resources = $summary['__affectedResources'];
        $resHtml = [];
        foreach($resources as $region => $resource){
            $items = [];
            $resHtml[] = "<dd>$region: ";
            foreach($resource as $identifier){
                $items[] = "<a href='#{$this->service}-$identifier'>$identifier</a>";   
            }
            $resHtml[] = implode(" | ", $items);
            $resHtml[] = "</dd>";
        }
        
        
        $output[] = "<dl>
        <dt>Description</dt>
        <dd>".$summary['^description']."</dd>
        <dt>Resources</dt>".implode("", $resHtml);
        
        $hasTags = $this->generateSummaryCardTag($summary);
        if(strlen(trim($hasTags)) > 0){
            $output[] = "<dt>Label</dt><dd>$hasTags</dd>";
        }
        
        if(!empty($summary['__links'])){
            $output[] = "<dt>Recommendation</dt>";
            $output[] = "<dd>" . implode("</dd><dd>", $summary['__links']) . "</dd>";   
        }
        
        $output[] = "</dl>";
        
        return implode("\n", $output);
    }
    
    function generateDonutPieChart($datasets, $idPrefix = '', $type='doughnut'){
        $id = $idPrefix . $type . uniqid(get_class($this));
        $output = [];
        $output[] = "<div class='chart'>
            <canvas id='$id' style='min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;'></canvas>
        </div>";
        
        list($labels, $enriched) = $this->__enrichDonutPieData($datasets);
        
        $this->addJS(
        "var donutPieChartCanvas = $('#$id').get(0).getContext('2d')
        var donutPieData = {
            labels: ".json_encode($labels).",
            datasets: [".json_encode($enriched)."]
        }");
        
        $this->addJS(
        "var donutPieOptions     = {
		  maintainAspectRatio : false,
		  responsive : true,
		}
		new Chart(donutPieChartCanvas, {
		  type: '$type',
		  data: donutPieData,
		  options: donutPieOptions
		})");
		
		return implode("\n", $output);
    }
    
    function generateBarChart($labels, $datasets, $idPrefix = ''){
        $id = $idPrefix . 'bar' . uniqid(get_class($this));
        
        $output = [];
        $output[] = "<div class='chart'>
            <canvas id='$id' style='min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;'></canvas>
        </div>";
        
        $enriched = $this->__enrichChartData($datasets);
        
        $this->addJS("var areaChartData = {
            labels: ".json_encode($labels).",
            datasets: ".json_encode($enriched)."
        }");
        
        $this->addJS("var barChartData = $.extend(true, {}, areaChartData)
    var stackedBarChartCanvas = $('#$id').get(0).getContext('2d')
    var stackedBarChartData = $.extend(true, {}, barChartData)");
        
        $this->addJS(
    "var stackedBarChartOptions = {
      responsive              : true,
      maintainAspectRatio     : false,
      scales: {
        xAxes: [{
          stacked: true,
        }],
        yAxes: [{
          stacked: true
        }]
      },
     onClick: function(e, i){
		checkCtrl = $('#checkCtrl')
		var v = i[0]['_model']['label'];
		if(typeof v == 'undefined')
			return
		
		curVal = checkCtrl.val()
		idx = curVal.indexOf(v)
		if (idx == -1){
			curVal.push(v)
		}else{
			curVal.splice(idx, 1);
		}
		
		checkCtrl.val(curVal).trigger('change')
    }
}
        
        new Chart(stackedBarChartCanvas, {
            type: 'bar',
            data: stackedBarChartData,
            options: stackedBarChartOptions
        })");
        
        return implode("\n", $output);
    }
    
    function generateSummaryCardTag($summary){
        $text = '';
        
        $text .= $this->__generateSummaryCardTagHelper($summary['downtime'] ?? false, 'Have Downtime');
        $text .= ' ' . $this->__generateSummaryCardTagHelper($summary['needFullTest'] ?? false, 'Testing Required');
        $text .= ' ' . $this->__generateSummaryCardTagHelper($summary['slowness'] ?? false, 'Performance Impact');
        $text .= ' ' . $this->__generateSummaryCardTagHelper($summary['additionalCost'] ?? false, 'Cost Incurred');
        
        return $text;
    }
    
    function __generateSummaryCardTagHelper($flag, $text){
        if($flag == false)
            return;
        
        $str = $text;
        $color = 'warning';
        if($flag < 0){
            $str .= " (maybe)";
            $color = 'info';
        }
        
        return "<span class='badge badge-{$color}'>$str</span>";
    }
    
    function __enrichDonutPieData($datasets){
        $label = [];
        $arr = [
            'data' => [],
            'backgroundColor' => []
        ];        
        foreach($datasets as $key => $num){
            $label[] = $key;
            $arr['data'][] = $num;
            $arr['backgroundColor'][] = $this->__randomHexColorCode();
        }
        
        return [$label, $arr];   
    }
    
    function __enrichChartData($datasets){
        $arr = [];
        foreach($datasets as $key => $num){
            $arr[] = [
                'label' => $key,
                'backgroundColor' => $this->__randomRGB(),
                'data' => $num
            ];
        }
        
        return $arr;
    }
    
    function __randomRGB(){
        $r1 = rand(1,255);
        $r2 = rand(1,255);
        $r3 = rand(1,255);
        $op = rand(8, 10)/10;
        return "rgba($r1, $r2, $r3, $op)";   
    }
    
    function __randomHexColorCode(){
        return '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
    }
    
    function generateTitleWithCategory($count, $title, $category, $color='info'){
        if(empty($category))
            return $title;

        return  "$count. $title <span class='detailCategory' data-span-category='$category'></span>";
    }
    
    
    function generateTable($resource){
        $output = [];
        foreach($resource as $check => $attr){
            $criticality = $attr['criticality'];
            $checkPrefix = '';
            if($criticality == 'H'){
                $checkPrefix = "<i style='color: #dc3545' class='icon fas fa-ban'></i> ";
            } else if($criticality == 'M'){
                $checkPrefix = "<i style='color: #ffc107' class='icon fas fa-exclamation-triangle'></i> ";
            }
            
            $output[] = "<tr>";
            $output[] = "<td>$checkPrefix$check</td>";
            $output[] = "<td>" . $attr['value'] . "</td>";
            $output[] = "<td>" . $attr['shortDesc'] . "</td>";
            $output[] = "</tr>";
        }
        
        return implode("\n", $output);
    }
    
    function __getTemplateByKey($key){
        $path = TEMPLATE_DIR . '/' . $this->pageTemplate[$key];
        if(file_exists($path)){
            return $path;
        }else{
            __warn[$path . ' does not exists'];
            debug_print_backtrace();
        }
    }
    
    ### ---------- HEADER & FOOTER ----------
    function buildHeader(){
        $output = [];
        #file_get_pre_css
        $headerPreCSS = file_get_contents( $this->__getTemplateByKey('header.precss') );
        $output[] = str_replace(
            ['{$ADVISOR_TITLE}', '{$SERVICE}'], 
            [CONFIG::ADVISOR['TITLE'], strtoupper($this->service)], 
            $headerPreCSS
        );
        
        if(!empty($this->cssLib)){
            foreach($this->cssLib as $lib){
                $output[] = "<link ref='stylesheet' href='$lib'>";
            }
        }
        
        #file_get_post_css
        $headerPostCSS = file_get_contents( $this->__getTemplateByKey('header.postcss') );
        $output[] = str_replace(
            ['{$ADVISOR_TITLE}'],
            [CONFIG::ADVISOR['TITLE']],
            $headerPostCSS);
            
        return $output;
    }
    
    function buildFooter(){
        $output = [];
        #file_get_template preInlineJS
        $preJS = file_get_contents ( $this->__getTemplateByKey('footer.prejs') );
        
        $ADMINLTE_VERSION = CONFIG::ADMINLTE['VERSION'];
        $ADMINLTE_DATERANGE = CONFIG::ADMINLTE['DATERANGE'];
        $ADMINLTE_URL = CONFIG::ADMINLTE['URL'];
        $ADMINLTE_TITLE = CONFIG::ADMINLTE['TITLE'];
        
        $PROJECT_TITLE = CONFIG::ADVISOR['TITLE'];
        $PROJECT_VERSION = CONFIG::ADVISOR['VERSION'];
        eval("\$x = \"$preJS\";");
        $output[] = $x;
        
        if(!empty($this->jsLib)){
            foreach($jsLib as $lib)
                $output[] = "<script src='$lib'></script>";
            
        }
        
        if(!empty($this->js)){
            $inlineJS = implode("; ", $this->js);
            $output[] = "<script>$(function(){
                ".$inlineJS."
            })</script>";
            
        }
        
        #file_get_template postInlineJS
        $postJS = file_get_contents ( $this->__getTemplateByKey('footer.postjs') );
        $output[] = $postJS;
        
        return $output;
    }
    ### [END] ---------- HEADER & FOOTER ----------
    
    
    ### ---------- NAVIGATION ----------
    function buildBreadcrumb(){
        $output=[];
        $breadcrumb = file_get_contents( $this->__getTemplateByKey('breadcrumb') );
        $output[] = str_replace(
            ['{$SERVICE}'], 
            [strtoupper($this->service)], 
            $breadcrumb
        );   
        
        return $output;
    }
    
    function buildNav(){
        $ISHOME = $this->isHome ? 'active' : '';
        
        $output = [];
        #file_getsidebar
        $sidebarPRE = file_get_contents( $this->__getTemplateByKey('sidebar.precustom') );
        $output[] = str_replace(
            ['{$ADVISOR_TITLE}', '{$ISHOME}'],
            [CONFIG::ADVISOR['TITLE'], $ISHOME],
            $sidebarPRE
        );
        
        $arr = $this->buildNavCustomItems();
        $output[] = implode("\n", $arr);
        
        $sidebarPOST = file_get_contents( $this->__getTemplateByKey('sidebar.postcustom') );
        $output[] = $sidebarPOST;
        
        return $output;
    }
    
    function buildNavCustomItems(){
        $services = $this->services;
        $activeService = $this->service;
        
        $output = [];
        $output[] = "<li class='nav-header'>Services</li>";
        
        foreach($services as $name => $count){
            $class = $name == $activeService ? 'active' : '';
            $icon = $this->__navIcon($name);
            
            $output[] = "<li class='nav-item'>
            <a href='{$name}.html' class='nav-link {$class}'>
                <i class='nav-icon fas fa-{$icon}'></i>
                <p>".strtoupper($name)." <span class='badge badge-info right'>{$count}</span></p>
            </a>
            </li>
            ";
        }
        
        return $output;
    }
    
    function __navIcon($service){
        return self::serviceIcon[$service] ?? 'cog';
    }
    ### [END] ---------- NAVIGATION ----------
    
    function addJS($js){
        $this->js[] = $js;
    }
    
    function addJSLib($js){
        $this->jslib[] = $js;
    }
    
    function addCSSLib($css){
        $this->cssLib[] = $css;
    }
    
    
    ########### TEMPLATE HERE #############
    function checkIsLowHangingFruit($attr){
        if($attr['downtime'] == 0 && $attr['additionalCost'] == 0 && $attr['needFullTest'] == 0)
            return true;
        
        return false;
    }
    
    function buildContentSummary_default(){
        $output = [];
        
        ## Chart Building
        $summary = $this->reporter->cardSummary;
        $regions = $this->regions;
        $labels = [];
        $dataSets = [];
        foreach($summary as $label => $attrs){
            $labels[] = $label;
            $res = $attrs['__affectedResources'];
            foreach($regions as $region){
                $cnt = 0;
                if(!empty($res[$region]))   
                    $cnt = sizeof($res[$region]);
                    
                $dataSets[$region][] = $cnt;
            }
        }
        
        $html = $this->generateBarChart($labels, $dataSets);
        $card = $this->generateCard($id=$this->getHtmlId('SummaryChart'), $html, $cardClass='warning', $title='Summary', '', $collapse=true);
        $items = [[$card, '']];
        $output[] = $this->generateRowWithCol($size=12, $items, "data-context='summaryChart'");
        ## Chart completed
        
        ## Filter
        $filterTitle = "<i class='icon fas fa-search'></i> Filter";
        $filterByCheck = $this->generateFilterByCheck($labels);
        $filterRow = $this->generateRowWithCol($size=[6, 6, 12], $this->addSummaryControl_default(), "data-context='summary-control'");
        
        $output[] = $this->generateCard($id='summary-control', $filterByCheck . $filterRow, 'info', $filterTitle, '');
        
        ## SummaryCard Building
        $items = [];
        foreach($summary as $label => $attrs){
            $body = $this->generateSummaryCardContent($attrs);
                
            $badge = $this->generatePriorityPrefix($attrs['criticality'], "style='float:right'") 
                . ' ' . $this->generateCategoryBadge($attrs['__categoryMain'], "style='float:right'");
            $card = $this->generateCard($id=$this->getHtmlId($label), $body, '', $label, $titleBadge=$badge, $collapse=9);
            $divHtmlAttr = "data-category='".$attrs['__categoryMain']."' data-criticality='".$attrs['criticality']."'";
            
            if($this->checkIsLowHangingFruit($attrs))
                $divHtmlAttr .= " data-lhf=1";
            
            $items[] = [$card, $divHtmlAttr];
        }
        
        $output[] = $this->generateRowWithCol($size=4, $items, "data-context='summary'");
        return $output;
    }
    
    function buildContentDetail_default(){
        $output = [];
        $output[] = '<h5 class="mt-4 mb-2">Detail</h5>';
        
        $details = $this->reporter->getDetail();
        $count = 1;
        $previousCategory = "";
        foreach($details as $region => $lists){
            $items = [];
            $output[] = "<h6 class='mt-4 mb-2'>$region</h6>";
            foreach($lists as $identifierx => $attrs){
                $tab = [];
                $identifier = $identifierx;
                $category = '';
                $checkIfCategoryPresent = explode('::', $identifierx);
                if(sizeof($checkIfCategoryPresent) == 2){
                    list($category, $identifier) = $checkIfCategoryPresent;
                    if(empty($previousCategory))
                        $previousCategory = $category;
                }
                
                $tab[] = "<table class='table table-sm'><thead><tr>";
                $tab[] = "<th scole='col'>Check</th><th scole='col'>Current Value</th><th scole='col'>Recommendation</th>";
                $tab[] = "</tr></thead><tbody>";
                $tab[] = $this->generateTable($attrs);
                $tab[] = "</tbody></table>";
                $tab = implode("\n", $tab);
                
                if($previousCategory != $category && $category != '' && $count%2 == 0){
                    $items[] = [];
                }
                    
                
                $item = $this->generateCard($id=$this->getHtmlId($identifierx), $tab, $cardClass='warning', $title=$this->generateTitleWithCategory($count, $identifier, $category), '', false, true);
                $items[] = [$item, $divHtmlAttr=''];
                
                $previousCategory = $category;
                $count++;
            }
            
            $output[] = $this->generateRowWithCol($size=6, $items, "data-context=detail");
        }
        
        $str = <<<EOL
$('span.detailCategory').each(function(){
  var t = $(this);
  t.parent().parent().append("<span class='badge badge-info' style='float:right; line-height:15px'>"+t.data('span-category')+"</span>");
})    
EOL;
        $this->addJS($str);
        
        return $output;
    }
    
    function generateFilterByCheck($labels){
        $output = [];
        
        $opts = [];
        foreach($labels as $label)
            $opts[] = "<option value='$label'>$label</option>";   
        
        
        $options = implode("", $opts);
        
        $str = <<<EOL
<div class='col-md-12'>
<div class="form-group">
	<label>Checks</label>
	<div class="select2-purple">
	<select id='checkCtrl' class="select2" multiple data-placeholder="Select checks..." data-dropdown-css-class="select2-purple" style="width: 100%;">
		$options
	</select>
	</div>
</div>
</div>
EOL;

        return $str;
    }
    
    function addSummaryControl_default(){
        $jsServIdPrefix = "#" . $this->service . '-';
        
        $output=[];
        $output[] = '';
        
        $items = [];
        $str = <<<EOL
<div class="form-group">
  <label>Pillar</label>
  <select id='filter-pillar' class="form-control">
    <option value='-' selected>All</option>
    <option value='O'>Operation Excellence</option>
    <option value='R'>Reliablity</option>
    <option value='S'>Security</option>
    <option value='P'>Performance Efficiency</option>
    <option value='C'>Cost Optimization</option>
  </select>
</div>     
EOL;
        $items[] = [$str, ''];
        
        $str = <<<EOL
<div class="form-group">
  <label>Criticality</label>
  <select id='filter-critical' class="form-control">
    <option value='-' selected>All</option>
    <option value='H'>High</option>
    <option value='M'>Medium</option>
    <option value='L'>Low</option>
    <option value='I'>Informational</option>
  </select>
</div>  
EOL;
        $items[] = [$str, ''];
        
        $str = <<<EOL
<div class='col-md-12' >
  <div class='row'>
    <div class='col-md-4'>
      <div class="form-group">
          <div class="icheck-success d-inline">
              <input type="checkbox" id="cbLowHangingFruit">
              <label for="cbLowHangingFruit">Show low hanging fruit(s) only</label>
          </div>
      </div>
    </div>
    <div class='col-md-4'>
      <div class="form-group clearfix">
        <div class="icheck-success d-inline">
          <input type="radio" id="radio_cs1" name=radio_cs value='expand'>
          <label for="radio_cs1">Expand / </label>
        </div><div class="icheck-success d-inline">
          <input type="radio" id="radio_cs2" name=radio_cs value='collapse' checked>
          <label for="radio_cs2">Hide all cards</label>
        </div>
      </div>
    </div>
  </div>
</div>
EOL;
        $items[] = [$str, ''];
        
        $js = <<<EOL
$('.select2').select2()
var si = $('div[data-context="summary"] div[data-category]');

var cards = $('[data-context="summary"] div.col-md-4')
$('input[name=radio_cs]').change(function(){
  var v = $(this).val()
  var i = cards.find('button > i')
  if (v == 'expand') {
    cards.find('.collapsed-card').removeClass('collapsed-card')
    cards.find('div.card-body').show()
    i.removeClass('fa-plus').addClass('fa-minus')
  }else{
    tmp = $('[data-context="summary"] div.col-md-4 > div:not(.collapsed-card)')
    tmp.addClass('collapsed-card')

    cards.find('div.card-body').hide()
    i.removeClass('fa-minus').addClass('fa-plus')
  }

})

$('#filter-critical, #filter-pillar, #checkCtrl, #cbLowHangingFruit').change(function(){
var cb_lhf_on = $("#cbLowHangingFruit").is(':checked')
var pv = $('#filter-pillar').val();
var fc = $('#filter-critical').val();
var tiArray = $('#checkCtrl').val();

var s = '';
if(pv != '-') s += '[data-category="'+pv+'"]';
if(fc != '-') s += '[data-criticality="'+fc+'"]';

if(tiArray.length > 0){
	si.hide()
	$.each(tiArray, function(k, v){
		id = "$jsServIdPrefix" + v
		$(id).parent().addClass('showLater');
	})
	
	if(s.length > 0){
		$('div[data-context="summary"] .showLater'+s+'').show()
	}else{
		$('.showLater').show()
	}
	
	$('.showLater').removeClass('showLater')
}else if(s.length == 0){
  si.show();
}else{
  si.hide();
  $('div[data-context="summary"] div'+s+'').show()
}

$('[data-context="summary"] .col-md-4:visible').addClass('showLater2')
if(cb_lhf_on == true){
  $('.showLater2').hide()
  $('.showLater2[data-lhf=1]').show()
  $('.showLater2').removeClass('showLater2')
}

})
EOL;
        $this->addJS($js);
        return $items;
    }
}
<?PHP
/**
* @name as_propsheet.php - PHP class for creating "Property Sheet"-like DHTML pages
* Tabs-style (default) and Wizard-style supported (jQuery used)
* @author Alexander Selifonov < alex [at] selifan {dot} ru >
* @link http://www.selifan.ru
*
* @Copyright Alexander Selifonov, 2006-2024
* @version 1.42.001
* updated 2025-09-26
*/
define('TABSTYLE',0);
define('WIZARDSTYLE',1);
define('UITABS',10); # create tabs by using with jQuery.UI.tabs()
# css class names that will be used in rendered HTML code
global $as_cssclass, $as_iface;

class CPropertyPage {
  public $tabtitle = '';
  public $id = '';
  public $longTitle = '';
  public $tooltip = '';
  public $drawobject = '';
  public $func_evtnext = '';
  public $hidden = false;
  public $disabled = false;
  public $useroptions = array();
  public $userparam1 = ''; // 4 user parameters passed to HTML-drawing function
  public $userparam2 = '';
  public $userparam3 = '';
  public $userparam4 = '';
  public $footer = FALSE;
  public $tabNo = '';
  static $uniqueNo = 0;

  public function __construct($options,$drawfunc='',$evtnext='', $param1='',$param2='',$param3='',$param4='',$attrib=0, $footer=FALSE) {
      # writeDebugInfo("options : ", $options);
      if(is_array($options)) { // assoc.array passed
        $this->tabtitle = isset($options['title']) ? $options['title'] : 'No title';
        $this->longTitle = isset($options['longtitle']) ? $options['longtitle'] : '';
        $this->tooltip = isset($options['tooltip']) ? $options['tooltip'] : '';
        $this->drawobject = isset($options['drawfunc']) ? $options['drawfunc'] : false;
        $this->id = $options['id'] ?? '';
        $this->func_evtnext = isset($options['evtnext']) ? $options['evtnext'] : '';
        $this->userparam1 = isset($options['param1']) ? $options['param1'] : '';
        $this->userparam2 = isset($options['param2']) ? $options['param2'] : '';
        $this->userparam3 = isset($options['param3']) ? $options['param3'] : '';
        $this->userparam4 = isset($options['param4']) ? $options['param4'] : '';
        $this->footer = $options['footer'] ?? '';
        if(!empty($options['tabno'])) $this->tabNo = $options['tabno'];
        if(empty($this->id)) {
            if(!empty($this->tabNo)) $this->id = $this->tabNo;
            else $this->id = (++self::$uniqueNo);
        }

        $this->useroptions = $options;
        if(!empty($options['attrib'])) {
            $this->hidden = ($options['attrib'] & 1);
            $this->disabled = ($options['attrib'] & 2);
        }
      }
      elseif(is_array($drawfunc)) { // assoc.array passed
        # writeDebugInfo("KT-002 ", $options);
        $titles = is_array($options) ? $options : explode('|', $options);
        $this->tabtitle = $titles[0];
        $this->longTitle = isset($titles[1]) ? $titles[1] : '';
        $this->tooltip = isset($drawfunc['tooltip']) ? $drawfunc['tooltip'] : '';
        $this->drawobject = $drawfunc;
        $this->func_evtnext = $evtnext;
        $this->userparam1 = $param1;
        $this->userparam2 = $param2;
        $this->userparam3 = $param3;
        $this->userparam4 = $param4;
        $this->footer = $footer;
        if(!empty($this->tabNo)) $this->id = $this->tabNo;
        else $this->id = (++self::$uniqueNo);

        if(!empty($drawfunc['tabno'])) $this->tabNo = $drawfunc['tabno'];
        $this->useroptions = $drawfunc;
        if(!empty($attrib)) {
            $this->hidden = ($attrib & 1);
            $this->disabled = ($attrib & 2);
        }
      }
      else {
        $titles = explode('|', $options);
        $this->tabtitle = $titles[0];
        $this->longTitle = isset($titles[1]) ? $titles[1] : '';
        $this->drawobject = $drawfunc;
        $this->func_evtnext = $evtnext;
        $this->userparam1 = $param1;
        $this->userparam2 = $param2;
        $this->userparam3 = $param3;
        $this->userparam4 = $param4;
        $this->footer = $footer;
        $this->hidden = ($attrib & 1);
        $this->disabled = ($attrib & 2);
        $this->id = (++self::$uniqueNo);
      }
  }
}

class CFormField {
  public $fname = '';
  public $ftype = 'text';
  public $prompt = '';
  public $title = '';
  public $initvalue = '';
  public $options = '';
  public $maxlength = 0;
  public $width = 0;
  public $height = 0;
  public $addparams = '';
  public $onchange = '';
  public $onclick = '';
  public $checkevent = '';
  public $groupid = '';
  public $rowclass = '';
  public $children = NULL; # for toolbar type - buttons container
  public function __construct($name,$type='text',$prompt='',$initvalue='',$vlist='',
            $maxlength=0,$width=0,$title='',$onchange='',$addprm='',$groupid='') {
      $this->fname = $name;
      if(is_array($type)) {
        # WriteDebugInfo($name,': received array:', $type);
        $this->ftype = isset($type['type']) ? strtolower($type['type']) : 'text'; ;
        $this->prompt = isset($type['prompt']) ? $type['prompt'] : $this->fname;
        $this->title = isset($type['title']) ? $type['title'] : $this->fname;
        $this->initvalue = isset($type['initvalue']) ? $type['initvalue'] : $initvalue;
        $this->options = isset($type['options']) ? $type['options'] : $vlist;
        $this->maxlength = isset($type['maxlength']) ? $type['maxlength'] : 0;
        $this->width = isset($type['width']) ? $type['width'] : 0;
        $this->height = isset($type['height']) ? $type['height'] : 0;
        $this->onchange = isset($type['onchange']) ? $type['onchange']:'';
        $this->onclick = isset($type['onclick']) ? $type['onclick']:'';
        $this->checkevent = isset($type['checkevent']) ? $type['checkevent']:'';
        $this->addparams = isset($type['addparams']) ? $type['addparams']:$addprm;
        $this->groupid = isset($type['groupid']) ? $type['groupid']: $groupid;
        $this->rowclass = isset($type['rowclass']) ? $type['rowclass']: '';
        if(!empty($type['children'])) $this->children = $type['children'];
      }
      else {
        $this->ftype = strtolower($type);
        $this->prompt = $prompt;
        $this->initvalue = $initvalue;
        $this->options = $vlist;
        $this->maxlength = $maxlength;
        $this->width = $width;
        $this->title = $title;
        $this->onchange = $onchange;
        $this->addparams = $addprm;
        $this->groupid = $groupid;
      }
      if(!empty($this->options) && is_string($this->options)) {
        $this->options = GetArrayFromString($this->options);
      }
  }

  public function renderMultiSelect($initValues)  {
      $height = (($this->height>0) ? $this->height : CPropertySheet::$BLIST_HEIGHT) .'px';
      $width = (($this->width>0) ? "width:{$this->width}px;" : '');
      $arCurVal = is_array($initValues) ? $initValues : explode(',', $initValues);
      # $msBody = "<table class='zebra'>";
      $flname = $this->fname;
      $lbclass = ' class="rt"'; # rt: right align label
      $trAttr = " id=\"tr_$flname\""; # ID for table row (for dynamic hide/show)
      if(!empty($this->rowclass)) {
        $trAttr .= " class=\"{$this->rowclass}\"";
      }
      $msBody = "<tr $trAttr><td><div class='bordered' style='$width max-height:{$height}; overflow:auto;'>"
        . "<table class='table table-striped table-hover table-bordered'>";
      foreach($this->options as $no => $row) {
          $rKeys = array_keys($row);
          $itVal = $row[0] ?? $no;
          $itLabel = $row[1] ?? $row[$rKeys[1]] ?? $row[$rKeys[0]];
          if($itVal === '<') $msBody.= "  <tr><td><b>$itLabel</b></td></tr>\n";
          else {
              $checked = in_array($itVal,$arCurVal) ? "checked" : '';
              $msBody .= "  <tr><td style='width:100%'><label><input type='checkbox' name='{$flname}[]' value='$itVal' $checked /> $itLabel</label></td></tr>\n";
          }
      }
      $msBody .= "</table></div><td>$this->prompt</td></tr>";
      return $msBody;
  }
}

class CPropertySheet {
  const VERSION = '1.42';
  const STYLE_TABS   = 0;
  const STYLE_WIZARD = 1;
  const STYLE_UITABS = 10;
  const STYLE_VTABS = 20; // Tabs are in left column (vertical orientation)

  const TABS_LEFT    = 0;
  const TABS_CENTER  = 1;
  const TABS_RIGHT   = 2;
  const TABS_JUSTIFY = 10;

  var $sheetid = ''; // unique Sheet id, used as prefix for all pages id's
  var $style = TABSTYLE;
  var $tabsPosition = 0;
  var $width = 800;
  var $height= 120;
  var $finishfunc = '';
  var $finish_caption = 'Finish';
  var $finish_enabled = false;
  var $finish_visible = true;
  var $pages = array();
  var $_tabchangeEvent = '0'; # registered js function to fire on every tab change
  var $_jqueryui = false;
  protected $tabNo = '';
  static public $_commonJsSent = FALSE;
  static public $BLIST_HEIGHT = 120; // height in px for "blist" input field
  static private $sheet_cnt = 0; // increases each time when new CpropertySheet created, to create unique ID
  static private $sheetlist = array(); // all created shhet ID's, to avoid duplicates

  public static function getVersion() { return self::VERSION; }

  function __construct($param, $width=null,$height=null,$sheetstyle=TABSTYLE, $tabsPos=false) {
      self::$sheet_cnt++;
      if(is_array($param)) { // all needed parameters pased in assoc.array
          $newid = isset($param['id'])? $param['id'] : 'propsheet'.self::$sheet_cnt;
          if (in_array($newid, self::$sheetlist)) $newid .= rand(10000000,99999999);
          $this->sheetid = self::$sheetlist[] = $newid;
          if(!empty($param['jqueryui'])) $this->_jqueryui = true; # user jQuery UI tabs functionality and active theme
          if(isset($param['width']))  $this->width = $param['width'];
          if(isset($param['height'])) $this->height = $param['height'];
          if(isset($param['style']))  $this->style = $param['style'];
          $this->tabsPosition = (isset($param['tabsPosition'])) ? $param['tabsPosition'] : self::TABS_JUSTIFY;
      }
      else {
        $this->sheetid = $param;
        $this->tabsPosition = ($tabsPos!==false) ? $tabsPos : self::TABS_JUSTIFY;
        $this->style = $sheetstyle;
      }
    if($width) $this->width = $width;
    if($height) $this->height = $height;
    if($sheetstyle) $this->style = $sheetstyle;
  }

  function TabChangeEvent($funcname) { $this->_tabchangeEvent=$funcname; }

  function AddPage($tabtitle,$drawfunc='', $jsfunc_next='', $param1='',$param2='',$param3='',$param4='', $attrib=0, $footer=FALSE) {
      if(is_array($tabtitle)) {
          $this->pages[] = new CPropertyPage($tabtitle);
      }
      else $this->pages[] = new CPropertyPage($tabtitle,$drawfunc,$jsfunc_next, $param1,$param2,$param3,$param4,$attrib, $footer);
  }
  function SetFinishButton($function,$caption='',$enabled=false) {
    $this->finishfunc = $function;
    if($caption!='') $this->finish_caption = $caption;
    $this->finish_enabled = $enabled;
  }

  static public function iniCssNames($style = '') {
  	global $as_cssclass;
	if(!isset($as_cssclass)) $as_cssclass = array();
	if(empty($as_cssclass['textfield'])) $as_cssclass['textfield'] = 'ibox';
	if(empty($as_cssclass['button'])) $as_cssclass['button'] = 'btn btn-primary';
    if ($style === self::STYLE_VTABS) {
        if(empty($as_cssclass['tactive'])) $as_cssclass['tactive'] = 'tblactive_v';
        if(empty($as_cssclass['tinactive'])) $as_cssclass['tinactive'] = 'tblinactive_v';
    }
    else {
        if(empty($as_cssclass['tactive'])) $as_cssclass['tactive'] = 'tblactive';
        if(empty($as_cssclass['tinactive'])) $as_cssclass['tinactive'] = 'tblinactive';
    }
	if(empty($as_cssclass['tabdisabled'])) $as_cssclass['tabdisabled'] = 'tabdisabled';
	if(empty($as_cssclass['pagebody'])) $as_cssclass['pagebody'] = 'pagebody';
	if(empty($as_cssclass['pagebodywiz'])) $as_cssclass['pagebodywiz'] = 'pagebodywiz';
	if(empty($as_cssclass['row_header'])) $as_cssclass['row_header'] = 'head';
  }
  static public function commonJsBlock() {

      global $as_cssclass;
      self::iniCssNames();
      $jscode = <<< EOJS
asSheets = {
  sheets: []
  ,addSheet: function(shid,tabsList) {
    // console.log("addSheet ", shid," tabsList:",tabsList);
    if(!!tabsList) asSheets.sheets[shid] = tabsList;
    else {
      asSheets.sheets[shid] = {};
    }
    // console.log('addSheet() for '+shid);
  }
  ,hideTab: function(sheetid,tabid) {
     jQuery('#'+sheetid+tabid+',#as_page'+sheetid+tabid).hide();
  }
  ,showTab: function(sheetid,tabid) {
     jQuery('#'+sheetid+tabid).show();
  }
  ,selectTab: function(shid,tabid) {
      // console.log("selectTab ", shid, tabid);
      if(jQuery('#'+shid+tabid).prop('disabled')) return false;
      if(!!asSheets.sheets[shid]['events']) eval(asSheets.sheets[shid]['events']);
      if(asSheets.sheets[shid]['style']==1) { // WIZARDSTYLE
          var curpg = asSheets.sheets[shid]['curTab'];
          var result = 1;
          if(tabid=='+1' && asSheets.sheets[shid]['pageEvents'][curpg]!=undefined)
            try { result = eval(asSheets.sheets[shid]['pageEvents'][curpg]); }
            catch(e) {alert("exception. Error name: "+e.name+". Error message: "+e.message);  }
          if(!result) return; // wrong parameters, don't leave a page until he fix it !
          if(tabid==='-1') {
            tabid = asSheets.sheets[shid]['curTab']-1;
            if (tabid>=1) asSheets.sheets[shid]['curTab']=tabid;
          }
          else if(tabid==='+1') {
          	tabid = asSheets.sheets[shid]['curTab']+1;
          	if (tabid <=asSheets.sheets[shid].tabCount)
          	   asSheets.sheets[shid]['curTab']=tabid;
          }
      }
      // console.log(asSheets.sheets[shid]);
      for(var kk in asSheets.sheets[shid].tabs) {
          var k = parseInt(kk)+1;
          var myTab = asSheets.sheets[shid].tabs[kk];
          if(asSheets.sheets[shid]['style']==0 || asSheets.sheets[shid]['style']==20) { // TABS, VTABS
             if(myTab==tabid) jQuery('#'+shid+myTab).removeClass('$as_cssclass[tinactive]').addClass('$as_cssclass[tactive]');
             else jQuery('#'+shid+myTab).removeClass('$as_cssclass[tactive]').addClass('$as_cssclass[tinactive]');
          }
          if(myTab==tabid) jQuery('#as_page'+shid+myTab).show();
          else {
            // console.log("myTab not tabid:", myTab, tabid);
            jQuery('#as_page'+shid+myTab).hide();
          }
      }
      if(asSheets.sheets[shid]['style']==1) { // WIZARDSTYLE
         jQuery('#asprbt_prev'+shid).prop('disabled',(tabid<=1));
         jQuery('#asprbt_next'+shid).prop('disabled',(tabid >= asSheets.sheets[shid]['tabCount']));
      }
      asSheets.sheets[shid].curTab = tabid;
      return false;
  }
  ,setEnabled: function(shid,tabid,val) {
    if(val) {
        jQuery('#'+shid+tabid).removeClass('$as_cssclass[tabdisabled]').removeAttr('disabled').css('cursor','pointer');
    }
    else {
      jQuery('#'+shid+tabid).addClass('$as_cssclass[tabdisabled]').prop('disabled',true).css('cursor','auto');
      if(asSheets.sheets[shid].curTab == tabid) {
          var rr = this.selectAnyTab(shid);
      }
    }
  }
  ,isEnabled: function(shid, tabid) {
    return (jQuery('#'+shid+tabid).prop('disabled') ? false : true);
  }
  ,selectAnyTab: function(shid) {
    // find first non-disabled non-hidden tab and activate it
    var curtab = asSheets.sheets[shid].curTab;
    if(!jQuery('#'+shid+curtab).prop('disabled') && jQuery('#'+shid+curtab).css('display')!='none') {
      return curtab;
    }
    for(var kk in asSheets.sheets[shid].tabs) {
      var tabaid = asSheets.sheets[shid].tabs[kk];
      if(!jQuery('#'+shid+tabaid).prop('disabled') && jQuery('#'+shid+tabaid).css('display')!='none') {
         this.selectTab(shid,tabaid);
         return tabaid;
      }
    }
    return false;
  }
};

EOJS;
    self::$_commonJsSent = TRUE;
    return $jscode;
  }
  /**
  * Rendering final JS+HTML code for property sheet
  *
  * @param mixed $startpage initially active page No (0-based)
  * @param mixed $buffered if TRUE|1, don't send to client, just return HTML code instead
  * @param mixed $commonjs if passed, TRUE means "send common js code for property sheet, FALSE - don't (AJAX calls from propsheets)
  */
  public function Draw($startpage=0, $buffered=false, $commonjs=NULL) {
    global $as_cssclass,$as_iface,$psheet_jsdone;

    self::iniCssNames($this->style);

	# localized interface strings:
	if(empty($as_iface['btn_prev'])) $as_iface['btn_prev'] ='Previous';
	if(empty($as_iface['btn_next'])) $as_iface['btn_next'] ='Next';

    $width = self::makeCssSize($this->width);
    # if($buffered) ob_start();
	$htmlret = '';
    if(count($this->pages)<1) return false;
    $twidth = intval(100/count($this->pages));
    if($startpage===false) $startpage='0';
    if(is_object($this->pages[$startpage]) && $this->pages[$startpage]->hidden) { #<3>
    // starting-page is hidden, so look for thr first visible page
      foreach ($this->pages as $ipg => $page) {
        if($ipg!=$startpage && !$page->hidden) {$startpage=$ipg; break; }
      }
    } #<3>
    $addCode = '';
    $sendcommon = ($commonjs===NULL) ? self::$_commonJsSent : !$commonjs;
    if(!$sendcommon) {
        $commonCode = self::commonJsBlock();
        $htmlret .= "<script type='text/javascript'>$commonCode</script>\n";
    }
    $pgCnt = count($this->pages);
    $startPg =  $startpage+1;
    $tabList = [];
    foreach($this->pages as $id => $onePage) {
        $tabList[] = "'$onePage->id'";
    }
    $strTabList = implode(',', $tabList);

    $addCode .= <<< EOJS

asSheets.addSheet('{$this->sheetid}',{
    style: '{$this->style}'
   ,events: []
   ,ontabChange: {$this->_tabchangeEvent}
   ,tabCount : $pgCnt
   ,tabs: [ $strTabList ]
   ,curTab : $startPg
   ,pageEvents : []
});
EOJS;


    $tabs = '';
    $sheets = '';
    if($this->style == self::STYLE_TABS) {
        $outline = <<< EOHTM
<div id="as_propsheet_{$this->sheetid}">
  <!-- start tabs --><table class="custom-nav w-100"><!-- tabs --></table>
  <div class="proppages"><!-- sheets start-->
  <!-- sheets -->
  <!-- sheets end -->
  </div>
</div><!-- as_propsheet_{$this->sheetid} end -->
EOHTM;

      if($this->tabsPosition == self::TABS_RIGHT) $tabs .= "<td style='width:90%'></td>";
      elseif($this->tabsPosition == self::TABS_CENTER) $tabs .= "<td style='width:45%'>&nbsp;</td>";

      foreach ($this->pages as $kk=>$onePage) {
        $prompt = $this->pages[$kk]->tabtitle;

        $cls = ($kk==$startpage)? $as_cssclass['tactive']:$as_cssclass['tinactive'];
        $cls .= ($onePage->disabled) ? ' '.$as_cssclass['tabdisabled'] : '';
        $k1 = $kk+1;
        $styles = array();
        if($this->tabsPosition==self::TABS_JUSTIFY) $styles[] = "width:{$twidth}%";
        if($onePage->hidden) $styles[] = 'display:none';

        $attr = count($styles)? 'style="'.implode(';',$styles).'"' : '';
        if($onePage->disabled) $attr .= ' disabled="disabled"';
        if(!empty($onePage->tooltip)) $attr .= ' title="'. $onePage->tooltip . '"';

        $tabNo = $tabId = $onePage->id;
        $attr .= " onclick=\"asSheets.selectTab('{$this->sheetid}','$tabId')\"";

        $bigTabId =  $this->sheetid . $tabId;
        $tabs .= "<td id=\"$bigTabId\" class=\"$cls\" $attr>$prompt</td>\n";
        $sheets .= $this->_generateSheetHtml($kk);
      }

      if($this->tabsPosition == self::TABS_LEFT) $tabs .= "<td style='width:90%'></td>";
      elseif($this->tabsPosition == self::TABS_CENTER) $tabs .= "<td style='width:45%'>&nbsp;</td>";
      $htmlret .= strtr($outline, ['<!-- tabs -->'=> $tabs, '<!-- sheets -->' => $sheets]);
    }

    elseif($this->style == self::STYLE_VTABS) {
        $outline = <<< EOHTM
<div id="as_propsheet_{$this->sheetid}" class="text-center card">
  <div class="proppages_v">
  <table><tr>
    <td class="vtop"><div class="col-2"><table class="nav-y"><!-- tabs --></table></div></td>
    <td class="vtop w-100 p-2"><!-- sheets --></td>
  </tr></table>
  </div>
EOHTM;

      # $htmlret .= "<table class='propsheet' border='0' style='width:$width' cellpadding='0' cellspacing='0'>";
      foreach($this->pages as $kk=>$onePage) {
        $prompt = $onePage->tabtitle;

        $cls = ($kk==$startpage)? $as_cssclass['tactive']:$as_cssclass['tinactive'];
        $cls .= ($onePage->disabled) ? ' '.$as_cssclass['tabdisabled'] : '';
        $k1 = $kk+1;
        $styles = array();
        if($onePage->hidden) $styles[] = 'display:none';

        $attr = count($styles)? 'style="'.implode(';',$styles).'"' : '';
        if($onePage->disabled) $attr .= ' disabled="disabled"';
        $tabId = $onePage->id;
        if(!empty($onePage->tooltip)) $attr .= ' title="'. $onePage->tooltip . '"';
        $attr .= " onclick=\"asSheets.selectTab('{$this->sheetid}',$k1)\"";
        $tabs .= "<tr><td id='{$this->sheetid}{$tabId}' class='$cls' $attr >$prompt</td></tr>\n";
        $sheets .= $this->_generateSheetHtml($kk);
      }
      $htmlret .= strtr($outline, ['<!-- tabs -->'=> $tabs, '<!-- sheets -->' => $sheets]);

    }
    elseif($this->style == self::STYLE_UITABS) {
        $outline = <<< EOHTM
<div id=\"propsheet_tabsholder\" class=\"_uitabs_holder\" style=\"width:{$tabwidth}px; min-height:{$this->height}px;\">
  <ul><!-- tabs --></ul>
  <!-- sheets -->
</div>
<script type='text/javascript'>jQuery('._uitabs_holder').tabs();</script>
EOHTM;

        $tabwidth=$this->width+16;

        for($kk=0;$kk<count($this->pages);$kk++) { # ui.tabs headers
           $k1=$kk+1;
           $tabs .= "\n<li id=\"tab_{$this->sheetid}{$k1}\"><a href=\"#as_page{$this->sheetid}{$k1}\"><span>{$this->pages[$kk]->tabtitle}</span></a></li>";
        }

    }
    else { # Wizard style
#========================
        $cls = ($this->style===self::STYLE_WIZARD)? $as_cssclass['pagebodywiz'] : $as_cssclass['pagebody'];

        foreach($this->pages as $kk=>$page) {
            $k1=$kk+1;
            $displ = ($kk==$startpage)? '':"style='display:none'";
            $htmlret .= "<td id='as_page{$this->sheetid}{$k1}' {$displ} valign='top' style='text-align:center'>\n";
            if($this->style==WIZARDSTYLE) {
                $htmlret .= "<h3 align='center'>{$this->pages[$kk]->tabtitle}</h3>\n";
            }
            $htmlret .= $this->_generateSheetHtml($kk);
            $htmlret .= "\n</td><!-- page as_page{$this->sheetid}{$k1} end -->\n";
        }
        $htmlret .= "</tr></table><!-- prop sheet {$this->sheetid} end-->\n";

        if($this->style== self::STYLE_WIZARD) { // register 'next' event functions

          for($kk=0;$kk<count($this->pages);$kk++){
            if($this->pages[$kk]->func_evtnext!='') $addCode .= "prop_events{$this->sheetid}[$kk]='{$this->pages[$kk]->func_evtnext}';\n";
          }

          $htmlret .= "<div class=\"asprop-wizard-buttons\"><!-- wizard buttons start -->\n";
          $prevdis = ($startpage==0)? 'disabled':'';
          $nextdis = ($startpage+1<count($this->pages))? '':'disabled';
          $htmlret .= "<button id='asprbt_prev{$this->sheetid}' name='bt_previous' class='{$as_cssclass['button']}' style='width:160' $prevdis onclick='asSheets.selectTab(\"{$this->sheetid}\",\"-1\")'>{$as_iface['btn_prev']}</button>&nbsp;&nbsp;
          <button id='asprbt_next{$this->sheetid}' name='bt_next' class='{$as_cssclass['button']}' style='width:160' $nextdis onclick='asSheets.selectTab(\"{$this->sheetid}\",\"+1\")'>{$as_iface['btn_next']}</button>";
          if(!empty($this->finish_caption) && !empty($this->finishfunc)) {
            $finenab=$this->finish_enabled?'':'disabled="disabled"';
            $btshow = $this->finish_visible ? '' : 'display:none;';
            $htmlret .= "&nbsp;&nbsp;<button id='asprbt_finish{$this->sheetid}' name='bt_finish' class='{$as_cssclass['button']}' $finenab style='$btshow' onclick='{$this->finishfunc}'>{$this->finish_caption}</button>";
          }
          $htmlret .= "</div><!-- wizard buttons end -->\n";
        }
    }

    unset($tabs, $sheets);
    if ($addCode) $htmlret .= "<script type='text/javascript'>$addCode\n</script>\n";
    # file_put_contents('tmp/_fullpage.htm', $htmlret);
    if ($buffered) return $htmlret;
    else echo $htmlret;
    return '';
# ===================================
  } //Draw() end

  private function _generateSheetHtml($pgno, $curpage = 0) {

      $style = $pgno != $curpage ?  'style="display:none;"': '';
      $pageid = 'as_page' . $this->sheetid . $this->pages[$pgno]->id;

      $htmlret = "<div class=\"proppage\" id=\"$pageid\" $style>";
      $lowclass = is_object($this->pages[$pgno]->drawobject) ? strtolower(@get_class($this->pages[$pgno]->drawobject)) : '';
      if($lowclass==='cpropertysheet') {
          $htmlret .= $this->pages[$pgno]->drawobject->Draw(0,true);
      }
      elseif(is_array($this->pages[$pgno]->drawobject)) {
          $htmlret .= $this->DrawFormPage($pgno);
      }
      elseif(is_string($this->pages[$pgno]->drawobject)) {
               if( is_callable($this->pages[$pgno]->drawobject) ) {
                   ob_start();
                   $htmcode = call_user_func($this->pages[$pgno]->drawobject, $this->pages[$pgno]->userparam1,$this->pages[$pgno]->userparam2,
                      $this->pages[$pgno]->userparam3,$this->pages[$pgno]->userparam4);
                   $bufcode = ob_get_clean();
                  if($htmcode) $htmlret .= $htmcode;
                  if ($bufcode) $htmlret .= $bufcode;
               }
               else {
                   if(!empty($this->pages[$pgno]->longTitle))
                       $htmlret .= "<div class='darkhead with-background mb-3'>" . $this->pages[$pgno]->longTitle . "</div>\n";

                   $htmlret .= $this->pages[$pgno]->drawobject;
               }
      }
      elseif(is_object($this->pages[$pgno]->drawobject)) {

           if (method_exists($this->pages[$pgno]->drawobject,'drawPage')) {
               if(!isset($this->pages[$pgno]->useroptions['page'])) $this->pages[$pgno]->useroptions['page'] = $pgno;
               ob_start();
               $htmcode = $this->pages[$pgno]->drawobject->drawPage($this->pages[$pgno]->useroptions);
               if(is_string($htmcode)) $htmlret .= $htmcode;
                  if($bufcode = ob_get_clean()) $htmlret .= $bufcode;
           }
      }
      else { $htmlret .= "Empty page $pgno!"; }

      $htmlret .= "\n</div>";
      # file_put_contents("tmp/page_$pgno.htm", $htmlret);
      return $htmlret;
  }

  static function makeCssSize($sz) {
      $numsz = floatval($sz);
      $ret = $sz;
      if($numsz>0 and "$numsz" === (string)$sz) $ret = $sz.'px';
      return $ret;
  }

  # There is a field set, generate HTML code with inputs for them
  function DrawFormPage($pageNo) {
    global $as_cssclass;
    if(is_numeric($pageNo)) $onePage =& $this->pages[$pageNo];
    elseif(is_object($pageNo)) $onePage = $pageNo;
    # writeDebugInfo("$pageNo: ", $onePage->tabtitle);
    $fields = $onePage->drawobject;
    $htmlret = '';
    if(!empty($onePage->longTitle)) {
        $ttl = $onePage->longTitle;
        $htmlret .= "<div class='darkhead with-background mb-3'>$ttl</div>\n";
    }
    $htmlret .= "<table style='width:100%;'>\n";
    $inGroup = 0;
    $elems = [];
    $chkLabel = is_callable('AppEnv::getConfigValue') ? AppEnv::getLocalized('cfg_btncheck_label','Check') : 'Check';
    $chkLabelTitle = is_callable('AppEnv::getConfigValue') ? AppEnv::getLocalized('cfg_btncheck_title','Check value') : 'Check Value';
    foreach($fields as $ii=>$fdef) { #<3>
      if(!is_object($fdef)) continue;
      $fname = $fdef->fname;
      $prompt = $fdef->prompt;
      $init = $fdef->initvalue;
      $addstr = '';
      $style='';
      $curcSet = defined('MAINCHARSET') ? constant('MAINCHARSET') : 'UTF-8';
      if($fdef->ftype=='date') {
          $fdef->width = '70';
          $fdef->maxlength = '10';
      }
      if(!empty($fdef->maxlength)) $addstr .= " maxlength='{$fdef->maxlength}'";
      if(!empty($fdef->addparams)) $addstr .= ' '.$fdef->addparams;
      if(!empty($fdef->title)) $addstr .= " title='{$fdef->title}'";
      $checkEvt = empty($fdef->checkevent) ? '' :
         "</td><td><input type=\"button\" class=\"btn btn-primary\" style=\"float:right\"onclick=\"$fdef->checkevent\" "
           . "value=\"$chkLabel\" title=\"$chkLabelTitle\"/>";
      $wdth = $fdef->width;
      $wpost = ($wdth==0 OR (strpos($fdef->width,'%') OR stripos($fdef->width,'px') OR stripos($fdef->width,'em'))) ? '':'px';
      if($wpost) $wdth = ((string)$wdth) . $wpost;
      $styles = array();
      $style = '';
      if(!empty($wdth)) $styles[]="width:$wdth";
      if($style!='') $styles[] = trim($style);
      if ($fdef->ftype === 'textarea') {
          $styles[] = 'resize: none';
          $styles[] = 'width:100%';
          $styles[] = 'height:200px';
      }
      if (count($styles)) $style = " style='" . implode('; ', $styles) . "'";

      $trAttr = "id=\"tr_$fname\""; # ID for table row (for dynamic hide/show)
      # $fdef->rowclass .= ' d-flex';
      if(!empty($fdef->rowclass)) {
            $trAttr .= " class=\"{$fdef->rowclass}\"";
      }
      # if($fdef->groupid) $trAttr .= " class=\"fmtr_{$fdef->groupid}\"";
      $addclasses = $fdef->groupid ? ' fmin_'.$fdef->groupid : '';
      $lbclass = ' class="rt"'; # rt: right align label
      # if(stripos($fname, 'url')!==FALSE) writeDebugInfo("$fname: ", $fdef);
      switch($fdef->ftype) { #<4>
        case 'head': case 'header':
          if($inGroup) {
              $htmlret .= "\n</table></fieldset>\n";
          }
          else $htmlret .= '</table>';
          $inGroup = 1;
          $htmlret .= "<fieldset><legend>$prompt</legend><table style='width:100%'>";
          /*
          $hstyle = isset($as_cssclass['row_header_style'])? "style=\"{$as_cssclass['row_header_style']}\"" : '';
          $htmlret .= "<tr class='{$as_cssclass['row_header']}' $trAttr><td colspan=2 align='center' {$addstr} $hstyle><b>$prompt</b></td></tr>\n";
          */

          break;
        case 'text':
        case 'url':
          $flags = defined('ENT_HTML401') ? (ENT_HTML401 | ENT_COMPAT): ENT_COMPAT; # ENT_HTML401 in PHP 5.4.* !
          $init=htmlspecialchars($init, $flags, $curcSet);
          if ($fdef->ftype === 'url') {
              $addstr .= ' ondblclick="this.value=decodeURI(this.value)"';
          }
          $htmlret .= "<tr $trAttr>"
            . "<td $lbclass><input type=\"text\" name=\"$fname\" id=\"$fname\" class=\"{$as_cssclass['textfield']}$addclasses\"{$style}{$addstr} "
            . "value=\"$init\"></td><td nowrap=\"nowrap\">$prompt {$checkEvt}</td></tr>\n";
          break;
        case 'number': case 'int':
          $init=str_replace("'",'',$init);
          $htmlret .= "<tr $trAttr><td $lbclass><input type=\"number\" name=\"$fname\" id=\"$fname\" class='{$as_cssclass['textfield']}"
            . "$addclasses ct'{$style}{$addstr} value='$init' onchange='NumberRepair(this,true)'></td><td>$prompt {$checkEvt}</td></tr>\n";
          break;
        case 'password':
          $htmlret .= "<tr $trAttr><td width=\"99%\" $lbclass><input type='password' name=\"$fname\" id=\"$fname\" class='{$as_cssclass['textfield']}"
            . "$addclasses' {$style}{$addstr} value='$init'></td><td>$prompt {$checkEvt}</td></tr>\n";
          break;
        case 'date':
          if(strlen($init)>7 AND intval($init)>1000) $init = to_char($init);
          $htmlret .= "<tr $trAttr><td$lbclass><input type='text' name=\"$fname\" id=\"$fname\" class='{$as_cssclass['textfield']}$addclasses datefield'"
            . " {$style}{$addstr} value='$init'></td><td>$prompt {$checkEvt}</td></tr>\n";
          break;
        case 'textarea':
          $htmlret .= "<tr $trAttr><td colspan='2'>$prompt {$checkEvt}<br><textarea name=\"$fname\" id=\"$fname\" class='{$as_cssclass['textfield']}$addclasses'"
            . " {$style}>$init</textarea></td></tr>\n";
          break;

        case 'checkbox':
          $chk = $init?'checked':'';
          $onClick= empty($fdef->onchange)? '':" onClick='{$fdef->onchange}'";
          $htmlret .= "<tr $trAttr><td class='$addclasses' style='text-align:right'><input type='checkbox' name=\"$fname\" id=\"$fname\" value='1'"
            . " {$addstr} {$onClick} {$chk}/></td><td><label for='$fname'>$prompt {$checkEvt}</label></td></tr>\n";
          break;

        case 'select':
          $htmlret .= "<tr $trAttr><td$lbclass><select name=\"$fname\" id=\"$fname\" class='{$as_cssclass['textfield']}$addclasses'{$style} {$addstr}>\n";
          $lst = $fdef->options;
          if(is_array($lst))
              $htmlret .= DrawSelectOptions($lst, $init, TRUE)
                       . "</select></td><td>$prompt {$checkEvt}</td></tr>\n";
          break;

        case 'blist':
        case 'multi-select':
          $htmlret .= $fdef->renderMultiSelect($init);
          break;

        case 'button':
          $evt = !empty($fdef->onclick) ? $fdef->onclick : '';
          $onClick= empty($evt)? '':" onclick=\"$evt\"";
          $htmlret .= "<tr ><td>&nbsp;</td><td><input type=\"button\" name='$fname' class='{$as_cssclass['button']}' "
           . "{$style}{$addstr}{$onClick} {$addstr} value=\"$prompt\" /></td></tr>\n";
          break;

        case 'toolbar': # toolbar with buttons (or other controls)

          if(isset($fdef->children) && is_array($fdef->children)) foreach($fdef->children as $itemKey=> $item) {
              $elems[] = "<input type='button' class='btn btn-primary' onclick='$item[onclick]' value='$item[label]' />";
          }

          if(count($elems))
              $htmlret .= "<tr><td class='p-2 bounded' colspan='2'>" . implode(' ',$elems) . '</td></tr>';
          break;

        default:
          $init=str_replace("'",'"',$init);
          $htmlret .= "<tr $trAttr><td><input type=\"text\" name=\"$fname\" id=\"$fname\" class='{$as_cssclass['textfield']}$addclasses"
            . " {$fdef->ftype}'{$style}{$addstr} value='$init'></td><td>$prompt {$checkEvt}</td></tr>\n";
          break;

      } #<4>
      # close last "head" block
    } #<3>


    $htmlret .= "</table>";
    if($inGroup) $htmlret .= "</fieldset>";

    if(!empty($onePage->footer)) {
        $htmlret .= $onePage->footer;
    }

    return $htmlret;
  } // DrawFormPage end
} //CPropertySheet def. end

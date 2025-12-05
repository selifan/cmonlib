<?php
/**
* @name basefunc.php - common functions for all php pages on the site,
* @author Alexander Selifonov < alex [at] selifan dot ru >
* @link http://www.selifan.ru
* @Version  2.11.001
* modified  2025-07-14
**/

# if(!defined('JQUERY_VERSION')) define('JQUERY_VERSION', '1.11.3.min');
if(!defined('JQUERY_VERSION')) define('JQUERY_VERSION', '2.2.2.min');
if(!defined('UI_VERSION')) define('UI_VERSION', '1.10.4.custom');
if(!defined('UI_THEME'))   define ('UI_THEME', 'redmond'); # DEFAULT ui used theme
if(!defined('JQGRID_LOCALE')) {
    if (defined('MAINCHARSET') && constant('MAINCHARSET') === 'WINDOWS-1251')
      define('JQGRID_LOCALE', 'ru.1251'); # locale/language used jqgrid file
    else
      define('JQGRID_LOCALE', 'ru'); # locale/language used jqgrid file
}
if(!defined('JSDELIM2')) define('JSDELIM2',chr(12)); # Second Delimiter for AJAX transferred parameters
setlocale (LC_CTYPE, 'ru');

class Cdebs {
    static $outFile = '_debuginfo.log';
    static $_writeBigHeader = true;
    static $handleShutDown = TRUE;
    static $_FirePhp = null; # use FirePHP to send log directly to FireBug client console
    public static function DebugSetOutput($filename='', $bigPref=null) {
        if($filename === FALSE) self::$handleShutDown = FALSE;
        self::$outFile = empty($filename) ? '_debuginfo.log' : $filename;
        self::$_FirePhp = null;

        if($bigPref!==null) self::$_writeBigHeader = $bigPref;
    }
    public static function setFullHeader($par) {
        self::$_writeBigHeader = $par;
    }
}
class HeaderHelper {
    static $replacers = array();
    static $menutype = 0;
    static $menuid= '';

    static $mainheader = 'mainheader.htm';
    static $mainfooter = 'footer.htm';
    static $maincss = 'styles.css';
    static $zettacss = 'styles-zetta.css';
    static $bootstrapIconscss = 'bootstrap-icons/font/bootstrap-icons.css';
    static $bootstrapcss = 'bootstrap.css';
    static $cssinbitrix = 'bitrix.css';
    # static $maincss = 'styles_zetta.css'; # {upd/2023-09-28} пробую цвета Зетты
    static $html_nocache = 0;
    static $html_csscode = '';
    static $appversion = ''; # HeaderHelper::$appversion to include in <script ... src="somescript.js ? VERSION">
    static $mobile_style = false;
    static private $_jslibversions = array();
    static $bHeaderDrawn = false;
    static $bFooterDrawn = false;
    static $_folderJs = 'js/';
    static $header_jscode = array('head'=>'','onready'=>'','onload'=>'', 'footer'=>''); # javascript code to echo in HEADER block
    static $_jsdefs  = array(); # predefined js libraries list, with dependencies
    static $_usedjs   = array(); # to memorize allready included js libs
    static $_js_files = array(); # js files in the <head> section
    static $_js_filesbt = array(); # place theses js in the bottom of HTML
    static $_ui_theme  = '';

    static $_css_files = array();
    static $html_tags = array();
    static $jqmobile_used = false;
    static $_language = 'ru';
    static $sql_filter = array();  // global var to filter SQL pages in

    static private $_addedjs_hash = array();

    public static function AddReplacer($key, $value) {
        self::$replacers[$key] = $value;
    }
    public static function setAppVersion($ver) {
        self::$appversion = (string) $ver;
    }
    public static function useCssModules($csslist) {
        $md = is_string($csslist)? explode(',',$csslist) : (is_array($csslist)? $csslist : array($csslist));
        foreach($md as $cssname) {
            if(!in_array($cssname, self::$_css_files)) self::$_css_files[] = $cssname;
        }
    }
    public static function addJsCode($jscode, $block = 'head') {

        if(is_string($jscode)) {
            $kuda = strtolower($block);
            if (!$kuda) $kuda = 'head';
            $hash = md5($jscode);
            if (in_array($hash, self::$_addedjs_hash)) return; // avoid duplicates
            self::$_addedjs_hash[] = $hash;
            if($kuda ==='onready' || $kuda ==='ready') $kuda='onready';
            elseif($kuda ==='onload' || $kuda ==='load') $kuda='onload';
            elseif($kuda ==='tail' || $kuda ==='end') $kuda='footer';
            self::$header_jscode[$kuda] .= $jscode."\n";
        }
    }

    public static function GetTemplatesFolder() {
        return (defined('FOLDER_TEMPLATES') ? constant('FOLDER_TEMPLATES') : 'templates/');
    }
    public static function setMobileStyle($par=true) {
        self::$mobile_style = $par;
    }
    public static function drawMenuAsPage($menuarr, $menuid) {

    }
    public static function setMenuType($mnutype, $menuId='mainmenu') {
        self::$menutype = $mnutype;
        if($menuId) self::$menuid = $menuId;
        $id = self::$menuid;
        if(self::$menutype==2) {
            $menucode = "jQuery('#$id').menu({ my: 'top left', at: 'top left' } );";
            self::addJsCode($menucode,'ready');
#            WriteDebugInfo('added menu js code:',$menucode);
        }
    }

    public static function loadJsDefenitions() {

        self::$_folderJs = defined('FOLDER_JS') ? constant('FOLDER_JS') : 'js/';
        if(count(self::$_jsdefs)>0) return;
        $cset = defined('MAINCHARSETCL') ? strtoupper(constant('MAINCHARSETCL')) : '';

        self::$_ui_theme = constant('UI_THEME');

        self::$_jslibversions = array(
           'jquery' => ( 'jquery-' . constant('JQUERY_VERSION').'.js' )
          ,'ui'     => ('jquery-ui' . (UI_VERSION ? ('-'.UI_VERSION) : '') . '.min.js')
          ,'uicss'  => ('jquery-ui' . (UI_VERSION ? ('-'.UI_VERSION) : '') . '.css')
          ,'jqgrid' => ('jquery.jqGrid.min.js')
          # ,'jqgrid' => 'jquery.jqGrid.debug.js'
          ,'swfobject' => 'swfobject.js' # Google SWFObject API for inserting Flash into HTML page
          ,'jquerymobile' => 'jquery.mobile-1.3.1.min.js'
          ,'jquerymobilecss' => 'jquery.mobile-1.3.1.min.css'
        );

        $lang = getClientLang();

        $jqgridLocFile = 'grid.locale-' . $lang . '.js';
        $pickLocFile   = ($lang) ? ('i18n/jquery.ui.datepicker-' . $lang .'.js') : false;
        if ( $lang === 'ru' && $cset !== '') {
            $jqgridLocFile = 'grid.locale-' . $lang . '.' . strtolower(MAINCHARSETCL) . '.js';
            $pickLocFile   = 'i18n/jquery.ui.datepicker-' . $lang . '-' . strtolower(MAINCHARSETCL) .'.js';
        }

        self::$_jsdefs = array(
           'jquery'  => array(
              'items'   => array(self::$_folderJs . self::$_jslibversions['jquery'])
           )
          ,'bgiframe'   => array(
              'items'   => array(self::$_folderJs . 'jquery.bgiframe.js')
           )
          ,'positionby' => array(
              'items'   => array(self::$_folderJs . 'jquery.positionBy.js')
             ,'depends' => 'jquery'
           )
          ,'cookie'     => array( # jQuery cookie plugin
              'depends' => 'jquery'
              ,'items'  => array( self::$_folderJs . 'jquery.cookie.js' )
           )
          ,'ui'     => array( # jQuery UI framework
              'depends' => 'jquery'
             ,'items'   => array( self::$_folderJs . self::$_jslibversions['ui'] )
             ,'cssitems'=> array( self::$_folderJs . 'ui-themes/'.self::$_ui_theme .'/'. self::$_jslibversions['uicss'] )
           )
          ,'jqmobile'     => array( # jQuery Mobile UI plugin
              'depends' => 'jquery,ui'
             ,'items'   => array( self::$_folderJs . self::$_jslibversions['jquerymobile'] )
             ,'cssitems'=> array( self::$_folderJs . self::$_jslibversions['jquerymobilecss'] )
           )
          ,'modernizr'  => array( # Modernizr module , http://modernizr.com
             'items'   => array( self::$_folderJs . 'modernizr.js' )
           )
          ,'jquery-migrate' => array( 'depends'=>'jquery', 'items' => array(self::$_folderJs.'jquery-migrate-1.2.1.js')) # jquery-migrate-1.1.1.min.js for production!
          ,'datepicker' => array( # UI.datepicker widget
              'depends' => 'ui'
             ,'items'   => ($pickLocFile ? array( self::$_folderJs . $pickLocFile) : false)
           )
          ,'jdmenu' => array(
              'depends' => 'jquery' # bgiframe deleted (IE6 not supported anymore)! ",positionby"
             ,'items'   => array( self::$_folderJs . 'jquery.jdMenu.js' )
             ,'cssitems'=> array( self::$_folderJs . 'jquery.jdMenu.css' )
           )
          ,'dropdown' => array(
              'depends' => 'jquery'
             ,'items'   => array( self::$_folderJs . 'jquery.dropdown.js' )
             ,'cssitems'=> array( self::$_folderJs . 'jquery.dropdown.css' )
           )
          ,'jqgrid' => array( # jQuery jqgrid plugin, view only
              'depends' => 'jquery,ui'
             ,'items'   => array(
                  self::$_folderJs . $jqgridLocFile,
                  self::$_folderJs . self::$_jslibversions['jqgrid'],
               )
             ,'cssitems'=> array( self::$_folderJs . 'ui.jqgrid.css' )
           )
          ,'jqgrid.search' => array( # jQuery jqgrid Search feature
              'depends' => 'jqgrid'
             ,'cssitems'=> array( self::$_folderJs . 'jquery.searchFilter.css' )
           )
          ,'jqgrid.edit' => array( # jQuery jqgrid editing features
              'depends' => 'jqgrid'
             ,'items'   => array( self::$_folderJs . 'grid.common.js'
                                   ,self::$_folderJs . 'grid.inlinedit.js'
                                   ,self::$_folderJs . 'grid.formedit.js'
                                   ,self::$_folderJs . 'jquery.fmatter.js'
             )
             ,'cssitems'=> array( self::$_folderJs . 'ui.jqgrid.css' )
           )
          ,'swfobject' => array( # swfobject.js
             'items'   => array( self::$_folderJs . self::$_jslibversions['swfobject']
             )
           )
        );
        $inc_file = __DIR__ .'/basefunc.upc.php';
        if(file_exists($inc_file)) {
            $userdefs = @include($inc_file);
            if(is_array($userdefs)) self::$_jsdefs = array_merge(self::$_jsdefs,$userdefs);
        }
    }

    public static function useJsModules($modlist, $mode = FALSE) {
        if(count(self::$_jsdefs) < 1) {
            self::loadJsDefenitions();
        }
        $md = is_string($modlist)? explode(',',$modlist) : (is_array($modlist)? $modlist : array($modlist));
        foreach($md as $jsmodule) {
            $molo = trim(strtolower($jsmodule));
            if(!$molo || isset(self::$_usedjs[$molo])) continue;
            if(isset(self::$_jsdefs[$molo])) { # one of "pre-defined" js libraries
                if (!self::$_jsdefs[$molo]) continue;
                if($molo==='jqmobile') self::$jqmobile_used = TRUE;
                if(!empty(self::$_jsdefs[$molo]['depends'])) {
                    $deplibs = explode(',',self::$_jsdefs[$molo]['depends']);
                    foreach($deplibs as $deplib) { # auto-include js lib that is mandatory for this file
                        if(!isset(self::$_usedjs[$deplib])) self::UseJsModules($deplib, $mode);
                    }
                }
                if(isset(self::$_jsdefs[$molo]['items']))
                  $items = is_array(self::$_jsdefs[$molo]['items']) ? self::$_jsdefs[$molo]['items'] : explode(',',self::$_jsdefs[$molo]['items']);
                  foreach($items as $onejs) {
                    if(!empty(self::$_jsdefs[$molo]['placing']) && self::$_jsdefs[$molo]['placing']==='bottomhtml')
                         self::$_js_filesbt[] = $onejs;
                    else self::$_js_files[] = $onejs;
                }
                if(isset(self::$_jsdefs[$molo]['cssitems']) && is_array(self::$_jsdefs[$molo]['cssitems'])) {
                   foreach(self::$_jsdefs[$molo]['cssitems'] as $onecss)
                   { self::$_css_files[] = $onecss; }
                }
                if(isset(self::$_jsdefs[$molo]['htmltag'])) self::$html_tags[] = self::$_jsdefs[$molo]['htmltag'];
                self::$_usedjs[$molo] = 1;
            }
            else { # all other (non-listed) js files
                $fullname = is_file($jsmodule.'.js') ? ($jsmodule.'.js') : $jsmodule;
                if(!in_array($fullname, self::$_js_files))
                    self::$_js_files[] = $fullname;
            }
        }

    }

}
define ('NORIGHTS', 'У Вас нет полномочий для данной операции');
define ('NOREG', 'Сначала нужно войти в систему');
# define('TBACK','в начало');
# define('TMAIN','на главную');
$self = basename($_SERVER['PHP_SELF']);
# $host = $_SERVER['HTTP_HOST'];

$_HeaderDrawnType = '';
if(!isset($sql_filter))  $sql_filter = array();

if(defined('FILE_MAINCONFIG') && is_file(FILE_MAINCONFIG)) @include_once(FILE_MAINCONFIG);
# if(!defined('FOLDER_TEMPLATES'))  @include_once('base_design.inc');

if(!isset($gotomain)) $gotomain = '';

function AddHeaderJsCode($jscode,$block='head') {
    HeaderHelper::addJsCode($jscode,$block);
}
# short alias
function addJsCode($jscode, $block = 'head') {
    HeaderHelper::addJsCode($jscode,$block);
}
function HTML_Header($PGtitle = '', $ShowTitle = 1, $pgwidth='100%', $rd_url='', $rd_tm=0, $nodraw=0)
{ // draw html page designed header, w90 defines percentage width (table tag)
    global $smallheader,  $htmheader, $TitleBlock, $_HeaderDrawnType, $js_script_url;
    if (HeaderHelper::$bHeaderDrawn) return;
    if( (empty($pgwidth)) || ($pgwidth=='')) $pgwidth='100%';
    if($ShowTitle<0) { # minimal header
        $hdbody= @file_get_contents(HeaderHelper::GetTemplatesFolder().'smallheader.htm');
        $_HeaderDrawnType = 'mini';
    }
    else {
        $_HeaderDrawnType = 'normal';
        if(empty($htmheader)) {
          $tpl_name = HeaderHelper::$mainheader;
          if(class_exists('Mobile_Detect')) { # use "mobile" template if mobile client detected
             $detector = new Mobile_Detect();
             if ($detector->isMobile() && file_exists(HeaderHelper::GetTemplatesFolder().'mainheader-mobile.htm')) $tpl_name = 'mainheader-mobile.htm';
          }
          $hdbody = @file_get_contents(HeaderHelper::GetTemplatesFolder() . $tpl_name);
        }
        else $hdbody = $htmheader.$TitleBlock;
    }
    $brplc = MakeHtmlReplacer($PGtitle, $ShowTitle, $pgwidth, $rd_url, $rd_tm, $nodraw);
    if(mb_strpos($hdbody,'<!-- %AUTH_BLOCK% -->')!==false && function_exists('Auth_InlineForm')) {
       $brplc['<!-- %AUTH_BLOCK% -->']=Auth_InlineForm();
    }
    if(mb_strpos($hdbody,'<!-- %MENUBLOCK% -->')!==false && function_exists('HtmlBuildMenu')) {
       $brplc['<!-- %MENUBLOCK% -->'] = HtmlBuildMenu(1);
    }

    if(mb_strpos($hdbody,'<!-- %LINKS% -->')!==false) {
        if (class_exists('appEnv') && isset(appEnv::$mainmenuHtml)) {
            $brplc['<!-- %LINKS% -->'] = appEnv::$mainmenuHtml;
        }
        elseif (function_exists('HtmlBuildLinks')) $brplc['<!-- %LINKS% -->'] = HtmlBuildLinks(1);
    }
    if(count(HeaderHelper::$replacers)) $brplc = array_merge($brplc, HeaderHelper::$replacers);
    $hdbody = str_ireplace(array_keys($brplc),array_values($brplc),$hdbody);

    if(function_exists('RightHeaderBlock')) {
       $hdblk = RightHeaderBlock();
       $hdbody = str_replace('<!-- RIGHT-HEADER-BLOCK -->',$hdblk,$hdbody);
    }
    if(function_exists('BuildMainLinks')) BuildMainLinks($hdbody);
    HeaderHelper::$bHeaderDrawn = true;
#    WriteDebugInfo('head body: ',$hdbody);
    if(empty($nodraw)) {
       echo $hdbody;
    }
    else return $hdbody; // header будет выведен самостоятельно
} // end HTML_Header()

function MakeHtmlReplacer($PGtitle = '', $ShowTitle = 1, $pgwidth='100%', $rd_url='', $rd_tm=0, $nodraw=0) {
  global $htmheader, $TitleBlock, $bHeaderDrawn, $js_script_url,$sitecfg, $logofile;
  $ret = array();
  $cset = isset($sitecfg['charset'])? $sitecfg['charset']: (defined('MAINCHARSETCL')? constant('MAINCHARSETCL') :
     (defined('DEFAULT_CHARSET') ? DEFAULT_CHARSET:'WINDOWS-1251'));
  $logoimg = defined('LOGOFILE')? LOGOFILE : 'img/logo.png';
  $metattl = empty($GLOBALS['metatitle'])? $PGtitle : $GLOBALS['metatitle'];
  $imgpath = defined('IMGPATH')? IMGPATH : '';
  $vers = HeaderHelper::$appversion ? 'v='.HeaderHelper::$appversion : '';
  $ret = array('%TITLE%'=>$PGtitle,'%METATITLE%'=>$metattl, '%charset%'=>$cset,'%WIDTH%'=>$pgwidth,'%IMGPATH%'=>$imgpath,
     '<!-- %ONLOAD% -->'=>'','<!-- %REDIRECT% -->'=>'','<!-- %CSS% -->'=>'','%LOGOFILE%'=>$logoimg,
     '<!-- %CACHE% -->'=>'');
  if(!empty($logofile)) $ret['%LOGOFILE%']=$logofile;
  if (empty($ShowTitle)) $ret['%TITLE%']='';

  if(count(HeaderHelper::$_css_files)) {
        foreach(HeaderHelper::$_css_files as $css) {
            $myVersion = findVersionForUri($css);
            if(empty($myVersion)) $myVersion = $vers;
            $ret['<!-- %CSS% -->'] .= "<link rel=\"stylesheet\" href=\"$css"
               .($myVersion ? (mb_strpos($css,'?')===false?'?':'&').$myVersion : '')  ."\" type=\"text/css\" />\n";
        }
  }
  if(HeaderHelper::$html_csscode)  $ret['<!-- %CSS% -->'] .= "<style type=\"text/css\">\n" . HeaderHelper::$html_csscode . "\n</style>\n";

  if(!empty($rd_url)) { // output redirect directive, if redirect url passed
    if(empty($rd_tm)) $rd_tm = 1; // redirect pause time
    $ret['<!-- %REDIRECT% -->'] ="<meta http-equiv=\"refresh\" content=\"$rd_tm;url=$rd_url\">\n";
  }
#  if(isset($body_onload)) $ret['<!-- %ONLOAD% -->']=" onLoad='$body_onload'";

  if(count(HeaderHelper::$_js_files)) {
      $jsall = '';
      foreach(HeaderHelper::$_js_files as $jsfile) {
          $myVersion = findVersionForUri($jsfile);
          if(empty($myVersion)) $myVersion = $vers;
          $jsall .= "<script type=\"text/javascript\" src=\"$jsfile"
               .($myVersion ? (mb_strpos($jsfile,'?')===false?'?':'&').$myVersion : '')  ."\"></script>\n";
      }
    }
    $fulljs = HeaderHelper::$header_jscode['head'];
    if(!empty(HeaderHelper::$header_jscode['onready'])) {
        $fulljs .= "jQuery(document).ready(function() { ".HeaderHelper::$header_jscode['onready']." });\n";
    }
    if(!empty(HeaderHelper::$header_jscode['onload']))
        $fulljs .= "jQuery(window).on('load', function() { ".HeaderHelper::$header_jscode['onload']." });\n";

    if(!empty($fulljs)) $jsall .= "\r<script type=\"text/javascript\">\n{$fulljs}\n</script>\n";
    if(!empty($jsall)) $ret['<!-- %JAVASCRIPT% -->'] = $jsall;

  if(!empty(HeaderHelper::$html_nocache))
    $ret['<!-- %CACHE% -->'] = '<meta http-equiv="Expires" content="0"><meta http-equiv=pragma content=no-cache>';

  return $ret;
}
# if js/css link has a form plugins/<module>/..., returns "<module>"
function findVersionForUri($strUri) {
    $items = explode('/',$strUri);
    foreach($items as $no=>$elem) {
        if($elem ==='plugins' && !empty($items[$no+1])) {
            $version = WebApp::getModuleVersion($items[$no+1]);
            return (empty($version) ? '' : "v=$version");
        }
    }
    return '';
}
/**
*  draws mimified HTML page header
*
* @param mixed $stitle page title
* @param mixed $tovar if non empty, return HTML code instead of echoing
*/
function HtmlHeader2($stitle, $tovar=false){
  global $smallheader;
  $smallheader = @file_get_contents(HeaderHelper::GetTemplatesFolder() . 'smallheader.htm');

  if (empty($smallheader)) $smallheader = '<html><body>';

  $brplc = MakeHtmlReplacer($PGtitle, $ShowTitle, $pgwidth, $rd_url, $rd_tm, $nodraw);

  $hdbody = str_replace(array_keys($brplc),array_values($brplc),$smallheader);
  if(empty($tovar)) echo $hdbody;
  else return $hdbody;
} // HtmlHeader2() end

function HTML_Footer($BackButton = false, $HomeButton = false, $nodraw=0)
{ // drawing page footer, including </body></html>
  global $back_url, $as_iface; // User theme (for skin selection ability)
  global $HtmFooter;
  if(HeaderHelper::$bFooterDrawn) return;
  if(empty($HtmFooter)) $HtmFooter = @file_get_contents(HeaderHelper::GetTemplatesFolder() . HeaderHelper::$mainfooter);
  if(empty($HtmFooter)) $HtmFooter = '</body></html>';
  if(empty($back_url)) $back_url = './';
  if(!empty($BackButton)) {
      if(!is_string($BackButton)) $BackButton = isset($as_iface['prompt-back']) ? $as_iface['prompt-back'] : 'back';
      $baktxt = "<a href=\"$back_url\">$BackButton</a>";
  } else $baktxt='';

  if(!empty($HomeButton)) {
      if(!is_string($HomeButton)) $HomeButton = isset($as_iface['prompt-homepage']) ? $as_iface['prompt-homepage'] : 'Home';
      $hometxt = "<a href=\"/\">$HomeButton</a>";
  } else $hometxt='';

  $appver = defined('APPVERSION')? APPVERSION : '';
  if(class_exists('AppEnv')) $copyright = AppEnv::getConfigValue('comp_title');
  else
    $copyright = defined('APPCOPYRIGHT')? APPCOPYRIGHT : 'selifan.ru';
  $repl = array('<!-- %BACK% -->'=>$baktxt,'<!-- %HOME% -->'=>$hometxt,'%appversion%'=>$appver,'%copyright%'=>$copyright);
  if(function_exists('DrawBottomLinks')) $repl['<!--FOOTER_LINKS-->']=DrawBottomLinks();
  $ftcode = str_ireplace(array_keys($repl), array_values($repl),$HtmFooter);
  HeaderHelper::$bFooterDrawn = true;

  # footer (deferred) js code if exist
  if(HeaderHelper::$header_jscode['footer'] !=='') $ftcode .= "<script type='text/javascript'>\n".HeaderHelper::$header_jscode['footer']."</script>\n";

  if(empty($nodraw)) echo $ftcode;
  else return $ftcode;
} // end HTML_Footer()

function SubmitBtn($name='submit', $txt='SUBMIT', $type='SUBMIT', $adv='')
{ // returns HTML-code for colored "submit" button
  return "<input type=$type class=\"btn btn-primary\" name='$name' $adv value=\"$txt\" />";
//  return "<input type=$type style=\"background:".GetColorCode(3).";\" name=$name value=\"$txt\">";
}

function ErrorQuit($msg = 'ERROR !', $autoclose=false)
{
    global $back_url, $bHeaderDrawn;
    if(isAjaxCall()) exit(EncodeResponseData($msg));
    $endcode = "setTimeout('window.close()',".($autoclose*1000).')';
    if (empty($bHeaderDrawn)) {
//        AddHeaderJsCode($endcode,'ready');
        HTML_Header('ОШИБКА !',($autoclose? -1:1));
    }
    echo "<br><br><center><font color=RED size=\"+2\"><b>$msg</b></font></center>";
    if($autoclose) echo "<script type='text/javascript'>$endcode</script>\n";
    $back_url = 'javascript:window.history.back()';
    if($autoclose) exit('<body></html>');
    else HTML_Footer(false,false);
    exit();
    // send email to admin if neded !
}
# forgiving convert string to 'YYYY-MM-DD' ISO date
function dateRepair($dt) {
  if (empty($dt)) return '';
  $darr = preg_split("/[\s,-\/\.\:]+/",$dt);
  if($darr[0]<32) { # string was "d[.m[.Y]]"
      if(count($darr)<2) $darr[1] = date('m');
      if(count($darr)<3) $darr[2] = date('Y');
      $darr = array($darr[2],$darr[1],$darr[0]);
  }
  else {
      if(count($darr)<2) $darr[1] = date('m');
      if(count($darr)<3) $darr[2] = 1;
      if($darr[0]<=100) $darr[0] += 2000;
  }

  return date('Y-m-d', mktime(0,0,0,$darr[1],$darr[2],$darr[0]));
}
function to_date($dt, $tm=0, $fmt='') # $tm=1 - include time "hh:mi", $tm>=2 - with seconds: "... hh:mi:ss"
{ // returns "YYYY-MM-DD" from "DD.MM.YYYY [HH:MI:SS]"
  if(intval($dt)<=0) return '0000-00-00';
  $darr = preg_split("/[\s,-\/\.\:]+/",$dt);
  if(count($darr)>=3) {
     $god = ($darr[0]<=31) ? $darr[2] : $darr[0];
     $mes = $darr[1];
     $den = ($darr[0]<=31) ? $darr[0] : $darr[2];
     $hhmm = (count($darr)>3 ? $darr[3] : '00') . ':' .(count($darr)>4 ? $darr[4] : '00')
      .($tm>1 && count($darr)>5 ? (':'.$darr[5]) : '');
     if($god<15) $god+=2000; # correct "2-digit" year
     elseif($god<99) $god+=1900;
  }
  else return '';

  if($fmt=='ibase') // mm/dd/yyyy
    $ret =  "$mes/$den/$god";
  else $ret = "$god-$mes-$den";
  if($tm)  $ret .= ' '.$hhmm; # yyyy-mm-gg hh:mi
  return $ret;
}

function to_char($dt, $withtime=0, $fmt='', $to_fmt='d.m.Y')
{ // make DD.MM.YYYY string from source date
  if(empty($dt) || intval($dt)==0) return '';
  $dttmp = explode(' ',$dt);
  $dtarr = preg_split("/[-\.\/, ]+/", $dttmp[0]); # $dtarr = [YYYY,MM,DD,[Hour,Nim,Sec]]
  $tmarr = isset($dttmp[1])? preg_split('/[:.]/',$dttmp[1]) : array('00','00','00');
  if($fmt==='ibase') { //from interbase MM/DD/YYYY
    $dtarr = array($dtarr[2],$dtarr[0],$dtarr[1]);
  }
  elseif($dtarr[0]<=31 && $dtarr[2]>=1000) {
      $dtarr = array($dtarr[2],$dtarr[1],$dtarr[0]);
  }
  $ret = str_replace(array('Y','m','d'),$dtarr,$to_fmt);
  if(($withtime) && isset($tmarr[1])) {
      if($withtime===2 && $tmarr[0]+$tmarr[1]==0) return $ret; # avoid empty 00:00
      $ret .= ' '.$tmarr[0].':'.$tmarr[1];
  }
#  WriteDebugInfo("to_char($dt,[$withtime], [$fmt]=$ret");
  return $ret ;
}
function CompareDatesDMY($d1,$d2) {
  $ds1=preg_split("/[-\.\/, ]+/", $d1);
  $ds2=preg_split("/[-\.\/, ]+/", $d2);
  $dcmp1 = $ds1[2]*1000 + $ds1[1]*100 + $ds1[0];
  $dcmp2 = $ds2[2]*1000 + $ds2[1]*100 + $ds2[0];
  if($dcmp1 < $dcmp2) return -1;
  if($dcmp1 == $dcmp2) return 0;
  return 1;
}
function sql_string($strk, $fordb=1, $keepnl=false, $striphtml=false)
{ // prepare string so it can be put into mySQL field
  if(is_array($strk)) { echo "sql_string: array passed: "; var_dump($strk); return $strk; } // debug
  $rez = $strk;
  if(empty($keepnl)) {
    $rez = str_replace("\n", '<br />', $rez);
    $rez = str_replace("\r", '', $rez);
  }
  if($striphtml) $rez = strip_tags($rez);
  if($fordb)
  {
//    $rez = str_replace("'", '"', $rez);
    if(function_exists('mysql_real_escape_string'))
        $rez = mysql_real_escape_string($rez);
    elseif(is_callable('PDO::quote'))
        $rez = PDO::quote($rez);
    else $rez = str_replace("'", '"', $rez);
  }
  return $rez;
}

function unsql_string($strk)
{ // restore original string so I can edit it again
 $rez = str_replace('<br>',"\n", $strk);
 if(ini_get('magic_quotes_gpc'))
 { // надо вычистить лишние слеши из SQL!
   $rez = stripslashes($rez);
 }
 return $rez;
}
/*
function compose_filter($flarray)
{ // make plane filter string from [array]
   $fflt = '1';
    if(!is_array($flarray))
      $fflt = $flarray;
    else
      if(!empty($flarray))
      {
        reset($flarray);
        while (list ($key, $val) = each ($flarray)) {
          if(!empty($val)) $fflt .= " AND $val";
        } // сочинил полную строку фильтра
      }
    return $fflt;
} // compose_filter end
function ShowAuthor($authorname, $email='')
{
  $ret = $authorname;
  if(!empty($email))
    $ret = "<a href=\"mailto://$email\">$authorname</a>";
  return $ret;
}
*/

function Translit($src, $moda=0, $strip_quotes=false, $utfsrc=false) // 0 - from Rus to Lat, 1- LAT->Rus
{ // converts all russian letters to closest latin chars
  $srcCset = mb_detect_encoding($src, 'ASCII,UTF-8,WINDOWS-1251');
  # WriteDebugInfo("detectd carset: ", $realCset);
  if ($srcCset === 'ACSII' && empty($moda)) return $src;
  if ($srcCset !== 'UTF-8') {
        $src = iconv($srcCset, 'UTF-8', $src);
  }

  $s_rus = array('А','а','Б','б','В','в','Г','г','Д','д','Е','е','Ё','ё','Ж','ж','З','з',
    'И','и','Й','й','К','к','Л','л','М','м','Н','н', 'О','о','П','п','Р','р','С','с',
    'Т','т','У','у', 'Ф','ф','Х','х','Ц','ц','Ч','ч','Ш','ш','Щ','щ','Ъ','ъ','Ы','ы',
    'Ь','ь','Э','э','Ю','ю','Я','я');

#  mb_convert_variables('WINDOWS-1251','UTF-8',$s_rus);
  $s_enu = array('A','a','B','b','V','v','G','g','D','d','E','e','YO','yo','ZH','zh','Z','z',
    'I','i','I','i','K','k','L','l','M','m','N','n','O','o','P','p','R','r','S','s',
    'T','t','U','u','F','f','H','h','TS','ts','CH','ch','SH','sh','SCH','sch',"'Y","'y",'YI','yi',
    "_","_","'E","'e",'YU','yu','YA','ya');
  if($moda == 0 || empty($moda)) {
      $ret = str_replace($s_rus, $s_enu, $src);
      if($strip_quotes) $ret = str_replace(array('"',"'"),'',$ret);
  }
  else {
    $ret = str_replace($s_enu, $s_rus, $src);
    if ($srcCset !='UTF-8')
        $ret = iconv('UTF-8',$srcCset, $ret); # back to original char-set
  }

  return $ret;
}

function encode_rtf($srcstring, $magic_auto=true)
{ // encodes text to RTF-compatible (all non-latin chars will be {\'xx}, \t-> \tab...
//  $s_src = array("\t", '"',  "'");
//  $s_to  = array('\\tab ',"\'93",'\lquote ');
  $slen = strlen($srcstring);
  $sout = "";
  for($i=0; $i < $slen; $i++)
  {
    $schr = substr($srcstring,$i,1);
    $dchr = ord($schr);
    if($dchr>127)
    {
       $schr = "\\"."'".dechex($dchr);
    }
    elseif($dchr == 9) $schr = '\\tab ';
    elseif($schr == '"') $schr = "\\'94";
    # (get_magic_quotes_gpc() && ($magic_auto))?  "'94": "\\'94";
    # get_magic_quotes_gpc() dont use this function. It has been removed in php8
    elseif($schr == "'") $schr = "\\lquote ";
    elseif($schr == "\n") $schr = "\\par ";
    $sout .= $schr;
  }
//  $sout = str_replace($s_src, $s_to, $sout);
  return $sout;
}


function GetFileExtension($fname)
{ // returns file extension (lower case) : "path/image.jpg" -> "jpg"
  if(empty($fname)) return '';
  $path_info = pathinfo($fname);
  return mb_strtolower($path_info['extension']);
}
function GetMaxUploadSize() {
  $cfgval = ini_get('upload_max_filesize');
  $ret = intval($cfgval);
  if(mb_strpos($cfgval,'M')!==false) $ret *= 1048576;
  elseif(mb_strpos($cfgval,'K')!==false) $ret *= 1024;
  return $ret;
}

function file_exists_incpath ($file)
{
    $paths = explode(PATH_SEPARATOR, get_include_path());

    foreach ($paths as $path) {
        // Formulate the absolute path
        $fullpath = $path . DIRECTORY_SEPARATOR . $file;

        // Check it
        if (file_exists($fullpath)) {
            return true;
        }
    }

    return false;
}

function GrabEmailFrom($strk)
{
  $ar = explode(' ',$strk);
  if(count($ar)>0)
  {
     for($i=0; $i < count($ar); $i++)
       if(mb_strpos($ar[$i], '@')>0)
          return $ar[$i];
  }
  return '';
}

// GetArrayFromString: converts "1=text1;2=text2;..." or "@FuncName" to array or '!filename.ext'
function GetArrayFromString($parm, $passed='',$pdelim=';', $vdelim='=', $assoc=false)
{
    $lar = array();
    if(is_array($parm)) return $parm;
    if('@' == substr($parm,0,1)) {
    $addon = substr($parm,1);
    if(is_callable($addon))
      $lar = call_user_func($addon, $passed);
    else $lar = array(0, $addon.' - функция не найдена !');
  }
  elseif('!'==substr($parm,0,1)) {
     $lar = array();
     $comment = ($passed!=='')? $passed : null;
     $flname = substr($parm,1);
     if(is_readable($flname) && ($ffh=@fopen($flname,'r'))>0) { #<4>
       while(!feof($ffh)) {
         $strk =trim(fgets($ffh,4096));
         if($comment!==null && substr($strk,0,1)===$comment) continue; # skip commented lines
         if($strk!='') {
             $dta = explode('|', $strk);
             if($assoc) {
                 $key = array_shift($dta);
                 $lar[$key] = count($dta)>1 ? $dta : (count($dta)? $dta[0] : $key);
             }
             else $lar[] = $dta; #
         }
       }
       fclose($ffh);
     } #<4>
  }
  else { // <4> список в виде VALUE=Text;VALUE=text - to parse !
//               $sbody .="<tr><td>$addon</tr>"; // debug
     $pattern = '/['.$pdelim.']/';
     $m_ar = preg_split($pattern, $parm, -1, PREG_SPLIT_NO_EMPTY);
     foreach($m_ar as $k=>$mitem) {
         $tmp = explode($vdelim,$mitem);
         if(empty($tmp[1])) $tmp[1] = $tmp[0];
         if($assoc) $lar[$tmp[0]] = $tmp[1]; else $lar[] = $tmp;
     }
  } //<4>
  return $lar;
}
function IsEmptyDate($dt)
{
   if($dt=='' || $dt=='..' || $dt=='  .  .    ') return true;
   if(intval($dt) == 0) return true; // 00-00-0000, 00.00.0000,...
   return false;
}
/**
* @desc safe_string($src, $optimize) prepares string for inserting into DB by SQL query
* @param $optimize = 0 = no trim, no HTML spec.chars converting, 1=trim, 2=trim & convert spec.chars (<>&'"...)
*/
function safe_string($strk, $optimize=0)
{
   $ret = ($optimize>0) ? trim($strk) : $strk;
   $mquotes = ini_get('magic_quotes_gps');
   if(empty($mquotes))
     $ret = str_replace(array("'","\\"),array("\\'","\\\\"),$strk); # addslashes добавляет лишний "\" к дв.кавычке - нам это не надо
   if($optimize>1) $ret = htmlspecialchars($ret);
   return $ret;
}

/**
* makes date as a result for source date plus $years, $months, $days
*
* @param string $srcdate in one of format: "d.m.Y" or "Y-m-d"
* @param integer $years
* @param integer $months
* @param integer $days
* @return string changed date in the same format as source date
*/
function AddToDate($srcdate, $years=0,$months=0,$days=0) {
  $dsplt = preg_split("/[.\/-]/",$srcdate);
  if(count($dsplt)<3 || intval($dsplt[0])<1) return '0';
  $nmon = $dsplt[1];
  if(intval($srcdate)>=1000) {
    $fmt = 0; # database (MySQL) format was passed
    $nday = $dsplt[2];
    $nyear = $dsplt[0];
  }
  else {
    $fmt = 1;
    $nday = $dsplt[0];
    $nyear = $dsplt[2];
  }
  if(!is_numeric($nyear) || !is_numeric($years))
      exit(1 . AjaxResponse::showError("AddToDate: wrong years:<pre>srcdate:<br>".print_r($srcdate,1). "<br>years:$years<pre>".
        print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,3),1). '</pre>'));
  $finyear = $nyear+$years;
  $finmonth = $nmon + $months;
  if($finmonth>12){ while($finmonth>12) { $finyear++; $finmonth-=12; } }
  elseif($finmonth<=0) {
      while($finmonth<=0) { $finyear--; $finmonth+=12; }
  }
  $molen = array(0,31,($finyear%4 ? 28:29),31,30,31,30,31,31,30,31,30,31);
  $mlen = $molen[$finmonth];
  $nday = min(intval($nday), $molen[$finmonth]);

  $finday = $nday+$days;
  if($finday>0) {
    $mlen = $molen[$finmonth];
    while($finday>$mlen) {
      $finday-= $mlen;
      $finmonth = ($finmonth==12)? 1 : ($finmonth+1);
      $finyear += ($finmonth==1)?1:0;
      $molen = array(0,31,($finyear%4 ? 28:29),31,30,31,30,31,31,30,31,30,31);
      $mlen = $molen[$finmonth];
    }
  }
  else { #day <=0
    while($finday<=0) {
      $finmonth = ($finmonth==1)? 12: ($finmonth-1);
      $finyear -= ($finmonth==12)? 1:0;
      $molen = array(0,31,($finyear%4 ? 28:29),31,30,31,30,31,31,30,31,30,31);
      $mlen = $molen[$finmonth];
      $finday+= $mlen;
    }
  }
  $finday = min($molen[$finmonth],$finday); #
  $finday = ($finday<10)? "0$finday" : $finday;
  $finmonth = ($finmonth<10)? "0$finmonth" : $finmonth;
  return ($fmt ? "$finday.$finmonth.$finyear" : "$finyear-$finmonth-$finday");
}

/**
* new diffDays version (uses DateTime)
* @param string $dateFrom date "from", in 'yyyy-mm-dd' or 'dd.mm.yyyy' format
* @param string $dateTo date "to", in 'yyyy-mm-dd' or 'dd.mm.yyyy' format
* @param mixed $years orders returning full years and days separately (array returned)
* @return full days amount OR array [full_years,months,days] if $years parameter is 1/true
*/
function diffDays2($datefrom, $dateto,$years=false) {
  $dt1 = preg_split("/[-\.\/, ]+/", $datefrom);
  $dt2 = preg_split("/[-\.\/, ]+/", $dateto);
  if(sizeof($dt1)<3 or sizeof($dt2)<3) return false; # wrong formatted dates
  if(floatval($dt1[2])>=1000) { # dd.mm.yyyy passed, convert array to y,m,d
    $dt1 = array($dt1[2],$dt1[1],$dt1[0]);
  }
  if(floatval($dt2[2])>=1000) {
    $dt2 = array($dt2[2],$dt2[1],$dt2[0]);
  }
  $sign = 1;
  if($dt1[0]*10000+$dt1[1]*100+$dt1[2] > $dt2[0]*10000+$dt2[1]*100+$dt2[2]) {
      $sign = -1;
      list($dt1, $dt2) = array($dt2, $dt1); # swap
  }
  $d1 = new DateTime("$dt1[0]-$dt1[1]-$dt1[2]");
  $d2 = new DateTime("$dt2[0]-$dt2[1]-$dt2[2]");
  $diff = $d1->diff($d2);
  $myears = $diff->format('%r%y');
  $mmon = $diff->format('%r%m');
  $ddays = $diff->format('%r%d');
  $mdays = $diff->format('%r%a');
  return ($years ? array($sign*$myears,$sign*$mmon,$sign*$ddays) : $sign*$mdays);
}

/**
* evaluates days [full years and days] between two dates
* @param string $dateFrom date "from", in 'yyyy-mm-dd' or 'dd.mm.yyyy' format
* @param string $dateTo date "to", in 'yyyy-mm-dd' or 'dd.mm.yyyy' format
* @param mixed $years orders returning full years and days separately (array returned)
* @return full days amount OR array [full_years,days] if $years parameter is 1/true
* Valid only on PHP x64, otherwise dates after jan.2038 fail. Check PHP_INT_SIZE === 8 !
*/
function DiffDays($datefrom, $dateto,$years=false) {
  if(is_numeric($datefrom) && strlen($datefrom)==8) { # yyyymmdd or ddmmyyyy
      if(substr($datefrom,-4)>=1900 && substr($datefrom,0,2)<=31) # ddmmyyyy
        $datefrom = substr($datefrom,0,2) . '.' . substr($datefrom,2,2) . '.' .substr($datefrom,4);
      elseif(substr($datefrom,0,4)>=1900 && substr($datefrom,-2)<=31) # yyyymmdd
        $datefrom = substr($datefrom,0,4) . '-' . substr($datefrom,4,2) . '-' .substr($datefrom,6,2);
  }
  $dt1 = preg_split("/[-\.\/, ]+/", (string)$datefrom);
  $dt2 = preg_split("/[-\.\/, ]+/", (string)$dateto);
  if(sizeof($dt1)<3 or sizeof($dt2)<3) return false; # wrong formatted dates
  if(floatval($dt1[2])>=1000) { # dd.mm.yyyy passed, convert array to y,m,d
    $dt1 = array($dt1[2],$dt1[1],$dt1[0]);
  }
  if(floatval($dt2[2])>=1000) {
    $dt2 = array($dt2[2],$dt2[1],$dt2[0]);
  }
  $sign = 1;
  if($dt1[0]*10000+$dt1[1]*100+$dt1[2] > $dt2[0]*10000+$dt2[1]*100+$dt2[2]) {
      $sign = -1;
      list($dt1, $dt2) = array($dt2, $dt1); # swap
  }
  $fullyears = floor($dt2[0]-$dt1[0]);
  if($dt1[1]*100+$dt1[2] > $dt2[1]*100+$dt2[2]) $fullyears--; # day no in dt1 is greater than in dt2
  if($years) $dt1[0]+=$fullyears;
  $days1 = strtotime(implode('-',$dt1));
  $days2 = strtotime(implode('-',$dt2));
  $difdays = round(($days2 - $days1) / 86400);
  #  echo "$datefrom - $dateto : years=[$fullyears], days = [$difdays]"; # debug
  if ($years !==TRUE && $years >= 2) { # return float value = exact years (5.5 for 5 years + 6 months)
      $decPart = round($difdays / 365, intval($years));
      return $sign * ($fullyears + $decPart);
  }
  return ($years)? array($sign*$fullyears,$sign*$difdays) : ($sign*$difdays);
}

/**
* Builds &lt;option&gt;...&lt;/option&gt; html block for &lt;select&gt; form element, from passed data array
*
* @param array $options - data array (two-dimensional or associative), if first value in row is 'group', it starts &lt;optgroup&gt; element
* @param mixed $initval - value that should be "selected" initially
* @param mixed $tovar -if not empty, result will be returned from function, otherwise immediately echoed
* @return mixed string or nothing
*/
function DrawSelectOptions($options,$initval=false,$tovar=false) {
  if(!is_array($options) || count($options)<1) return '';
  $ret = '';
  $ingroup = false;
  $cset = defined('MAINCHARSET') ? MAINCHARSET : 'WINDOWS-1251';
  foreach($options as $key=>$val) {
    if(is_string($val)) {
      $vval = $key;
      $vtext = $val;
    }
    else {
        if(is_array($val) && count($val)>1) {
            $vkeys = array_keys($val);
            $vval = $val[$vkeys[0]];
            $vtext = $val[$vkeys[1]];
        }
        else {
          $vtext = is_array($val) ? $val[0] : $val;
          $vval = (is_numeric($key) ? $vtext : $key);
        }
    }
#    WriteDebugInfo("key= $key,val:", $val," vval = $vval, vtext=$vtext");
    $vtext = strtr($vtext, array('<'=>'&lt;', '>'=>'&gt;','"'=>"&quot;"));
    # $vtext = str_replace(array('<','>'),'',$vtext); # ex. StripLtGt
    if($vval==='group' || $vval==='<group>' || $vval==='<' ) {
        if($ingroup) $ret .='</optgroup>';
        $htmltext="<optgroup label=\"$vtext\">";
        $ingroup = true;
    }
    else {
        $sel = ($initval!==false && "$initval"==="$vval")? ' selected="selected"':'';
        $options = isset($val['options']) ? $val['options'] : array();
        $tattr = '';
        if(is_array($options) && count($options)) foreach($options as $atkey=>$attr) {
            $tattr .= ' '." $atkey=\"$attr\"";
        }
        $htmltext = "<option value='$vval'{$sel}{$tattr}>$vtext</option>\n";
    }
    $ret .= $htmltext;
  }
  if($ingroup) $ret .='</optgroup>';
  if($tovar) return $ret;
  echo $ret;
}

function isdigit($strg) {
  $first = mb_substr($strg,0,1);
  return (mb_strpos('0123456789',$first)!==false);
}
# search array for a key
function FindInArray($arrvar, $key) {
  if(!is_array($arrvar)) return false;
  foreach($arrvar as $akey=>$aval) {
#    echo "<br>FindInArray array element: $akey=";var_dump($aval); #debug
    $curkey = (is_array($aval)&& count($aval)>1)? $aval[0]:$akey;
    $curval = (is_array($aval)&& count($aval)>1)? $aval[1]:$aval;
    if($key==$curkey) return $curval;
  }
  return false;
}
/**
* Analog to in_array, but searches in sub-arrays if array is multi-dimensional
*
* @param mixed $value value to search for
* @param mixed $array array to search in, can be multi-dimensional
*/
function as_in_array($value, $arr) {
    if (!is_array($arr) || !is_scalar($value)) return FALSE;
    foreach ($arr as $key=>$val) {
        if (is_scalar($val) && $val == $value) {
            return TRUE;
        }
        if (is_array($val)) {
            $t_find = as_in_array($value,$val);
            if ($t_find) {
                return TRUE;
            }
        }
    }
    return FALSE;
}

function TextToPrint($txt='') {
  if(!is_string($txt)) return $txt;
  $ret = $txt;
  if(mb_strpos($ret,"\r",1)) $ret = str_replace("\r",'<br>', $ret);
  elseif(mb_strpos($ret,"\n",1)) $ret = str_replace("\n",'<br>', $ret); //Unix systems
  if(ini_get('magic_quotes_gpc')) {
    $ret = stripslashes($ret);
  }
  return $ret;
}
function TextToEdit($txt='')
{
 $ret = $txt;
 if(ini_get('magic_quotes_gpc'))
 { $ret = stripslashes($ret); }
 // convert 'smiles' macros to <IMG> tags...
 return $ret;
}
/**
* @desc prints out all passed params to the log file _debuginfo.log
*/
function WriteDebugInfo() {
  $argv = func_get_args();
  $numargs = func_num_args();
  if($numargs==0 ) {
      if(is_file(Cdebs::$outFile)) @unlink(Cdebs::$outFile);
      return;
  }

  $fh = @fopen(Cdebs::$outFile,'a');
  if(!$fh && is_file(Cdebs::$outFile)) { # try to change file rights and retry
        @chmod(Cdebs::$outFile,0666);
        $fh = @fopen(Cdebs::$outFile,'a');
  }
  if(!$fh) {
      if (!isset($_SERVER['REMOTE_ADDR'])) echo Cdebs::$outFile . " - file writing denied!\r\n";
      return;
  }
  $etext = '';
  $runinfo = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
  for($ilev=0;$ilev<count($runinfo);$ilev++) {
      if(isset($runinfo[$ilev]['file'])) break;
  }
  $runfile = $runinfo[$ilev]['file'] ?? '';
  if($runfile !=='' && ($curDir = getcwd())) {
      $runfile = str_ireplace($curDir, '',$runfile);
  }
  if($runfile)
    $pref = $runfile.'['.$runinfo[$ilev]['line'].']';
  else $pref = print_r($runinfo[$ilev],1);
/*  elseif (isset($runinfo[1]['file']))
    $pref = $runinfo[1]['file'].'['.$runinfo[1]['line'].']';
*/
  $strk = '';
  if($fh) {
    for($kk=0;$kk<count($argv);$kk++) {
      if(is_scalar($argv[$kk])) $strk .= ' '.$argv[$kk];
      else $strk .=  print_r($argv[$kk],true);
    }
    # $strk = str_replace("\n",' ',$strk);
    $header = '';
    if (Cdebs::$_writeBigHeader === 't') {
        $header = date('H:i:s');
    }
    elseif (Cdebs::$_writeBigHeader === 'f') { # short filename
        $header = $pref;
    }
    elseif (intval(Cdebs::$_writeBigHeader) == 0) {
        $header = '';
    }
    else {
        $header = date('Y-m-d H:i:s').' '. $pref;
    }
    fwrite($fh, "$header : $strk\n");
    fclose($fh);
  }
}

# set_error_handler('ErrorHandler'); - to activate error intersepting
function ErrorHandler($errno, $errstr, $errfile='', $errline='', $errcontext='') {
    # if($errno>=8) return;
    $skip_errors = [
      'headers and client library minor version mismatch'
    ];
    foreach($skip_errors as $oner) {
        if (stripos($errstr,$oner) !== FALSE) return;
    }
    # WriteDebugInfo("PHP ERROR $errno: file/line={$errfile}/$errline, Message: $errstr, trace: ", debug_backtrace(NULL,4));
    WriteDebugInfo("PHP ERROR $errno: file/line={$errfile}/$errline, Message: $errstr");
    $backTr = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,4);
    writeDebugInfo("trace calls: ", $backTr);
}

# register_shutdown_function( "shutdownHandler" ); # to activate shutdown error logging
function shutdownHandler() {
    if(!Cdebs::$handleShutDown) return;
    $errfile = "unknown file";
    $errstr  = "shutdown";
    $errno   = E_CORE_ERROR;
    $errline = 0;

    $error = error_get_last();

    if( $error !== NULL) {
        $errtype   = $error["type"];
        if($errtype>=8) return;
        $errfile = $error["file"];
        $errline = $error["line"];
        $errstr  = $error["message"];
        WriteDebugInfo("Shutdown with err data: err-level:$errtype / file=[$errfile] / line=[$errline] / errstr=$errstr");
    }
}

/**
* @desc decode POST data came from AJAX client
*/
function DecodePostData($getpost=false, $trim=false) {
  $ret = ($getpost) ? array_merge($_GET,$_POST) : $_POST;
  if ($trim) $ret = uni_trim($ret);

  if(!IsAjaxCall()) return $ret;
  $cset = defined('MAINCHARSETCL')? MAINCHARSETCL : 'WINDOWS-1251';
  if(strtoupper($cset)!=='UTF-8' && function_exists('mb_convert_variables')) {
    mb_convert_variables($cset,'UTF-8',$ret);
  }
  return $ret;
}
# Recursive array "trimmer" - make "trim" over all elements, including sub-arrays, as array_map('trim',$p) cannot
function uni_trim($par) {
    if (is_string($par)) return trim($par);
    if(is_array($par)) foreach(array_keys($par) as $pkey) {
        $par[$pkey] = uni_trim($par[$pkey]);
    }
    return $par;
}
/**
* encode response to UTF-8 (before sending to AJAX client) (if base charset is not UTF-8)
*
* @param mixed $strk string or array to be encoded
* @return mixed encoded result
*/
function EncodeResponseData($strk) {
    if(defined('MAINCHARSETCL')) $cset = MAINCHARSETCL;
    else $cset = defined('MAINCHARSET')? MAINCHARSET : 'WINDOWS-1251';
    if(strtoupper($cset)!=='UTF-8') {
      if(!headers_sent()) {
          header("Content-type: text/html; charset=utf-8",true); # jQuery, avoid AJAX problems
#          WriteDebugInfo('Content-type charset utf-8 sent, orig.data:',$strk);
      }
#      else WriteDebugInfo('cannot send Content-type charset, headers sent !');
      if(function_exists('iconv')) {
          $encstrk = iconv($cset,'UTF-8',$strk);
#          file_put_contents('_encoderesponse.tmp', "EncodeResponseData from".$_SERVER["SCRIPT_NAME"]." converted from $cset to UTF-8 for is:\r\n".$encstrk);
#          WriteDebugInfo("source: $strk, \r\n converted string:", $encstrk);
          return $encstrk;
      }
      else $strk .= " (iconv dosabled) !";
    }
#  WriteDebugInfo("no need to convert: MAINCHARSET=".MAINCHARSET);
  return $strk;
}
function GetCurrentLanguage() {
  $ret = 'ru';
  if(!empty($_SESSION['userlanguage'])) $ret = $_SESSION['userlanguage'];
  elseif(!empty($_COOKIE['userlanguage'])) $ret = $_COOKIE['userlanguage'];
  $valid_lng = array('ru','en');
  if(!in_array($ret,$valid_lng)) $ret = 'ru';
  return $ret;
}
/**
* checks passed string if can be a valid email (blablabla@domain.nnn)
*/
function isValidEmail($stremail) {
  $ret=preg_match("/^[-0-9A-Z_\.]{1,60}@([-0-9A-Z_\.]+\.){1,60}([0-9A-Z]){2,16}$/i", $stremail);
  return $ret;
}
# source: http://www.bin-co.com/php/scripts/array2json/
function array2json($arr) {
  if(function_exists('json_encode') && !defined('EMULATE_JSON_ENCODE')) return json_encode($arr); //Latest versions of PHP already has this functionality.
  $ret = '';
  $parts = array();
  $is_list = false;

  //Find out if the given array is a numerical array
  $keys = array_keys($arr);
  $max_length = count($arr)-1;
  if(($keys[0] == 0) and ($keys[$max_length] == $max_length)) {//See if the first key is 0 and last key is length - 1
    $is_list = true;
    for($i=0; $i<count($keys); $i++) { //See if each key correspondes to its position
      if($i != $keys[$i]) { //A key fails at position check.
        $is_list = false; //It is an associative array.
        break;
      }
    }
  }

  foreach($arr as $key=>$value) {
      if(is_array($value)) { //Custom handling for arrays
          if($is_list) $parts[] = array2json($value); /* :RECURSION: */
          else $parts[] = "\"$key\":" . array2json($value); /* :RECURSION: */
      } else {
          $str = '';
          if(!$is_list) $str = '"' . $key . '":';

          //Custom handling for multiple data types
          if(is_numeric($value)) $str .= $value; //Numbers
          elseif($value === false) $str .= 'false'; //The booleans
          elseif($value === true) $str .= 'true';
          else $str .= '"' . addslashes($value) . '"'; //All other things
          // :TODO: Is there any more datatype we should be in the lookout for? (Object?)

          $parts[] = $str;
      }
  }
  $json = implode(',',$parts);

  if($is_list) return '[' . $json . ']';//Return numerical JSON
  else return '{' .$json. '}';//Return associative JSON
}
function DrawFieldDate($varname,$initval='', $addon='') {
  global $sitecfg,$statetxt;
  $ret = "<input type='text' name='$varname' id='$varname' style='width:76px' $addon class='ibox' "
    . "onchange='return DateRepair(this)' value='$initval' />";
#  if(!empty($sitecfg['js_calendar'])) $ret .= '&nbsp;'.DrawCalendarButton($varname);
  return $ret;
}
function array_qsort (&$array, $column=0, $order=SORT_ASC, $first=0, $last= -2)
{ # src: http://snipplr.com/view.php?id=713
  // $array  - the array to be sorted
  // $column - index (column) on which to sort
  //          can be a string if using an associative array
  // $order  - SORT_ASC (default) for ascending or SORT_DESC for descending
  // $first  - start index (row) for partial array sort
  // $last  - stop  index (row) for partial array sort
  // $keys  - array of key values for hash array sort

  $keys = array_keys($array);
  if($last == -2) $last = count($array) - 1;
  if($last > $first) {
   $alpha = $first;
   $omega = $last;
   $key_alpha = $keys[$alpha];
   $key_omega = $keys[$omega];
   $guess = $array[$key_alpha][$column];
   while($omega >= $alpha) {
     if($order == SORT_ASC) {
       while($array[$key_alpha][$column] < $guess) {$alpha++; $key_alpha = $keys[$alpha]; }
       while($array[$key_omega][$column] > $guess) {$omega--; $key_omega = $keys[$omega]; }
     } else {
       while($array[$key_alpha][$column] > $guess) {$alpha++; $key_alpha = $keys[$alpha]; }
       while($array[$key_omega][$column] < $guess) {$omega--; $key_omega = $keys[$omega]; }
     }
     if($alpha > $omega) break;
     $temporary = $array[$key_alpha];
     $array[$key_alpha] = $array[$key_omega]; $alpha++;
     $key_alpha = $keys[$alpha];
     $array[$key_omega] = $temporary; $omega--;
     $key_omega = $keys[$omega];
   }
   array_qsort ($array, $column, $order, $first, $omega);
   array_qsort ($array, $column, $order, $alpha, $last);
  }
}

function UseJsModules($modlist, $mode = FALSE) {
    HeaderHelper::UseJsModules($modlist, $mode);
}

function UseCssModules($csslist) {
    HeaderHelper::useCssModules($csslist);
}
/**
* Creates array with all subdirecories of passed dir (optional mask can be set : "subdir/mask*.*")
*
* @param mixed $rootfolder
* @param mixed $recursive
*/
function getSubdirs($rootfolder, $mask='',$recursive=false) {
    if(!$mask) $mask = '*.*';
    if(mb_substr($rootfolder,-1)!='/' && mb_substr($rootfolder,-1)!=='\\') $rootfolder .='/';
    $fdir = opendir($rootfolder);
    $ret = array();
    if(($fdir)) {
        while(($fname=readdir($fdir))) {
            if(is_dir($rootfolder.$fname) && $fname !='.' && $fname!='..') {
                $ret[]=$fname;
                if($recursive) {
                    $subdirs = getSubdirs($rootfolder.$fname, $mask,true);
                    foreach($subdirs as $s_dir) { $ret[] = "$fname/$s_dir"; }
                }
            }
        }
        closedir($fdir);
    }
    return $ret;
}
# Adding css code fragment, to draw in <style> head block
function addCssCode($csscode) {
    HeaderHelper::$html_csscode .= (HeaderHelper::$html_csscode ? "\n":'') . $csscode;
}

function isAjaxCall() {
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest');
}

function generateMenu($data, &$menubody, $id='',$crumbs=array()) { # recursive function actually builds menu HTML code
    $mob = HeaderHelper::$mobile_style;
    $mnuclass = (defined('MENU_CLASS') ? constant('MENU_CLASS') : 'jd_menu');
    if(!$id) $id = empty(HeaderHelper::$menuid) ? 'mainmenu' : HeaderHelper::$menuid;
    if(!$menubody) $menubody = "<ul id=\"$id\" class=\"$mnuclass\">";
    else $menubody .= "\n  <ul>";
    foreach($data as $key=>$item) {
        if(!empty($item['delimiter'])) {
            $menubody .= '<li style="height:3px">---------------</li>';
            continue;
        }
        $href = isset($item['href']) ? $item['href'] : null;
        $titlestr = isset($item['title']) ? $item['title'] : 'no title here';
        $onclick = !empty($item['onclick']) ? "onclick='{$item['onclick']}'" : '';
        if(empty($href) && empty($onclick) && empty($item['submenu'])) continue;
        if(empty($href) && !empty($item['submenu']) && $mob) {
            # create "href" that should build  page with all submenu items
            $full_key = implode('/',$crumbs);
            $full_key .=  ($full_key==='' ? '':'/') . $key; # TODO: add previous submenu id (like breadcrumbs/sub/dir/...)
            $href="./?drawmenu=$full_key"; # Your main dispatch MUST support "drawmenu" call !
        }
        if($onclick) $href='javascript:void(0)';
        $menubody .= ($href!==null || $onclick) ? "<li><a href=\"$href\" $onclick>$titlestr</a>" : "<li>$titlestr";

        if(isset($item['submenu']) && is_array($item['submenu'])) {
            $cr2 = $crumbs;
            $cr2[] = $key;
            generateMenu($item['submenu'],$menubody,'',$cr2);
        }
        $menubody .= '</li>';
    }
    $menubody .= '</ul>';
}
# grabs value of XML element. For example returns "Hello, XML"  from xmlbody "<mytag>Hello, XML</mytag>"
function GetXmlTagValue($xmlbody,$elementname) {
    if ($xmlbody == false) {
        return false;
    }
    $found = preg_match('#<'.$elementname.'(?:\s+[^>]+)?>(.*?)'.
            '</'.$elementname.'>#s', $xmlbody, $matches);
    if ($found)  return $matches[1];  # $matches[0] - whole tag string
    else return false;
}
# Inserts "\n" (LF) if no LF found in the range of $maxlen chars, trying to find closest space to warp text.
function SmartTextWrap($param, $maxlen=80) {
    $ret = '';
    $src = (string)$param;
    $strlf = "\n";
    while(strlen($src) > $maxlen) {
        $lfpos = mb_strpos($src,$strlf);
        if($lfpos!== false && $lfpos <= $maxlen) {
           $ret .= mb_substr($src, 0, $lfpos+1);
           $src = mb_substr($src, $lfpos+1);
           continue;
        }
        $fragment = mb_substr($src,0,$maxlen);
        $spacepos = max(mb_strrpos($fragment, ' '), mb_strrpos($fragment, ','), mb_strrpos($fragment, ';')
           ,mb_strrpos($fragment, '.'),mb_strrpos($fragment, '-'),mb_strrpos($fragment, '/'));
        if($spacepos === false) $spacepos = $maxlen;
        $ret .= mb_substr($src, 0, $spacepos+1) . $strlf;
        $src = mb_substr($src, $spacepos+1);
    }
    $ret .= $src;
    return $ret;
}
# defines if client' REMOTE_ADDR come from one of passed IP sub-nets (IPV4 !)
function RemoteAddrInSubnet($subnets) {
    if(!isset($_SERVER['REMOTE_ADDR'])) return false; # running from console, CRON etc.
    $nets = is_array($subnets) ? $subnets : explode(',',(string)$subnets);
    foreach($nets as $subnet) {
        if(empty($subnet)) continue;
        if(substr($_SERVER['REMOTE_ADDR'], 0, strlen($subnet)) === $subnet) return true;
    }
    return false;
}
# returns beginning string for writing XML file
function xmlStdHeader($encoding='UTF-8') {
    return '<'."?xml version=\"1.0\" encoding=\"$encoding\"?".">\n";
}
function SanitizeUserData(&$vars, $badStrings=false) {
   $rep_patrn = array('/<script/', '/\/script>/','/javascript:/');
   if(is_array($badStrings)) foreach($badStrings as $bad) $rep_patrn[] = '/'.$bad.'/';
   if(is_array($vars)) foreach($vars as $key=>$val) {
       $vars[$key] = preg_replace($rep_patrn, '', $val);
   }
   elseif(is_scalar($vars)) $vars = preg_replace($rep_patrn, '', $vars);
}
function toWin1251($value) {
   return @iconv('UTF-8','WINDOWS-1251', $value);
}
function toUtf8($value) {
    $cset = defined('MAINCHARSET') ? constant('MAINCHARSET') : '';
    if ($cset !=='' && $cset !== 'UTF-8') return @iconv($cset, 'UTF-8', $value);
    return $value;
}
function removeNonDigits($par) {
    $pattern = '/[^0-9]*/';
    return preg_replace($pattern,'', $par);
}
# russian week day name
function RusWeekDay($nday, $short=false) {
    switch($nday) {
        case 0: return ($short) ? 'Вс':'Воскресенье';
        case 1: return ($short) ? 'Пн':'Понедельник';
        case 2: return ($short) ? 'Вт':'Вторник';
        case 3: return ($short) ? 'Ср':'Среда';
        case 4: return ($short) ? 'Чт':'Четверг';
        case 5: return ($short) ? 'Пт':'Пятница';
        case 6: return ($short) ? 'Сб':'Суббота';
    }
}

# parses string like "1...20,35,40,50-55" and creates array containing all "listed" integer values, including ranges n1-n2...
function parseIntList($strg,$nonegative=false, $allowString=FALSE) {
    $ret = array();
    $rtmp = preg_split( '/[,;]/', $strg, -1, PREG_SPLIT_NO_EMPTY );
    foreach($rtmp as $item) {
        if($item==='') continue;
        $spt = explode('...',$item);
        if($nonegative) {
            $spt2 = explode('-',$item);
            if(count($spt2) > count($spt)) $spt = $spt2;
        }
        $ret[] = ($allowString ? $spt[0] : intval($spt[0]));
        if(isset($spt[1]) AND intval($spt[1])>intval($spt[0])) for($i=intval($spt[0])+1;$i<=intval($spt[1]);$i++) { $ret[]=$i; }
    }
    return $ret;
}
function getActiveCharset($defaultset='') {
    if(defined('MAINCHARSETCL')) return constant('MAINCHARSETCL');
    if(defined('MAINCHARSET')) return constant('MAINCHARSETCL');
    return $defaultset;
}
function moneyFormat($val, $decpoint='.', $decimals=2) {
    return number_format(floatval($val),$decimals,$decpoint,' ');
}
function moneyUnformat($val, $thousands=' ', $decpoint=',') {
    return str_replace(array($thousands, $decpoint), array('','.'), $val);
}
function strtoupper_ru($str){
    return strtr($str,
    "abcdefghijklmnopqrstuvwxyz".
    "\xE0\xE1\xE2\xE3\xE4\xE5\xb8\xe6\xe7\xe8\xe9\xea".
    "\xeb\xeC\xeD\xeE\xeF\xf0\xf1\xf2\xf3\xf4\xf5\xf6".
    "\xf7\xf8\xf9\xfA\xfB\xfC\xfD\xfE\xfF",
    "ABCDEFGHIJKLMNOPQRSTUVWXYZ".
    "\xC0\xC1\xC2\xC3\xC4\xC5\xA8\xC6\xC7\xC8\xC9\xCA".
    "\xCB\xCC\xCD\xCE\xCF\xD0\xD1\xD2\xD3\xD4\xD5\xD6".
    "\xD7\xD8\xD9\xDA\xDB\xDC\xDD\xDE\xDF");
}
# getting MAximal value for specified column in multi-row (2D) array
function array_find_max($arval, $column) {
    $ret = null;
    foreach($arval as $row) {
        if (!isset($row[$column]) || !is_scalar($row[$column])) continue;
        $ret = max($ret,$row[$column]);
    }
    return $ret;
}
function getClientLang() {
    if (class_Exists('WebApp') && !empty(WebApp::$lang)) return WebApp::$lang;
    if (defined('MAINLANGUAGE')) return constant('MAINLANGUAGE');
    if (isset($_SESSION['userlang'])) return $_SESSION['userlang'];
    if (isset($_COOKIES['userlang'])) return $_COOKIES['userlang'];
    if (defined('MAINCHARSET') && constant('MAINCHARSET')==='WINDOWS-1251') return 'ru';
    return 'en';
}
function GetMainCharset() {
  return (defined('MAINCHARSET') ? constant('MAINCHARSET') : 'UTF-8');
}
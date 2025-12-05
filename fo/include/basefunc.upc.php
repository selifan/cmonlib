<?PHP
/**
* @name basefunc.upc.php, addon for "basefunc.php", "basefunc2.php" modules
* User-defined module contains additional js/css "predefined" js/css-file definitions (with dependencies]
* @author Alexander Selifonov <as-works [@] narod {.} ru> <alex {at} selifan [dot] ru >
* @copyright Alexander Selifonov
* Modified 2025-11-27
*/
# add user's predefined javascript libraries and related css files
$ret = '';
$jspt = HeaderHelper::$_folderJs;
$isutf = (defined('MAINCHARSET') && substr(constant('MAINCHARSET'),0,3)==='UTF');
$ret = [
# Alexander Selifonov's libraries: as_jsfunclib & jQuery.floatWindow
    'asjs'=> [ 'items'=> [$jspt . 'asjs.js'] ]
    ,'floatwindow' => [ 'items'=> [$jspt . 'jquery.floatwindow.js'],'depends'  => 'ui,cookie' ]
    # ajaxupload.js - file upload "control" for AJAX forms
    ,'ajaxupload' => [ 'items'=> [$jspt . 'ajaxupload.js']]
    ,'simpleajaxuploader' => [ # https://github.com/LPology/Simple-Ajax-Uploader
       # 'items' => [$jspt . 'SimpleAjaxUploader.js'] // for debug!
       'items' => [$jspt . 'SimpleAjaxUploader.min.js']
    ]
    ,'tooltip' => [ 'items'=> [$jspt . 'jquery.tooltip.js'],'cssitems'=> [$jspt .'jquery.tooltip.css']]
    # Scrolling "Hot-line" plugin, don't forget describe css styles tickercontainer,.tickercontainer .mask, ul.newsticker,ul.newsticker li
    #  HighCharts.com charts with theme if set in HIGHCHARTS_THEME constant
    ,'highcharts' => ['depends' => 'jquery','items' => [$jspt . 'highcharts/highcharts.js']]
    ,'highcharts-3d' => ['depends' => 'highcharts','items' => [$jspt . 'highcharts/highcharts-3d.js']]
    # Angular JS library
    ,'angular'    => ['items' => [$jspt . 'angular.min.js']]
    ,'maskedinput'=> [ 'items' => [$jspt . 'jquery.maskedinput.min.js'] ]
    ,'tinymce'    => [ // TinyMCE 3.x
        'items'   => [$jspt.'tiny_mce/tiny_mce.js', $jspt.'tiny_mce/jquery.tinymce.js']
       ,'depends' => 'jquery'
    ]
    ,'tinymce4'   => [ // TinyMCE 4.x
        'items'   => [$jspt.'tinymce4/tinymce.min.js',$jspt.'tinymce4/jquery.tinymce.min.js']
       ,'depends' => 'jquery'
    ]
    ,'ckeditor'   => [ // CKeditor, for version: 4.4.7
        'items'   => [$jspt.'ckeditor/ckeditor.js',$jspt.'ckeditor/adapters/jquery.js']
       ,'depends' => 'jquery'
    ]
    ,'hashtable' => ['items'=>$jspt . 'hashtable.js']
    ,'numberformatter' => ['depends' => 'jquery,hashtable', 'items'=>$jspt . 'jquery.numberformatter.min.js']
    ,'require' => ['items' => [$jspt . 'require/require.js']]
    ,'sticky' => ['depends' => 'jquery', 'items' => [$jspt.'jquery.sticky.js']]
    ,'smartmarquee' => ['depends'=>'jquery', 'items' => [$jspt.'jquery.smartmarquee.js']]
    ,'splitter' => ['items' => [$jspt.'jquery.splitter.js'], 'cssitems'=>[$jspt.'jquery.splitter.css']]
    # new "jqGrid" = datatables!
    ,'datatables' => ['items'=>[$jspt.'datatables/datatables.min.js'], 'cssitems' => [$jspt.'datatables/datatables.min.css'] ]
];

$charts_theme = defined('HIGHCHARTS_THEME') ? constant('HIGHCHARTS_THEME') : '';
if ($charts_theme) $ret['highcharts']['items'][] = $jspt ."themes/$charts_theme.js";

return $ret;

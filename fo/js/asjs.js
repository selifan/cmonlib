/**
 * asjs.js: common javascript function set,
 * Author / collector Alexander Selifonov <alex [at] selifan.ru>
 * @version 1.073.002
 * updated  2024-12-19
 * License MIT
 **/
OBSERVE = null;
OBSERVEname = false;
asJs = {
  version: "1.071",
  defaultDlgClass: 'floatwnd',
  responsecontext: null,
  modalWaitText: '',
  fireEvt: false,
  fnList: [],
  notifyText : '',
  notifyTime: 0,
  notifyTimer: false,
  registerFn: function (id, func) {
    asJs.fnList[id] = func;
  },
  sendRequest: function (url, params, modalmode, fireevents, rq_finalaction) {
    if (typeof (modalmode) == 'undefined') { // create layer above html document
      modalmode = false;
    }
    if (typeof (fireevents) == 'undefined') fireevents = false;
    asJs.fireEvt = fireevents;
    asJs.rq_finalaction = rq_finalaction;
    if (modalmode) ShowSpinner(true);
    //  debugPrint(params, url);
    $.post(url, params, function (data) {
      ShowSpinner(false);
      // console.log('asJs: response: ',data);
      asJs.handleResponse(data, asJs.fireEvt);
      if (as_cumulmsg != '' && $.isFunction(ShowAccumulatedText))
        ShowAccumulatedText(as_cumulmsg);
      if (asJs.rq_finalaction) {
        if ($.isFunction(asJs.rq_finalaction)) asJs.rq_finalaction();
        else eval(asJs.rq_finalaction);
      }
    });
    return false;
  },
  // parse AJAX response and execute passed cmds(alerts,change DOM attributes/form values/...)
  // if array UDFsetValues[] contains varname, call UDFsetValuesFunc() with this data pair instead of std setting
  handleResponse: function (data, fireonchange) {
    as_cumulmsg = '';
    var splt = data.split("\t"),
      fselector, felem;
    // console.log('response items ', splt);
    if (splt[0] == "1") {
      for (var kk = 1; kk < splt.length; kk++) {
        var vals = splt[kk].split(/[|\f]/);
        var vals1 = (vals.length > 1) ? vals[1] : '';
        var vals2 = (typeof (vals[2]) == 'undefined') ? '' : vals[2];

        var cmd = vals[0].trim();
        if (!!asJs.fnList[cmd] && typeof (asJs.fnList[cmd]) === 'function') {
          // var prms = vals;
          prms = vals.splice(1);
          asJs.fnList[cmd](prms);
          continue;
        }
        switch (cmd) { //<3>
          case 'addmsg':
            as_cumulmsg += vals[1];
            break;
          case 'set':
            // var tmpobj = $("#"+vals[1]).get(0);  setFormValue(tmpobj,vals[2],true);
            if (typeof (UDFsetValues) === 'object' && IsInArray(UDFsetValues, vals1)) {
              UDFsetValuesFunc(vals1, vals2);
              continue;
            }
            // asJs.setFormValue(vals1,vals2,fireonchange); continue;
            if(vals1[0] ==='#') felem = $(vals1);
            else
              felem = $("input[name=" + vals1 + "]", asJs.responsecontext);
            if (!felem.get(0)) {
              felem = $("select[name=" + vals1 + "]", asJs.responsecontext);
            }
            if (!felem.get(0)) {
              felem = $("textarea[name=" + vals1 + "]", asJs.responsecontext);
            }
            if (!felem.get(0)) {
              felem = $("#" + vals1, asJs.responsecontext);
            }
            if (!felem.get(0)) { /*alert(vals1+': field not found');*/
              // console.log("not defined elem:", felem);
              continue;
            }
            var inob = felem.get(0);
            try {
              eltype = inob.type;
              if (eltype == 'checkbox') { // non-zero value means 'checked' !
                var curChk = $(inob).is(':checked');
                var newChk = ((vals2 - 0) != 0);
                // $(inob).attr('checked', ((vals2-0)!=0));
                if (curChk != newChk) {
                  // $(inob).prop('checked', newChk);
                  if (fireonchange) $(inob).trigger('click').prop('checked', newChk);
                  else $(inob).prop('checked', newChk);
                }
              } else if (eltype == 'radio') {
                /* felem = "input[name=" + vals1 + "][value=" + vals2+']'; $(felem).attr('checked', true); */
                $(felem).each(function () {
                  // console.log('radio name:', this.name, ' id:',this.id, ' value:', this.value);
                  if (this.value == vals2) {
                    // $(this).attr('checked', true);
                    this.checked = true;
                    if (fireonchange) $(this).triggerHandler('click');
                  }

                });
              } else {
                $(inob, asJs.responsecontext).val(vals2);
                if(vals1 == OBSERVEname) {
                    OBSERVE = $(inob, asJs.responsecontext).val();
                    console.log(vals1, ' to ',vals2, ' new val(): ', $(inob, asJs.responsecontext).val());
                }
              }
            } catch (e) {};
            // if(vals[1] === 'term') console.log(vals[1], 'set to ', vals[2], " now:", inob.value);
            if (fireonchange) {
              if (inob) {
                //  $(felem).trigger('click').trigger('change');
                if (eltype !== 'radio' && eltype !== 'checkbox') {
                  // console.log(inob, "onchange type: ", typeof(inob.onchange));
                  // console.log(inob, "onclick type: ", typeof(inob.onclick));
                  if(typeof(inob.onchange)!=="undefined") try { $(inob).trigger('change'); } catch (e) {};

                  if(typeof(inob.onclick)!=="undefined") try {
                    inob.onclick(inob);
                  } catch (e) {};
                  //  if($.isFunction(felem[0].onclick)) try { felem.onclick(); }  catch (e){};
                }
              }

              if(OBSERVE !== null) {
                  newVal = $("#".OBSERVEname).val();
                  if(newVal != OBSERVE) {
                      console.log(OBSERVEname+" changed to ",newVal, " after ",vals1," event");
                      OBSERVE = null;
                  }
              }

            }
            break;
          case 'html':
            $("#" + vals1).html(vals2);
            // console.log("changed html contents for #"+vals1+" = "+vals2);
            break;
          case 'addhtml': case 'appendhtml':
            $("#" + vals1).append(vals2);
            break;
          case 'title':
            $("#" + vals1).attr("title", vals2);
            break;
          case 'enable':
            if (vals2 != '0') $(vals1).removeAttr('disabled');
            else {
              $(vals1).attr('disabled', true);
            }
            if (vals2 == "1") $(vals1).removeAttr("readonly");
            break;
          case 'readonly':
            $(vals1).attr("readonly", (vals2 != '0'));
            break;

          case 'show':
            if (vals2 === '' || !!vals2) { //show/slideDown/
              $(vals1).show();
            } else { //hide/slideUp
              $(vals1).hide();
            }
            break;
          case 'hide':
            $(vals1).hide();
            break;
          case 'addclass':
            $(vals1).addClass(vals2);
            break;
          case 'removeclass':
            $(vals1).removeClass(vals2);
            break;
          case "css": // change css : tag:value;tag2:value;...
            var tcss = {};
            var cssplt = vals2.split(";");
            for (var cssid in cssplt) {
              var splt2 = cssplt[cssid].split(":");
              if (splt2[1]) tcss[splt2[0]] = splt2[1];
            }
            $(vals1).css(tcss);
            break;
          case 'attr':
          case 'prop': // set DOM attribute
            //              alert(vals);
            $(vals1).prop(vals2, vals[3]);
            break;
          case 'alert':
            alert(vals1);
            break;
          case 'alertdlg':
          case 'showmessage': // showmessage, text [, title, err_class]
            showMessage(vals2, vals1, vals[3],(vals[4]||''));
            break;
          case 'talert':
            var vtime = (typeof (vals[2]) === 'undefined') ? 3 : parseInt(vals[2]);
            var sclass = (typeof (vals[3]) === 'undefined') ? false : vals[3];
            TimeAlert(vals1, vtime, sclass);
            break;
          case 'confirm': // confirm : dlg-title : dlg-text : funcYes [: funcNo]
            var dlgParam = {
              title: vals1,
              text: vals2
            };
            var fYes = vals[3] ? vals[3] : false;
            var fNo = vals[4] ? vals[4] : false;
            dlgConfirm(dlgParam, fYes, fNo);
            break;
          case 'seladd':
          case 'addoption': // add select box option: selectadd | select_id | value [text]
            var selobj = $("#" + vals1).get(0);
            if (selobj.type == "select-one") {
              var opval = vals2;
              var optext = (typeof (vals[3]) == "string") ? vals[3] : opval;
              $(selobj).append('<option value="' + opval + '">' + optext + "</option>");
            }
            break;
          case 'selclear': // clear <select> box from all options
            var selobj = $("#" + vals1).get(0);
            if (selobj.type == "select-one") {
              selobj.options.length = 0;
            }
            break;
          case 'gotourl':
            window.location.href = vals1;
            break;
          case 'reloadpage':
            window.location.reload(true);
            break;
          case 'flash':
            FlashDiv(vals1);
            break;
          case 'eval':
            try {
              eval(vals1)
            } catch (e) {
              if (__JsModeDebug) alert('eval internal error for \n' + vals1);
            };
            break;
          case 'viewlog':
            $("body").floatWindow({
              html: '<div class="div_outline" id="div_viewlog" style="width:800px;height:490px;"><div style="overflow:auto;height:450px; padding:0.2em; margin:0.2em">' + vals1 + '</div></div>',
              id: 'div_viewlog',
              left: 100,
              top: 50,
              title: (vals2 ? vals2 : 'Operation log')
              /*  ,init : function() {
                  $("#div_viewlog").html(vals1);
                  }
              */
            });
            break;
          case 'remove':
            $(vals1).remove();
            break; // removes code from document
          case 'trigger': // jqGrid or other "trigger" supported operation
            var gridOper = vals2 ? vals2 : 'reloadGrid';
            $(vals1).trigger(gridOper);
            break;
          case 'datepicker':
            var selector = vals1;
            if (vals2 === 'destroy') {
              $(vals1).datepicker('destroy'); // disable datepicker
              $(vals1).removeClass("hasDatepicker").removeAttr('id');
            }
            // other datepicker commands...
            break;
          case 'ssv': // Set sessionStorage Var
            sessionStorage.setItem(vals1,vals2);
            break;
          case 'rssv': // remove from sessionStorage
            sessionStorage.removeItem(vals1);
            break;
          case 'lsv': // Set LocalStorage Var
            localStorage.setItem(vals1,vals2);
            break;
          case 'rlsv': // remove from localStorage
            localStorage.removeItem(vals1);
            break;
          case 'timednotify':
            asJs.timedNotification(vals1,vals2);
            break;
          default:
            alert("handleResponse: unsupported cmd [" + vals + ']');
            break;
        }
      }
    } else TimeAlert(data, 4, "msg_error");
  }
  ,setSelectByText: function(selid, strvalue, fireEvt) {
      console.log(selid, " seeking:",strvalue);
      var tOptions = $(selid + " option");
      var RetCode = false;
      tOptions.each(function() {
          if($(this).text() === strvalue) {
              $(selid).val(this.value);
              if(fireEvt) {
                  $(selid).change();
              }
              RetCode = true;
          }
      });
      return RetCode;
  }
  ,TimeAlert: function(txt, seconds, cls) {
      if (typeof txt == 'undefined') {
        txt = 'TimeAlert: no passed string';
      }
      if (!cls) cls = "msg_ok"; // msg_ok OR ui-state-highlight / ui-state-error OR msg_error
      if (!seconds) seconds = 10;
      if (!$("#divetimealert").get(0)) {
        $(document.body).append('<div id="divetimealert" class="' + cls + '" style="z-index:10000; position:absolute; width:400px; text-align:center; left:-1000px;top:20px; padding:16px;" onclick="$(this).hide()"></div>');
      }
      $("#divetimealert").css({
        left: -1000,
        top: 0
      }).show(); // now we can use $.height() to get real div height!
      var divheight = $("#divetimealert").height();
      var divwidth = $("#divetimealert").width();
      var mHeight = window.innerHeight ? window.innerHeight : $(window).height();
      var mWidth = window.innerWidth ? window.innerWidth : $(window).width();
      var mleft = Math.floor((mWidth - divwidth) / 2);
      var mtop = Math.floor((mHeight - divheight) / 2);
      var curScroll = $(window).scrollTop();
      if (curScroll > 0) {
        mtop += curScroll;
      }
      $("#divetimealert").html(txt).css({
        left: mleft,
        top: mtop
      }).removeClass().addClass(cls).show();
      window.setTimeout('$("#divetimealert").fadeOut()', seconds * 1000);
      return false;
  }
  ,confirm: function(param, funcYes, funcNo) {
      var msgtxt = '';
      $("div.ui-dialog").remove();
      var opts = {
        title: 'Подтверждение',
        width:'40%',
        maxWidth:800,
        modal: true,
        closeOnEscape: true,
        resizable: false,
        dialogClass: 'floatwnd'
      };

      if (typeof (param) == 'object') {
        if (param.text) msgtxt = param.text;
        if (param.dialogClass) opts.dialogClass = param.dialogClass;
        if (param.width) opts.width = param.width;
        if (param.title) opts.title = param.title;
        if (param.open) opts.open = param.open;
        if (param.close) opts.close = param.close;
        if (param.height) opts.height = param.height;
        if (param.closeOnEscape) opts.closeOnEscape = param.closeOnEscape;
      } else {
        msgtxt = param;
      }
      var yesLabel = param.yes || 'Да';
      var noLabel = (typeof(param.no)==='undefined') ? 'Нет' : param.no;
      console.log("dlg options:", opts);
      opts.buttons = {};
      opts.buttons[yesLabel] = function () {
          $(this).dialog("close");
          if (funcYes) {
            if (typeof (funcYes) === 'function') funcYes();
            else if (typeof (funcYes) === 'string') eval(funcYes);
          }
          return true;
      };
      if(noLabel) opts.buttons[noLabel] =  function () {
          $(this).dialog("close");
          if (funcNo) {
            if (typeof (funcNo) === 'function') funcNo();
            else if (typeof (funcNo) === 'string') eval(funcNo);
          }
          return false;
      };

      var htmlCode = ("<div id='modalyesno'>" + msgtxt + "</div>");
      $(htmlCode).dialog(opts);
  }
  ,setFormValue: function(fname, fval, fireevt) {
      var felem = $("input[name=" + fname + "]", asJs.responsecontext);
      if (!felem.get(0)) {
        felem = $("select[name=" + fname + "]", asJs.responsecontext);
      }
      if (!felem.get(0)) {
        felem = $("textarea[name=" + fname + "]", asJs.responsecontext);
      }
      if (!felem.get(0)) {
        felem = $("#" + fname, asJs.responsecontext);
      }
      if (!felem.get(0)) {
        // console.log("not found felem:", felem);
        return;
      }
      var inob = felem.get(0);
      try {
        eltype = inob.type;
        if (eltype == 'checkbox') { // non-zero value means 'checked' !
          inob.checked = ((fval - 0) != 0);
          $(inob).attr('checked', ((fval - 0) != 0));
          //  if(vals1=='bea_prg3') alert(vals1+': handleResponseData ckeckbox to '+vals2);
        } else if (eltype == 'radio') {
          $(felem).each(function () {
            // alert($(this).val() +' ==(radio) '+ vals2);
            if ($(this).val() == fval) {
              // alert(vals1+': found for radio trigger:'+vals2);
              $(this).attr('checked', true);
              if (fireevt) $(this).trigger('click');
            }
          });

        } else {
          $(inob, asJs.responsecontext).val(fval);
        }
      } catch (e) {};
      if (fireevt) {
        if (inob) {
          if (eltype !== 'radio') {
            if ($.isFunction(inob.onchange)) {
              try {
                inob.onchange();
              } catch (e) {}
            }
            if ($.isFunction(inob.onclick)) try {
              inob.onclick();
            } catch (e) {};
          }
        }
      }
  }
  ,getFormValue: function(felem) {
      ret = '';
      var itmp;
      if (typeof (felem) == "string") felem = eval(felem);
      if (typeof (felem) != "object") return ret;
      switch (felem.type) {
        case 'select-one':
          itmp = felem.selectedIndex;
          if (itmp >= 0) ret = felem.options[itmp].value;
          break;
        case 'checkbox':
          ret = felem.checked ? 1 : 0;
          break;
        default:
          ret = felem.value;
          break;
      }
      return ret;
    }
    ,yearsBetween: function(date1, date2) {
        if(typeof(date1)=='undefined' || date1 =='') return 0;
        if(typeof(date2)=='undefined' || date2 =='') return 0;
        var s1 = date1.split(/[.-/]/)
        var s2 = date2.split(/[.-/]/);
        if (s1.length<3) {
          s1 = date1.split('-')
        }
        if (s2.length<3) {
          s2 = date2.split('-');
        }
        // showAlert('yearsBetween', 'dateFrom:'+s1.join('; ') + '<br>dateTo:'+s2.join('; '));

        // console.log('yearsBetween: v1:', s1, ' s2:', s2);
        if(s1.length < 3 || s2.length < 3) return 0;

        if ( s1[0]>1000 ) { s1 = [s1[2],s1[1],s1[0]]; }
        if ( s2[0]>1000 ) { s2 = [s2[2],s2[1],s2[0]]; }
        var years = s2[2]-s1[2];
        if(parseInt(s2[1])<parseInt(s1[1])) { years--; }
        else if(s2[1]==s1[1]) {
          if(s2[0] - s1[0]<0) {
            years--;
          }
        }
        // if (years < 0) years = 0;
        return years;
  },
  timedNotification: function(sText, nTime) {
    // console.log("timedNotification:",sText);
    if(this.notifyText !='') {
      this.notifyText = this.notifyText + "<br>"+sText;
    }
    else this.notifyText = sText;
    if($("#asjs_div_timednotify").is(":visible")) {
      $("#asjs_div_timednotify").html(this.notifyText);
      this.notifyTime = Math.max((nTime || 5), this.notifyTime);
    }
    else {
      this.notifyTime = nTime ||  5;
      var notifyCode = '<div id="asjs_div_timednotify" class="msg_timed">' + this.notifyText + '</div>';
      $("body").append(notifyCode);
    }
    if(!this.notifyTimer) {
      this.notifyTimer = setInterval("asJs.tickEvent()",1000);
    }
    return this.notifyTime;
  },
  tickEvent: function() {
    if(this.notifyTime > 1) {
      this.notifyTime--;
      // $("#asjs_div_timednotify").html(this.notifyText + ":"+ this.notifyTime.toString());
    }
    else { // stop timer, remove asjs_div_timednotify, clear notifications
      this.notifyTime = 0;
      this.notifyText ='';
      clearTimeout(this.notifyTimer);
      this.notifyTimer = false;
      $("#asjs_div_timednotify").fadeOut(500, function() { $(this).remove(); });
    }
  },
  clearTimedNotification: function() {
    if(this.notifyTime>0) {
      this.notifyTime = 0;
      this.tickEvent();
      this.notifyText = '';
    }
  }
};
var as_jsdebug = false;
//var ajaxbusy = false;
var as_cumulmsg = ''; // accumulate all 'addmsg' strings here
var _b_modalmode = false;
var __JsModeDebug = false; // true to show some debug info about exceptions etc.

function SetFormValue(felem, newval, fireonchange) {
  asJs.setFormValue(felem, newval, fireonchange);
}

function GetFormValue(felem) { return asJs.getFormValue(felem); }

function SelectBoxSet(frm, objname, val) {
  var obj;
  elems = '';
  if (typeof frm == 'string') eval("obj=document." + frm + "." + objname);
  else eval('obj=frm.' + objname);
  if (!isNaN(obj.selectedIndex)) //alert('SelectBoxSet error: '+frm+'.'+objname+" - not a selectbox !");
  {
    for (kk = 0; kk < obj.options.length; kk++) {
      if (obj.options[kk].value == val) {
        obj.selectedIndex = kk;
        return;
      }
    }
  }
}

// make input date well formatted: dd.mm.yyyy (if fmt='dmy')
function DateRepair(sparam, fmt, delim) {
  if (!fmt) fmt = 'dmy'; // default format - dd.mm.yyyy
  if (!delim) delim = '.'; // default delimiter
  var sdate;
  if (typeof (this.value) !== 'undefined') {
    sdate = this.value; /* functon was attached by jQuery on/change method*/
  } else if (sparam instanceof jQuery) {
    sdate = sparam.val();
  } else {
    sdate = (typeof (sparam) == 'string' ? sparam : sparam.value);
  }
  try {
    if (sdate.length < 1) return '';
  } catch (e) {};
  var spltd = sdate.split(/[\\\/.-]/);
  var posm = fmt.indexOf("m");
  var posd = fmt.indexOf("d");
  var posy = fmt.indexOf("y");
  var now = new Date();

  if (posm < 0 || posd < 0 || posy < 0) {
    alert('DateRepair: wrong fmt - ' + fmt);
    return ''
  }
  if (spltd.length > 1) {
    if (spltd[0] == '') spltd[0] = 0;
    if (!spltd[1] || spltd[1] == '') spltd[1] = 1 + now.getMonth();
    if (!spltd[2] || spltd[2] == '') spltd[2] = now.getFullYear();
  } else if (sdate.length <= 2) { //only first 2 chars entered
    spltd[0] = sdate;
    if (fmt == 'dmy') {
      spltd[1] = 1 + now.getMonth();
      spltd[2] = now.getFullYear();
    } else if (fmt == 'mdy') {
      spltd[1] = now.getDay();
      spltd[2] = now.getFullYear();
    } else if (fmt == 'ymd') {
      spltd[1] = 1 + now.getMonth();
      spltd[2] = now.getDay();
    } else if (fmt == 'ydm') {
      spltd[1] = now.getDay();
      spltd[2] = 1 + now.getMonth();
    }
  } else if (sdate.length <= 4) { // case of ddmm or mmdd
    spltd[0] = sdate.substring(0, 2);
    spltd[1] = sdate.substring(2);
    if (fmt == 'dmy' || fmt == 'mdy') {
      spltd[2] = now.getFullYear();
    }
  } else if (sdate.length <= 8) { //ddmmyyyy, mmddyyyyy, yymmdd ... - case of 2-digit year, no delimiters
    spltd[0] = sdate.substring(0, 2);
    spltd[1] = sdate.substring(2, 4);
    spltd[2] = sdate.substring(4);
  }
  spltd[0] = parseInt(spltd[0] - 0);
  spltd[1] = parseInt(spltd[1] - 0);
  spltd[2] = parseInt(spltd[2] - 0);
  if (isNaN(spltd[0])) spltd[0] = 0;
  if (isNaN(spltd[1])) spltd[1] = 0;
  if (isNaN(spltd[2])) spltd[2] = 0;
  if (spltd[posd] < 1) {
    if (typeof (sparam) == 'object') {
      sparam.value = "";
      return false;
    }
    return "";
  }
  if (spltd[posm] < 1) spltd[posm] = 1 + now.getMonth();
  else if (spltd[posm] > 12) spltd[posm] = 12;
  if (spltd[posy] < 1 && spltd[posy] != "00") spltd[posy] = now.getYear();
  else if (spltd[posy] <= 99) spltd[posy] = spltd[posy] + 2000; //'.96' =>'1996'
  else if (spltd[posy] <= 999) spltd[posy] = spltd[posy] + 1000; //'.x96' =>'1x96'
  delete now;
  // auto-correcting wrong month and day :
  if (spltd[posm] > 12) spltd[posm] = 12;
  else if (spltd[posm] < 1) spltd[posm] = 1;
  maxday = 31;
  if (spltd[posm] == 4 || spltd[posm] == 6 || spltd[posm] == 9 || spltd[posm] == 11) maxday = 30;
  if (spltd[posm] == 2) maxday = (spltd[posy] % 4) ? 28 : 29;
  if (spltd[posd] > maxday) spltd[posd] = maxday;
  else if (spltd[posd] < 1) spltd[posd] = 1;
  if (spltd[posd] < 10) spltd[posd] = "0" + spltd[posd];
  if (spltd[posm] < 10) spltd[posm] = "0" + spltd[posm];
  spltd['d'] = spltd[posd];
  spltd['m'] = spltd[posm];
  spltd['y'] = spltd[posy];
  var ret = "";
  for (kfmt = 0; kfmt < 3; kfmt++) {
    ret += (ret == "" ? "" : delim) + spltd[fmt.substring(kfmt, kfmt + 1)];
  }
  if (typeof (this.value) !== 'undefined') {
    this.value = ret;
    return false;
  }
  if (typeof (sparam) == 'object') {
    sparam.value = ret;
    return false;
  }
  return ret;
}
// DayDiff from A1ien51, http://www.pascarello.com
function DaysDiff(dy1, mo1, yr1, dy2, mo2, yr2) {
  //  var _targetDate = new Date(d.getFullYear(), d.getMonth(), d.getDate(), 0, 0, 0);
  var Date1 = Date.UTC(yr1, mo1 - 1, dy1);
  var Date2 = Date.UTC(yr2, mo2 - 1, dy2);
  DateDiff = Math.round((Date2 - Date1) / (24 * 60 * 60 * 1000));
  return (DateDiff);
}

function DaysDiffDMY(dmobj1, dmobj2) {
  var dmy1 = (typeof (dmobj1) == 'object') ? dmobj1.value : dmobj1;
  var dmy2 = (typeof (dmobj2) == 'object') ? dmobj2.value : dmobj2;
  var da1 = dmy1.split(/[\\\/.-]/);
  var da2 = dmy2.split(/[\\\/.-]/);
  if (da1.length < 3 || da2.length < 3) return false;
  return DaysDiff(da1[0], da1[1], da1[2], da2[0], da2[1], da2[2]);
}

function NumberRepair(sparam, onlyPos, emptyVal) {
  var sdata;
  if (typeof (this.value) !== 'undefined') {
    sdata = this.value; /* functon was attached by jQuery on/change method*/
  } else sdata = (typeof (sparam) == 'string' ? sparam : sparam.value);
  if (typeof emptyVal == 'undefined') emptyVal = '';
  var ret = sdata.replace(',', '.').replace(' ', '');
  // ret = parseFloat(ret);
  ret = ret.replace(/[^0-9\.-]/g,'');
  if (isNaN(ret) || ret == '') ret = emptyVal;
  else if (onlyPos && ret < 0) ret = emptyVal;
  else if(onlyPos > 0) ret = ret.toFixed(onlyPos); // N decimal digits

  if (typeof (this.value) !== 'undefined') {
    this.value = ret;
    return;
  }
  if (typeof (sparam) == 'object') {
    sparam.value = ret;
    return;
  }
  return ret;
}
// converts entered value to "integer", by dropping cents
function IntRepair(sparam, onlyPos, emptyVal) {
  var sdata;
  if (typeof (this.value) !== 'undefined') {
    sdata = this.value; /* functon was attached by jQuery on/change method*/
  } else sdata = (typeof (sparam) == 'string' ? sparam : sparam.value);
  if (typeof emptyVal == 'undefined') emptyVal = '';
  var ret = sdata.replace(',', '.').replace(' ', '');
  ret = parseInt(ret);
  if (isNaN(ret) || ret == '') ret = emptyVal;
  else if (onlyPos && ret < 0) ret = emptyVal;
  if (typeof (this.value) !== 'undefined') {
    this.value = ret;
    return;
  }
  if (typeof (sparam) == 'object') {
    sparam.value = ret;
    return;
  }
  return ret;
}

function addDays(sdate, days) { // src date: dd.mm.yyyy
  var delem = sdate.split(/[\\\/.-]/);
  if (delem.length < 3) return '';
  var nday = (delem[0] - 0);
  var nmon = (delem[1] - 0);
  var nyear = (delem[2] - 0);
  var Dt = new Date(nyear, nmon - 1, nday);
  Dt.setDate(Dt.getDate() + days);
  var eDay = Dt.getDate();
  var eMon = Dt.getMonth() + 1;
  var eYear = Dt.getFullYear();
  var ret = (eDay < 10 ? '0' : '') + eDay.toString() + '.' + (eMon < 10 ? '0' : '') + eMon.toString() + '.' + eYear.toString();
  return ret;
}

function AddToDate(sdate, years, months, days) { // sdate must be in 'dd.mm.yyyy' format !!!
  var mlen = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
  var delem = sdate.split(/[\\\/.-]/);
  if (delem.length < 3) return '';
  var nday = (delem[0] - 0);
  var nmon = (delem[1] - 0);
  var nyear = (delem[2] - 0);
  if (months > 12 || months < -12) {
    years += Math.floor(months / 12);
    months = months % 12;
  }
  if (years != 0) nyear += years;
  if (months > 0) {
    nmon += months;
    while (nmon > 12) {
      nyear++;
      nmon -= 12;
    }
  } else if (months < 0) {
    nmon += months;
    while (nmon < -12) {
      nyear--;
      nmon += 12;
    }
  }
  var nmlen = ((nyear % 4 == 0) && nmon == 2) ? 29 : mlen[nmon];
  nday = Math.min(nday, nmlen);

  if (nday < 10) nday = '0' + nday;
  if (nmon < 10) nmon = '0' + nmon;
  var retDate = nday + '.' + nmon + '.' + nyear;
  if (days != 0) retDate = addDays(retDate, days);
  return retDate;
}
// returns "rounded" years diff between two "dd.mm.yyyy" dates
function RoundedYearsDiff(dt1, dt2, bordermon, show) {
  da1 = dt1.split(/[\\\/.-]/);
  da2 = dt2.split(/[\\\/.-]/);
  var years = parseInt(parseFloat(da2[2])) - parseInt(parseFloat(da1[2]));
  var months = parseInt(parseFloat(da2[1])) - parseInt(parseFloat(da1[1]));
  var days = parseInt(parseFloat(da2[0])) - parseInt(parseFloat(da1[0]));
  if (months < 0) {
    years--;
    months += 12;
  }
  if (days < 0) {
    months--;
    if (months < 0) {
      years--;
      months += 12;
    }
  }
  if (!!show) var bdeb = "border=" + bordermon + ", difference: years-mons-days = " + years + '-' + months + '-' + days;
  if (months > (bordermon - 0)) years++;
  if (!!show) alert("RoundedYearsDiff " + bdeb + " returned years: " + years);
  return years;
}
// compares two 'dd.mm.yyyy' strings as dates. Returns: -1 if dt1 < dt2, 0 if equal, 1 if dt1>dt2
function CompareDates(dt1, dt2) {
  var da1 = dt1.split(/[\\\/.-]/);
  var da2 = dt2.split(/[\\\/.-]/);
  var CompareDates_ret = 0;
  if (da1.length < 3 || da2.length < 3) return false;
  if (parseInt(da1[0]) > 1000) da1 = [da1[2], da1[1], da1[0]]; // auto correct yyyy,mm,dd to dd,mm,yyyy
  if (parseInt(da2[0]) > 1000) da2 = [da2[2], da2[1], da2[0]];

  var nd1 = (10000 * da1[2]) + (100 * da1[1]) + (1 * da1[0]);
  var nd2 = (10000 * da2[2]) + (100 * da2[1]) + (1 * da2[0]);
  if (nd1 > nd2) CompareDates_ret = 1;
  else if (nd1 < nd2) CompareDates_ret = -1;
  else CompareDates_ret = 0;
  return CompareDates_ret;
}

function IsInArray(arr, val) {
  if (typeof (arr) != 'object') return false;
  for (tkey in arr) {
    if (arr[tkey] == val) return true;
  }
  return false;
}

function FormatInteger(integer) { // author:
  var result = '';
  var pattern = '###,###,###,###';
  if (typeof (integer) != 'string') integer = integer + '';
  integerIndex = integer.length - 1;
  patternIndex = pattern.length - 1;
  while ((integerIndex >= 0) && (patternIndex >= 0)) {
    var digit = integer.charAt(integerIndex);
    integerIndex--;
    // Skip non-digits from the source integer (eradicate current formatting).
    if ((digit < '0') || (digit > '9')) continue;

    // Got a digit from the integer, now plug it into the pattern.
    while (patternIndex >= 0) {
      var patternChar = pattern.charAt(patternIndex);
      patternIndex--;
      // Substitute digits for '#' chars, treat other chars literally.
      if (patternChar == '#') {
        result = digit + result;
        break;
      } else {
        result = patternChar + result;
      }
    }
  }
  return result;
}
// formatNumber source from http://phpjs.org/functions/number_format/
function formatNumber(number, decimals, dec_point, thousands_sep) {
  var n = !isFinite(+number) ? 0 : +number,
    prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
    sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
    dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
    s = '',
    toFixedFix = function (n, prec) {
      var k = Math.pow(10, prec);
      return '' + Math.round(n * k) / k;
    };
  // Fix for IE parseFloat(0.55).toFixed(0) = 0;
  s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
  if (s[0].length > 3) {
    s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
  }
  if ((s[1] || '').length < prec) {
    s[1] = s[1] || '';
    s[1] += new Array(prec - s[1].length + 1).join('0');
  }
  return s.join(dec);
}
/* useful functions from Dustin Diaz, http://www.dustindiaz.com/top-ten-javascript/
 */

/* get, set, and delete cookies */
function getCookie(name, default_val) {
  var start = document.cookie.indexOf(name + "=");
  var len = start + name.length + 1;
  if ((!start) && (name != document.cookie.substring(0, name.length))) {
    return null;
  }
  if (start == -1) return default_val;
  var end = document.cookie.indexOf(";", len);
  if (end == -1) end = document.cookie.length;
  return unescape(document.cookie.substring(len, end));
}

function setCookie(name, value, expires, path, domain, secure) {
  var today = new Date();
  today.setTime(today.getTime());
  if (expires) {
    expires = expires * 1000 * 3600 * 24;
  } else expires = 30 * 1000 * 3600 * 24; // default 30 days
  var expires_date = new Date(today.getTime() + (expires));
  document.cookie = name + "=" + escape(value) +
    ((expires) ? ";expires=" + expires_date.toGMTString() : "") + //expires.toGMTString()
    ((path) ? ";path=" + path : "") +
    ((domain) ? ";domain=" + domain : "") +
    ((secure) ? ";secure" : "");
}

function deleteCookie(name, path, domain) {
  if (getCookie(name)) document.cookie = name + "=" +
    ((path) ? ";path=" + path : "") +
    ((domain) ? ";domain=" + domain : "") +
    ";expires=Thu, 01-Jan-1970 00:00:01 GMT";
}

function asGetObj(name) {
  if (document.getElementById) {
    return document.getElementById(name);
  } else if (document.all) {
    return document.all[name];
  } else if (document.layers) {
    if (document.layers[name]) {
      return document.layers[name];
    } else {
      return document.layers.testP.layers[name];
    }
  }
  return null;
}

function round_2dec(num) { // rounds to 2 decimal digits
  var rrr = 0.01 * Math.round(0.01 * Math.round(num * 10000)) + '';
  if (rrr.indexOf(".") != -1) rrr = rrr.substring(0, rrr.indexOf(".") + 4);
  rrr -= 0;
  return rrr;
}

function getObjPos(oElement) {
  var ret = [0, 0];
  while (oElement != null) {
    ret[0] += oElement.offsetLeft;
    ret[1] += oElement.offsetTop;
    oElement = oElement.offsetParent;
  }
  return ret;
}

function TimeAlert(txt, seconds, cls) {
  asJs.TimeAlert(txt, seconds, cls);
}

function HideTimeAlert() {
  $("#divetimealert").hide();
}

/** make some DOM element (div etc) "flashing" 6 times**/
function FlashDiv(id, flashcolor) {
  if (!flashcolor) flashcolor = 'yellow';
  var _FlashCnt = 12;
  var _bgcolor = $("#" + id).css('backgroundColor');
  $("#" + id).css("backgroundColor", _bgcolor);
  var _tmhan = setInterval(function () {
    if (--_FlashCnt <= 0) {
      window.clearTimeout(_tmhan);
    }
    $("#" + id).css("backgroundColor", ((_FlashCnt % 2) ? flashcolor : _bgcolor));
  }, 120);
}
/* make DOM element centralized (position tag must be "absolute" */
// function HVCenter(elemid) {
//   var wHeight = window.innerHeight ? window.innerHeight : $(window).height();
//   var wWidth = $(window).width();
//   var eHeight = parseInt($("#" + elemid).css("height"));
//   var eWidth = parseInt($("#" + elemid).css("width"));
//   var ntop = Math.floor((wHeight - eHeight) / 2) + "px";
//   var nleft = Math.floor((wWidth - eWidth) / 2) + "px";
//   $("#" + elemid).css({
//     top: ntop,
//     left: nleft
//   }).show();
// }

function ShowSpinner(show, imgpth, bmodal, loadtext) {
  if (show) {
    $('#loading_spinner,#_modal_layer').remove();
    var mHeight = window.innerHeight ? window.innerHeight : $(window).height();
    var mWidth = $(window).width();
    if (bmodal) {
      if (!_b_modalmode) {
        _b_modalmode = true;
        $('body').append("<div id='_modal_layer' style='position:fixed; z-index:2000; left:0px; top:0px; background-color:#fff;'/>");
        $('#_modal_layer').css({
          width: '100%',
          height: '100%',
          opacity: 0.5
        });
      }
    }
    // if(!$("#loading_spinner").get(0)) {
    if (!imgpth) imgpth = (typeof (imgpath) == 'string') ? imgpath : 'img/';
    var divText = '<div id="loading_spinner" style="z-index:2001; position:fixed; text-align:center; width:300px; height:200px;">';
    var wtText = loadtext;
    if (!wtText) wtText = asJs.modalWaitText;
    if (wtText) divText += "<div class='wait_message'>" + wtText + "</div><br><br>";
    divText += '<img src="' + imgpth + 'spinner107.gif" style="width:106px;height:107px;border:0"/></div>';
    $(document.body).append(divText);
    //}
    var _left = parseInt(mWidth / 2) - 150;
    var _top = parseInt(mHeight / 2) - 100;
    $("#loading_spinner").css({
      top: _top + "px",
      left: _left + "px"
    }).show();
  } else {
    $("#loading_spinner,#_modal_layer").remove();
    _b_modalmode = false;
  }
}
// Every jQuery Ajax call will show spinner while loading... (in modal mode, if true passed)
function activateAjaxSpinner(bmodal) {
  $(document).ajaxStart(function () {
    ShowSpinner(true, false, bmodal);
  }).ajaxStop(function () {
    $("#loading_spinner").hide();
    $("#_modal_layer").remove();
  });
}

function dlgConfirm(param, funcYes, funcNo) {
  asJs.confirm(param, funcYes, funcNo);
}

// common AJAX call with handling "structured" response
function SendServerRequest(url, params, modalmode, fireevents, rq_finalaction) {
  return asJs.sendRequest(url, params, modalmode, fireevents, rq_finalaction);
}

function SetResponseContext(cntxt) {
  asJs.responsecontext = cntxt;
}

// parse AJAX response and execute passed cmds(alerts,change DOM attributes/form values/...)
// if array UDFsetValues[] contains varname, call UDFsetValuesFunc() with this data pair instead of std setting
function handleResponseData(data, fireonchange) {
  return asJs.handleResponse(data, fireonchange);
}

function debugPrint(obj, title) {
  if ($('#debugprint').get(0)) {
    var strk = '<table><tr><th colspan="2">' + title + '</th></tr>';
    for (skey in (obj)) {
      strk += '<tr><td>' + skey + "</td><td>" + obj[skey] + "</td></tr>";
    }
    strk += '</table>';
    $('#debugprint').html(strk);
  } else {
    var strk = title + "\n";
    for (skey in (obj)) {
      strk += skey + " = [" + obj[skey] + "]\n";
    }
    alert(strk);
  }
}

function stripChars(fobj, mode) {
  if (!mode) mode = 'l';
  var spattern = '',
    outval = 'xxx';
  switch (mode) {
    case 'l':
      spattern = /[^0-9]/g;
      break; // strip all non-digits
    case 'd':
      spattern = /[0-9]/g;
      break; // strip digits
  }
  if (typeof (fobj) == "object") {
    var ival = fobj.value;
    //        alert(typeof(fobj) + ', value='+ival);
    var outval = ival.replace(spattern, '');
    fobj.value = outval;
  } else if (typeof (fobj) == 'string') return fobj.replace(spattern, '');
}
// shows ui dialog window with desired title, text and OK button
function showMessage(stitle, stext, bk_class, wndWidth) {
  $("div.ui-dialog").remove();
  var winW = $(window).width() - 20;
  if (!wndWidth) wndWidth = 600;
  var defw = Math.min(wndWidth, winW);
  var dlgOpts = {
    width: defw,
    resizable: false,
    zIndex: 500,
    buttons: [{
      text: "OK",
      class: "button",
      click: function () {
        $(this).dialog("close").remove();
      }
    }],
    maxHeight: ($(window).height() - 30),
    open: function (event, ui) {
      $('.ui-dialog').css('z-index', 9002);
      $('.ui-widget-overlay').css('z-index', 9001);
    }
  };

  if (!!stitle) dlgOpts.title = stitle;
  var dlgClass = (typeof (bk_class) === 'string') ? bk_class : '';
  if(dlgClass !='') {
      dlgOpts.classes = { 'ui-dialog': dlgClass };
      dlgOpts.dialogClass = dlgClass;
  }

  $('<div id="dlg_showmessage" style="z-index:9900">' + stext + '</div>').dialog(dlgOpts);
}
// from dd.mm.yyyy to yyyy-mm-dd, for correct comparisons etc
function to_date(ddmmyyyy) {
  var spl = ddmmyyyy.split(/[\\\/.-]/);
  if (spl[0] >= 1000) return ddmmyyyy;
  if (spl[0] <= 1 || spl.length < 3) return '';
  return (spl[2] + '-' + spl[1] + '-' + spl[0]);
}

function parseIntList($strg, $nonegative) {
  var ret = [];
  var rtmp = $strg.split(/[,;]/);
  for (var nn in rtmp) {
    if (rtmp[nn] === '') continue;
    $spt = rtmp[nn].split('-');
    ret.push(($spt[0]));
    if (!!($spt[1]) && parseInt($spt[1]) > parseInt($spt[0]))
      for (var i = parseInt($spt[0]) + 1; i <= parseInt($spt[1]); i++) {
        ret.push(i);
      }
  }
  return ret;
}

function debugValue(par) {
  var ret = '';
  if (typeof (par) == 'object') {
    ret = typeof (par) + ": \n[";
    for (var kk in par) {
      ret += ' ' + kk + ':' + debugValue(par[kk]);
    }
    ret += "]\n";
  } else ret += par;
  return ret;
}

formTool = {
  save: function (fmid) {
    var htm, cval, cl = [];
    $('input,select,textarea', fmid).each(function () {

      cval = null;
      var iname = this.name;
      if (this.type === 'checkbox') {
        cval = this.checked ? this.value : null;
      } else if (this.type === 'radio') {
        if (this.checked) cval = this.value;
      } else cval = $(this).val();
      if (cval !== null) {
        cl[iname] = cval;
        htm += iname + ' = ' + cval + "\n";
      }
    });
    alert(fmid + ":\n" + htm);
    for (id in cl) {
      $.cookie(id, cl[id]);
    }
  },
  restore: function (fmid, varlst, fireevt) {
    SetResponseContext(fmid);
    for (id in varlst) {
      var cval = $.cookie(id);
      if (cval) asJs.setFormValue(id, cval, fireevt);
    }
    SetResponseContext(false);
  }
};
StrUtils = {
  padl: function (strg, schar, len) {
    var ret = strg + '';
    while (ret.length < len) {
      ret = schar + ret;
    }
    return ret;
  },
  padr: function (strg, schar, len) {
    var ret = strg + '';
    while (ret.length < len) {
      ret += schar;
    }
    return ret;
  }
}
// object debCons for debugging needs: show scrolled events log etc.
debCons = {
  dlgObj: null,
  log: function (strg) {
    if (typeof (window.console) == 'object') {
      console.log(strg);
      return;
    }
    if (this.dlgObj === null) {
      this.dlgObj = $('<div id="debConsole" style="overflow:auto;font-height:8px"/>').dialog({
        height: 400,
        width: 200,
        left: 1,
        top: 1,
        title: 'debug Log',
        position: {
          my: 'left',
          at: 'right bottom'
        }
      }).addClass('debuglog');
    } else {
      if (!this.dlgObj.dialog('isOpen')) this.dlgObj.dialog('open');
    }
    $('#debConsole').append(strg + '<br>').animate({
      scrollTop: 9000000
    }, 1000);
  }
}

// storage object for saving/storing named values. Old (non-HTML5) browsers will use cookie
Storage = {
  set: function (key, value, mode) {
    if (typeof (value) != 'string') value = String(value); // TODO: if array or object, make json string
    try {
      if (mode) {
          window.sessionStorage.setItem(key, value);
      }
      else {
          window.localStorage.setItem(key, value);
      }
    } catch (e) {
        console.error('save ERROR/'+key, e);
    }
  },
  get: function (key, defaultval, mode) {
    var ret = '';
    try {
      if(mode) {
        ret = window.sessionStorage.getItem(key);
        if (ret === null) ret = defaultval;
      }
      else {
        ret = window.localStorage.getItem(key);
        if (ret === null) ret = defaultval;
      }
    } catch (e) {
      // console.log('KT2: storage.getItem error: ' + e.message);
      // getCookie(key,defaultval);
    }
    return ret;
  },
  remove: function (key) {
    if (typeof (key) != 'string') key = String(key);
    try {
      if (mode) return sessionStorage.removeItem(key);
      else return localStorage.removeItem(key);
    } catch (e) {
      //return deleteCookie(key);
    }
  }
}

/* // to test IE in compat mode:
$(document).ready(function () {
    var iec = new IECompatibility();
    alert('IsIE: ' + iec.IsIE + '\nVersion: ' + iec.Version + '\nCompatability On: ' + iec.IsOn);
});
*/

function IECompatibility() {
  var agentStr = navigator.userAgent;
  this.IsIE = false;
  this.IsOn = undefined; //defined only if IE
  this.Version = undefined;

  if (agentStr.indexOf("MSIE ") > -1) {
    this.IsIE = true;
    if (agentStr.indexOf("Trident/6.0") > -1) {
      this.Version = 'IE10';
      if (agentStr.indexOf("MSIE 7.0") > -1) this.IsOn = true;
    } else if (agentStr.indexOf("Trident/5.0") > -1) {
      this.Version = 'IE9';
      if (agentStr.indexOf("MSIE 7.0") > -1) this.IsOn = true;
    } else if (agentStr.indexOf("Trident/4.0") > -1) {
      this.Version = 'IE8';
      if (agentStr.indexOf("MSIE 7.0") > -1) this.IsOn = true;
    } else {
      this.IsOn = false; // compatability mimics 7, thus not on
      this.Version = 'IE7';
    }
  } //IE 7
}

function getSelectedText(objectId) {
  var textComponent = document.getElementById(objectId);
  var selectedText;
  if (document.selection != undefined) { // IE version
    textComponent.focus();
    var sel = document.selection.createRange();
    selectedText = sel.text;
  } else if (textComponent.selectionStart != undefined) { // Mozilla version
    var startPos = textComponent.selectionStart;
    var endPos = textComponent.selectionEnd;
    selectedText = textComponent.value.substring(startPos, endPos);
  }
  return selectedText;
}

// msie 6, or compatibility mode - no indexOf on arrays, so add it
if (!Array.indexOf) {
  Array.prototype.indexOf = function (obj) {
    for (var i = 0; i < this.length; i++) {
      if (this[i] == obj) {
        return i;
      }
    }
    return -1;
  }
}
if (!String.prototype.trim) {
  String.prototype.trim = function () {
    return this.replace(/^\s+|\s+$/g, '');
  };
}
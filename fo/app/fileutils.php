<?php
/**
* @package ALFO
* Набор функций для работы с файлами сканов в полисах
* modified 2025-09-10
* @version 1.11.002
* sample run: heif-convert -q 80 tmp/test.heic tmp/test-001.jpg
*/
class FileUtils {

    const FOLDER_SCANS = 'agmt_files/'; # в эту папку складываются сканы документов
    static $debug_Uploads = 0;
    static $logHeic = 1;
    static $debug = 0;
    static $debugPdf = 0; # выводить опознанные размерности страниц исходника PDF
    static $renameScans = 1; # автоматически переименовывать файлы сканов в соотв. с указанным типом док.
    static $fileRestrictions = []; # ограничения на формат файла, если надо для некоторых типов (пример: паспорт стр-ля - только PDF)
    private static $baseScanNames = [ # база для имен файлов в соотв. с типом скана-документа
      'plc_zayav' => 'Заявление-на-страхование',
      'passport_insr' => 'Паспорт-документ-Страхователя',
      'passport_insd' => 'Паспорт-документ-Застрахованного',
      'passport_insd2' => 'Паспорт-документ-Застрахованного-2', # для полисов с 2-мя застр (Поч.возраст!)
      'passport_child' => 'Паспорт-свид-во-Ребенка',
      'passport_ben'  => 'Паспорт-документ-Выгодоприобретателей',
      'plc_paydoc' => 'Платежный-документ',
      'viza' => 'Виза-миграционная-карта',
      'anketa_insr' => 'Анкета-Клиента',
      'anketa_insd' => 'Анкета-Застрахованного',
      'anketa_ben'  => 'Анкеты-Выгодоприобретателей',
      'anketa_other' => 'Доп-анкеты',
      'oplist_fatca' => 'Опросный-лист-FATCA',
      'fin_anketa' => 'Финансовая-анкета',
      'fin_plan' => 'Фин-план',
      'spravki_ndfl' => 'Справки-2НДФЛ',
      'signed_policy' => 'Скан-подписанного-полиса',
      'epolicy' => 'Электронный-полис',
      'edo_policy' => 'Электронный-полис-ЭДО',
      'calculation' => 'Расчет-стоимости', # HappyHome - XLS файл с расчетом (ну и где еще пригодится)
      'sogl_pdn' => 'Согласие-на-обработку-ПДн',
    ];

    private static $docCategories = [
      'blank' => 'Бланки, шаблоны документов',
      'normdoc' => 'Нормативные Документы',
      'instruct' => 'Инструкции, руководства',
      'insrules' => 'Правила страхования',
      'other' => 'Прочие файлы',
    ];

    # список конвертеров для авто-конвертации загруженных файлов типа A в тип B (например, Apple heic -> jpg)
    static $imageConverters = [
      'heic' => 'convertHeic', # для вызовов конвертора heic -> jpg
    ];

    # добавляет ограничение на тип(расширение) файла для указанного типа сканов
    public static function setFileTypeRestriction($fileType, $extList) {
        self::$fileRestrictions[$fileType] = is_array($extList) ? $extList : explode(',', $extList);
    }
    public static function addScanType($typeid, $desc, $fileName = FALSE) {
        PM::$scanTypes[$typeid] = $desc;
        self::$baseScanNames[$typeid] = ($fileName ? $fileName : strtr($desc, [' '=>'-','/'=>'-']));
    }
    public static function getFilesInPolicy($module, $id, $filter = FALSE, $forExport=FALSE) {
        # writeDebugInfo(__CLASS__, '/', __FUNCTION__, " module=$module, id=$id");
        $bkend = AppEnv::getPluginBackend($module);
        if ($module === 'investprod') {
            $where = ['insurancepolicyid'=>$id];
            if (is_array($filter)) $where = array_merge($where, $filter);
            elseif(is_string($filter) && !empty($filter)) $where[] = $filter;
            $ret = appEnv::$db->select(investprod::TABLE_DOCSCANS, ['where'=>$where,
              'orderby'=>'id'
            ]);
        }
        else {
            $where = ['stmt_id'=>$id];
            if (is_array($filter)) $where = array_merge($where, $filter);
            elseif(is_string($filter) && !empty($filter)) $where[] = $filter;
            $ret = appEnv::$db->select(PM::T_UPLOADS, ['where'=>$where,
              'fields' => 'id,doctype,filename,filesize,descr,path,exported',
              'orderby'=>'doctype DESC,id'
            ]);
        }
        if (is_array($ret) && $forExport) {
            # перегоняю поля для СЭД
            $files = [];
            foreach($ret as &$fl) {
                $fullFpath = $fl['path'] . $fl['filename'];
                $fl['fullpath'] = $fullFpath;
                if (!is_file($fullFpath)) continue;
                $fl['filename'] = $fl['descr'];
                if(method_exists($bkend, 'getExportDocumentName')) {
                    # {upd/2025-04-09} в модуле есть ф-ция, задающая свои выходные имена для типов документов (в ДСЖ-скан паспорта)
                    $newName = $bkend->getExportDocumentName($id, $fl['doctype']);
                    if(!empty($newName)) {
                        $ext = GetFileExtension($fl['filename']);
                        $fl['filename'] = $newName . '.'. $ext;

                    }
                }
            }
        }

        return $ret;
    }
    # Пришел запрос на чтение (загрузку в браузер клиенту) файла скана к договору
    public static function openFile($params) {
        $data = appEnv::$db->select(PM::T_UPLOADS, array('where'=>array('id'=>$params['id']), 'singlerow'=>1));
        while ($oblev = ob_get_level()) {
            ob_end_clean();
        }
        if (is_array($data) && (count($data)>0)) {
           # if (ob_get_level()) ob_end_clean();
           # $srcfname = translit($data['descr']);
           $srcfname = trim($data['descr']);
           $srcext = appEnv::getFileExt($srcfname);
           $realext = appEnv::getFileExt($data['filename']);
           if ($srcext !== $realext) $srcfname .= ".$realext";
           $fname = $data['path']. $data['filename'];
           if(!is_file($fname)) exit(appEnv::getLocalized('err_file_not_found'));
           #WriteDebugInfo("orig file name to load: ", $srcfname);
           appEnv::sendBinaryFile($fname, $srcfname);

        }
        else appEnv::echoError('err_data_not_found');
        exit;
    }

    # Изменение - (пока только удаление) скана док-та
    public static function updateFile($params) {
        # if ($this->debug) WriteDebugInfo('updtagrscan params:', $this->_p);
        #TODO: проверять разрешение оператору на работу с полисом !!!
        $pars = is_array($params) ? $params : appEnv::$_p;
        $oper = $pars['oper'];
        $idlist = explode(',', $pars['id']);
        $module = $pars['plg'];
        $bkend = appEnv::getPluginBackend($module);
        $ret = '1';
        $plcdata = false;
        $origId = isset($params['policyid']) ? $params['policyid'] : 0;
        $policyid = FALSE;
        $access = 0;
        if($oper === 'del') {
            foreach($idlist as $id) {
                $dta = appEnv::$db->select(PM::T_UPLOADS, array('where'=>"id=$id", 'singlerow'=>true));
                if ($policyid===FALSE) {
                    $policyid = isset($dta['stmt_id']) ? $dta['stmt_id'] : 0;
                    if ($policyid === 0) {
                        if (appEnv::isApiCall()) return ['result'=>'ERROR','message'=>'Неверный ИД файла'];
                        continue;
                    }
                    $plcdata = $bkend->loadPolicy($policyid,-1);
                    $access = $bkend->checkDocumentRights($policyid);

                    if (!$access) {
                        if (appEnv::isApiCall()) return ['result'=>'ERROR', 'message'=>appEnv::getLocalized('err-no-rights')];
                        exit($ret); # Оформленный договор - нельзя ничего удалять
                    }
                }
                if ($origId>0 && $policyid!=$origId) {
                    if (appEnv::isApiCall()) return ['result'=>'ERROR','message'=>'ИД файла не от того полиса'];
                    continue;
                }
                if ($plcdata['stateid']==11) {
                    $ret = 'Договор в статусе Оформлен, удаление файлов недоступно<br>';
                    if (appEnv::isApiCall()) return ['result'=>'ERROR', 'message' => $ret];
                    exit($ret); # Оформленный договор - нельзя ничего удалять
                }
                $fname = isset($dta['filename']) ? $dta['path'].$dta['filename'] : '';
                $origName = $dta['descr'];
                if($fname && is_file($fname)) @unlink($fname);
                appEnv::$db->delete(PM::T_UPLOADS, array('id'=>$id));
                $pref = $bkend->getLogPref();
                appEnv::logEvent($pref.'SCAN DEL',"Удален файл $origName ($id)",0, $policyid);
                if (appEnv::isApiCall()) return ['result'=>'OK', 'message'=>'Файл скана удален'];
            }
            # $ret = json_encode(array('success'=>0, 'response'=>'You Cannot delete last scan!')); // debug
        }
        exit($ret);
    }

    # проверяю наличие аналогичного файла по его размеру и MD5 сумме
    public static function fileAlreadyInPolicy($policyid, $fsize, $md5sum) {

        $dta = appEnv::$db->select(PM::T_UPLOADS, array('where'=>"stmt_id=$policyid"));
        if(is_array($dta) && count($dta)>0) {
            foreach($dta as $finfo) {
                $fname = $finfo['path'] . $finfo['filename'];
                if(is_file($fname) && filesize($fname)==$fsize && md5_file($fname) === $md5sum)
                    return TRUE;
            }
        }
        return FALSE;
    }

    /**
    *  пришел файл со сканом - надо занести в систему!
    * @param mixed $params, по умолчанию - вызов из AJAX, все данные в appEnv::$_p, $_FILES
    * но если надо залить файл из другого модуля - передан массив:
    * 'doctype' => doc_type, 'filename' => "file.ext', 'filebody' OR 'fullpath' - для передачи содержимого
    * # TODO: сохранять MD5 суммы файлов, защита от повторных заливок одного файла md5sum - md5_file()
    */
    public static function addScan($params=0, $skipCheck = false, $plcdata = FALSE, $sysAction=FALSE) {
        if (self::$debug_Uploads)  {
            WriteDebugInfo("addScan, $skipCheck=[$skipCheck], _FILES=",$_FILES);
            WriteDebugInfo('passed params=',$params);
            WriteDebugInfo('appEnv::_p:  ',appEnv::$_p);
            WriteDebugInfo('plcdata:  ',$plcdata);
            writeDebugInfo("isApicall: ", appEnv::isApiCall());
        }
        appEnv::avoidExternalHeaders();
        $module = FALSE;
        if (isset($params['plg'])) $module = $params['plg'];
        elseif (!empty($params['module'])) $module = $params['module'];
        elseif (!empty(appEnv::$_p['plg'])) $module = appEnv::$_p['plg'];
        elseif (!empty($plcdata['module'])) $module = $plcdata['module']; # вызов при онлайн оплате

        if(!$module) {
            $errText = 'Не указан страховой модуль';
            writeDebugInfo("Загрузка файла, ошибка: $errText", " _p:", AppEnv::$_p, ' params:', $params, "\trace:" , debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
            if(AppEnv::isApiCall()) return ['result'=>'ERROR', 'message'=>$errText];
            exit($errText);
        }

        $plcid = isset($params['id']) ? $params['id'] : ($plcdata['stmt_id'] ?? AppEnv::$_p['policyid'] ?? 0);

        if(!$plcid) {
            $errText = 'Не передан ИД полиса';
            writeDebugInfo("Загрузка файла, ошибка: $errText", " _p:", AppEnv::$_p, ' params:', $params, "\trace:" , debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
            if(appEnv::isApiCall()) return ['result'=>'ERROR', 'message'=>$errText];
            exit($errText);
        }

        # {upd/2024-12-18} - добавил настройку папки загрузок - если непустая, буду класть по заданному пути
        $flFolder = PolicyModel::getUploadFolder(self::FOLDER_SCANS);

        if(self::$debug_Uploads) {
            writeDebugInfo("policy id: [$plcid], module:$module, folder: $flFolder, is_dir:[". is_dir($flFolder).']');
            if(self::$debug_Uploads > 2 && is_dir($flFolder)) {
                $checkWritable = file_put_contents($flFolder . '/_test.txt', 'writetest');
                writeDebugInfo("write to folder result:[$checkWritable]");
                $tmpDir = ini_get('upload_tmp_dir');
                $savedTmp = @file_put_contents( "$tmpDir/test.txt", "write to tmp $tmpDir");
                WriteDebugInfo("check write to temp $tmpDir result: $savedTmp");

            }
        }
        $bkend = appEnv::getPluginBackend($module);
        if(!empty($bkend->scanRestrict) && is_array($bkend->scanRestrict) && count($bkend->scanRestrict))
            self::$fileRestrictions = array_merge(self::$fileRestrictions,$bkend->scanRestrict);

        $return = FALSE;
        if (is_array($params) ) {
            # вызов из другого модуля
            $return = TRUE;
            # $plcid = $params['id'];
            $doctype = isset($params['doctype']) ? $params['doctype'] : '';
            if ($doctype == '') {
                if (appEnv::isApiCall()) $doctype = 'agmt';
                else $doctype = 'checklog';
            }

            # writeDebugInfo("params for file:", $params, " trace ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4));
            if(!empty($params['fullpath']) || !empty($params['filepath'])) {
                $tmpname = $params['fullpath'] ?? $params['filepath'];
                $filesize = filesize($tmpname);
                $name = $params['filename'] ?? basename($tmpname);
            }
            elseif(!empty($params['filebody'])) {
                $saveDir = AppEnv::getAppFolder('tmp/');
                # WriteDebugInfo("is writable $saveDir: [" . is_writable($saveDir). ']');
                $tmpname = $saveDir . '/tempfile'. rand(100000,999999).'.tmp';
                $filesize = @file_put_contents( $tmpname, $params['filebody'] );

                $params['size'] = strlen($params['filebody']);
                $name = isset($params['filename']) ? $params['filename'] : 'noname'; # обязательно передать!
            }
            $filesize = @filesize($tmpname);
            if (self::$debug_Uploads) writeDebugInfo("KT-1: tmpname = $tmpname, size = $filesize, name = $name");
        }
        else {
            $plcid = appEnv::$_p['policyid'] ?? AppEnv::$_p['cardid'] ?? 0;
            if (!$plcid && !empty($plcdata['stmt_id'])) $plcid = $plcdata['stmt_id'];
            $doctype = isset(appEnv::$_p['doctype']) ? appEnv::$_p['doctype'] : (isset($params['doctype'])? $params['doctype'] : 'stmt');
            if (self::$debug_Uploads) WriteDebugInfo("policyid: [$plcid], attachfile info: ", $_FILES);
            $fl = isset($_FILES['attachfile']) ? $_FILES['attachfile'] : array();

            if(empty($fl['size'])) {
                $erText = isset($fl['error']) ? self::decodeFileError($fl['error']) : 'Неверно оформлен массив не задан(size)';
                $errMsg = \AppEnv::getLocalized('err-upload_error') . '<br>'.$erText;
                if($fl['error'] == 6) $errMsg .= "<br>php.ini Tmp folder:".ini_get('upload_tmp_dir');
                $errMsg .= "<br>php:ini from: ".php_ini_loaded_file();
                appEnv::echoError($errMsg);
                exit;
            }
            $name = $fl['name'];
            $tmpname = $fl['tmp_name'];
            $filesize = filesize($tmpname);
            if (!is_dir($flFolder)) {
                if (is_file($tmpname)) @unlink($tmpname);
                if (appEnv::isApiCall()) {
                    return array(
                      'result' =>'ERROR',
                      'message' => appEnv::getLocalized('Загрузка временно невозможна')
                    );
                }
                appEnv::echoError('Загрузка невозможна: не создана папка для файлов '.$flFolder);
                exit;
            }
        }
        if (self::$debug) writeDebugInfo("tmpname: $tmpname, filesize: ", $filesize);
        /*
        if(!$plcid) {
            if ($return || appEnv::isApiCall()) return ['result'=>'ERROR', 'message'=>'policy ID not passed'];
            appEnv::echoError('policy ID not passed');
            exit;
        }
        */
        if (!empty($params['id'])) $access = 2; # вызов при онлайн-оплате или др.сторонних операциях, права не смотрим)
        else $access = $bkend->checkDocumentRights($plcid);
        if(self::$debug_Uploads) writeDebugInfo("access for document: [$access]");
        if (!$access) {
            if ($return || appEnv::isApiCall()) {
                return ['result'=>'ERROR', 'message' => appEnv::getLocalized('err-no-rights')];
            }
            appEnv::echoError('err-no-rights');
            exit;
        }

        $ext = $origExt = self::fileExtension($name);
        # writeDebugInfo("file ext: ", $ext);

        # проверка ограничения на тип загруженного файла
        if(!empty(self::$fileRestrictions[$doctype])) {

            if(!in_array(strtolower($ext), self::$fileRestrictions[$doctype])) {
                $allExt = implode(', ',  self::$fileRestrictions[$doctype]);
                $scanTp = \PlcUtils::decodeScanType($doctype);
                $error = appEnv::getLocalized('err-filetype-restrict','',['{ext}'=>$allExt,'{type}'=>$scanTp]);
                # writeDebugInfo("file error: ", $error);
                if ($return || appEnv::isApiCall()) {
                    return ['result'=>'ERROR', 'message' => $error ];
                }
                appEnv::echoError($error);
                exit;
            }
        }

        if(method_exists($bkend, 'checkScanFile')) {
            $checked = $bkend->checkScanFile($doctype,$tmpname, $ext);
            if($checked!==TRUE) {
                if(!is_string($checked)) $checked = 'Файл не прошел проверку';
                if ($return || appEnv::isApiCall()) {
                    return ['result'=>'ERROR', 'message' => $checked ];
                }
                appEnv::echoError($checked);
            }
        }
        # writeDebugInfo("$name / $tmpname, ext: $fileExt");
        if(isset(self::$imageConverters[$ext])) {
            $convName = self::$imageConverters[$ext];
            $result = FALSE;
            if(method_exists('FileUtils',$convName))
                $result = self::$convName($name, $tmpname);
            elseif(is_callable($convName))
                $result = call_user_func($convName, $name, $tmpname);
            if(is_array($result) && count($result)>=2) {
                # конвертация прошла успешно, беру сконвертированный вместо загруженного
                if(is_file($tmpname) && $tmpname != $result[1])
                    @unlink($tmpname); # исходный удаляю за ненадобностью
                list($name, $tmpname) = $result;
                $ext = self::fileExtension($name); # новое расширение!
                $filesize = filesize($tmpname); # новый размер!
            }
            if(self::$debug_Uploads) writeDebugInfo("$origExt-$ext convertation, new name and tmpName: $name, $tmpname, new size: $filesize");
        }

        if ($doctype === PM::$scanEdoPolicy) {
            $oldDel = self::deleteOldVersion($plcid, $doctype);
            if(self::$debug) writeDebugInfo("$doctype: delete Old version:[$oldDel]");
        }
        else {
            $filehash = md5_file($tmpname);
            if (!$skipCheck && self::fileAlreadyInPolicy($plcid,$filesize, $filehash)) {
                if ($return || appEnv::isApiCall()) {
                    return [
                      'result' =>'ERROR',
                      'message' => appEnv::getLocalized('err_uploaded_file_already_exists')
                    ];
                }
                appEnv::echoError('err_uploaded_file_already_exists');
                exit;
            }
        }

        if ($filesize <=0 ) {
            if( self::$debug_Uploads ) WriteDebugInfo("upload abort - file size=0");
            if ($return || appEnv::isApiCall()) {
                return array(
                  'result' =>'ERROR',
                  'message' => 'Ошибка при загрузке файла, либо передан пустой файл'
                );
            }
            exit('Ошибка при загрузке файла, либо передан пустой файл');
        }

        $inserted_id = 0;
        try {

            if ($doctype === 'checklog') {
                self::deleteOldVersion($plcid, $doctype);
                # удалить предыдущий checklog файл, если есть
            }

            $savepath = str_replace("\\", '/', $flFolder);
            $newBaseName = self::getBaseNameForType($doctype);

            if ( self::$renameScans && !empty($newBaseName) ) {
                $curFiles = self::getCurrentFileScanNames($plcid);
                if ($ext !=='') $newBaseName .= '.' . $ext;
                $name = self::uniqueFileName($newBaseName, $curFiles);
            }
            $f = array(
               'stmt_id' => $plcid
               ,'doctype' => $doctype
               ,'filename' => '_tochange_'
               ,'filesize' => $filesize
               ,'path' => $savepath
               # ,'descr' => ( isset($this->_p['description']) ? $this->_p['description'] : $name)
               ,'descr' => $name
               ,'createdby'=> appEnv::$auth->userid
            );
            if (self::$debug_Uploads) {
                WriteDebugInfo("savepath: $savepath, Data array to insert ",$f);
            }

            $result = $inserted_id = appEnv::$db->insert(PM::T_UPLOADS, $f);
            if (!$result) {
                $err = 'Ошибка при занесении в БД данных о файле '.appEnv::$db->sql_error();
                WriteDebugInfo($err, ' параметры:', $f);
                if ($return || appEnv::isApiCall()) {
                    return array(
                      'result' =>'ERROR',
                      'message' => 'Ошибка при занесении в БД'
                    );
                }
                appEnv::echoError($err);
                exit;
            }

            //сохраняем для отката
            $scanid = appEnv::$db->insert_id();
            $movename = $module ."-{$scanid}.{$ext}";
            $movefilePath = $savepath . $movename;
            # меняю имя файла на реальное, с id из таблицы
            appEnv::$db->update(PM::T_UPLOADS, array('filename'=>$movename), array('id'=>$scanid));
            if(self::$debug_Uploads) WriteDebugInfo("договор $plcid: добавлена запись о загруж.файле $name/$scanid, сохр.под именем $movename");
            # $this->rollback[] = array('id'=>$f['id'],'tablename'=>'bn_policyscan');
            if(dirname($tmpname) == dirname($movefilePath)) {
                writeDebugInfo("doctype: [$doctype] одинаковая папка у файлов $tmpname / $movefilePath");
                writeDebugInfo("tmpname     =$tmpname exist: [". is_file($tmpname).']');
                writeDebugInfo("movefilePath=$movefilePath exist: [". is_file($movefilePath).']');
            }

            if (!copy($tmpname,$movefilePath)) {
                WriteDebugInfo("ALARM: полис $plcid, удаление скана:$scanid после неудачного переноса файла из $tmpname в $movefilePath, ",
                  debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4));
                appEnv::$db->delete(PM::T_UPLOADS, array('id'=>$scanid));
                @unlink($tmpname);
                if (appEnv::isApiCall()) {
                    return array(
                      'result' =>'ERROR',
                      'message' => ('Внутренняя ошибка при копировании загруженного файла')
                    );
                }
                appEnv::echoError('err_moving_uploaded_file');
            }
            else @unlink($tmpname);

            $logText = ($doctype === PM::$scanEdoPolicy) ? "Сформирован эл.полис с ПЭП" : "Добавлен файл $name ($doctype)";
            $userid = ($sysAction) ? 0 : FALSE;
            appEnv::logEvent($bkend->getLogPref().'SCAN ADD',$logText,0,$plcid,$userid,$module);
            # $gridid = ($doctype==='stmt') ? 'grid_stmtscans' : 'grid_agrscans';

            $gridid = 'grid_agrscans';
            $ret = "1\ttrigger\f#{$gridid}\freloadGrid"; # скомандует обновить грид со сканами

            if (!in_array($doctype, [PM::$scanCheckLog, PM::$scanEdoPolicy])) {
                # при сохранении "системных" файлов кнопки не обновлять, и писем не слать!
                if (!appEnv::isApiCall())
                    $ret .= $bkend->refresh_view($plcid, TRUE); # обновить доступность кнопок !!!
                # WriteDebugInfo("addscan-KT-009 notifyAgmtChange... ");
                if ( !empty($bkend->notify_addfile) && method_exists($bkend, 'notifyAgmtChange')) {
                    $details = "Загружен новый файл $name ($doctype)";
                    $bkend->notifyAgmtChange($details, $plcid);
                }
            }

            if (appEnv::isApiCall()) {
                return [
                    'result' => 'OK',
                    'message' => 'Файл успешно приложен к полису',
                    'data' => ['fileid' => $inserted_id] # API- возвращаю ИД записи о файле
                ];
            }

            if ($return || is_array($params)) { # вызов из другого модуля, не AJAX
                return $movefilePath;
            }

            exit($ret); # AJAX response
        }
        catch (Exception $e) {
            $err = $e->getMessage();
            if ($return || appEnv::isApiCall() ) {
                return [
                    'result' => 'ERROR',
                    'message' => 'Вызвано исключение '.$err
                ];
            }

            exit ($err);
        }
    }
    public static function getCurrentFileScanNames($plcid) {
        $ret = appEnv::$db->select(PM::T_UPLOADS, ['where'=>['stmt_id'=>$plcid], 'fields'=>'descr','associative'=>0]);
        return $ret;
    }
    public static function getBaseNameForType($doctype) {
        if (!isset(self::$baseScanNames[$doctype])) {
            $descr = PM::$scanTypes[$doctype] ?? '';
            if(!empty($descr)) return strtr($descr, [' '=>'-','/'=>'-']);
            return FALSE;
        }
        return self::$baseScanNames[$doctype];
    }
    public static function deleteOldVersion($plcid, $doctype) {
        $dta = appEnv::$db->select(PM::T_UPLOADS, array('where'=>['stmt_id'=>$plcid, 'doctype'=>$doctype], 'singlerow'=>true));
        if (!empty($dta['id'])) {
            $fullname = $dta['path'].$dta['filename'];
            if (is_file($fullname)) @unlink($fullname);
            appEnv::$db->delete(PM::T_UPLOADS, ['id'=> $dta['id']]);
            return TRUE;
        }
        return FALSE;
    }

    # удаляем из договора файлы сканов, ставшие устаревшими (перерасчет, изменение в данных)
    # $docTypes - список удаляемых типов (или '*' - "удалить всё")
    public static function deleteFilesInAgreement($module, $plcid, $docTypes) {
        if (empty($module) || empty($plcid) || empty($docTypes)) return FALSE;
        $allTypes = (is_string($docTypes) && $docTypes==='*');
        if (!is_array($docTypes)) $docTypes = explode(',', $docTypes);
        $bkend = appEnv::getPluginBackend($module);
        $log_pref = $bkend->getLogPref();
        $ret = 0;
        if ($module === PM::INVEST) {
            $finfo = appEnv::$db->select(investprod::TABLE_DOCSCANS, ['where'=>['insurancepolicyid'=>$plcid], 'orderby'=>'id']);
            if (is_array($finfo)) foreach($finfo as $fl) {
                if($allTypes || in_array($fl['typeid'], $docTypes)) {
                    $fullname = $fl['path'] . $fl['filename'];
                    if (is_file($fullname))  {
                        @unlink($fullname);
                        appEnv::$db->delete(investprod::TABLE_DOCSCANS, ['id' => $fl['id']]);
                        $ret++;
                        appEnv::logEvent("$log_pref.DEL FILE", "Удаление файла $fl[filename]($fl[typeid])", false, $plcid);
                    }
                }
            }
        }
        else {
            $finfo = appEnv::$db->select(PM::T_UPLOADS, array('where'=>['stmt_id'=>$plcid], 'orderby'=>'id'));
            if (is_array($finfo)) foreach($finfo as $fl) {

                if($allTypes || in_array($fl['doctype'], $docTypes)) {
                    $fullname = $fl['path'] . $fl['filename'];
                    if (is_file($fullname))  {
                        @unlink($fullname);
                        appEnv::$db->delete(PM::T_UPLOADS, ['id' => $fl['id']]);
                        $ret++;
                        appEnv::logEvent("$log_pref.DEL FILE", "Удаление файла $fl[filename]($fl[doctype])", false, $plcid);
                        # writeDebugInfo("$module/$plcid/удаление файла, тип $fl[doctype]");
                    }
                    else {
                        appEnv::$db->delete(PM::T_UPLOADS, ['id' => $fl['id']]);
                        # writeDebugInfo("$fullname already was deleted, delete record in DB");
                    }
                }
            }
        }
        return $ret;
    }

    /**
    * Проверка загруженного файла - сможет ли его загрузить FPDI для генерации/подписания
    *
    * @param mixed $fileName
    * @return кол-во страниц в PDF файле или FALSE если нечитабельный
    */
    public static function checkPdf($fileName) {
        if (!is_file($fileName)) return FALSE;
        require_once('tcpdf/tcpdf.php');
        require_once('tcpdi/tcpdi.php');
        $pdfTest = new TCPDI('P','mm','A4');
        $ret = FALSE;
        try {
            $ret = $pdfTest->setSourceFile($fileName); // кол-во страниц в шаблоне
        }
        catch (Exception $e) {
            $err = $e->getMessage();
            if (self::$debug) writeDebugInfo("check pdf $fileName error: ", $err);
            $ret = FALSE;
        }
        return $ret;
    }
    /**
    * Получить параметры всех страниц в PDF (чтобы знать какие шаблоны использовать - P|L
    *
    * @param mixed $fileName
    */
    public static function getPdfPagesInfo($fileName, $verbose=FALSE) {
        if (!is_file($fileName)) {
            if($verbose) echo "file <b>$fileName</b> not exists<br>";
            return FALSE;
        }
        require_once('tcpdf/tcpdf.php');
        require_once('tcpdi/tcpdi.php');
        $pdfTest = new TCPDI('P','mm','A4');
        $ret = FALSE;
        try {
            $pagecount = $pdfTest->setSourceFile($fileName); // кол-во страниц в PDF
            if($verbose) echo "pages in PDF: ". ((int)$pagecount);
            $ret = [];
            for($i = 1; $i <= $pagecount; $i++){
                $tplidx = $pdfTest->importPage($i);
                $specs = $pdfTest->getTemplateSize($tplidx);
                if(self::$debugPdf) writeDebugInfo("page $i: specs = ", $specs);
                $specs['orientation'] = ($specs['h'] > $specs['w'] ? 'P' : 'L');
                $ret[$i] = $specs;
                # $pdf->addPage($specs['h'] > $specs['w'] ? 'P' : 'L'); # ВОТ ОНО РЕШЕНИЕ!
                # $pdf->useTemplate($tplidx);
            }
            unset($pdfTest, $tplidx, $specs);
        }
        catch (Exception $e) {
            $err = $e->getMessage();
            if (self::$debug) writeDebugInfo("check pdf $fileName error: ", $err);
            if($verbose) echo "read PDF error: ".$err;
            $ret = FALSE;
        }
        return $ret;
    }

    # создает файл с первыми NN байтами из указанного (обрезалка)
    public static function cutFile($srcName, $size=0) {
        if (!is_file($srcName)) return FALSE;
        $fh = fopen($srcName, 'rw');
        if ($size <=0) $size= 32000;
        $body = fread($fh, $size);
        fclose($fh);
        $ret = appEnv::getAppFolder('tmp') . 'cutfile.txt';
        $fh = fopen($ret, 'w');
        if (!$fh) return 'Cannot write to '.$ret;
        fwrite($fh, $body);
        fclose($fh);
        return $ret;
    }

    /**
    * Придумывает уникальное имя файла, чтобы не встречалось в списке уже созданных,
    * добавляя к имени номер name_1.txt, name_2.txt, ...
    * @since 1.03 (2021-05-25)
    * @param @$baseName - исходное имя файла
    * @param @namesArr - массив готовых имен
    */
    public static function uniqueFileName($baseName, $namesArr = []) {
        $baseName = strtr($baseName, ["'"=>'', '"'=>'', '?'=>'','/'=>'','\\'=>'']);
        $fileExt = self::fileExtension($baseName);
        if ($fileExt === '') $justName = $baseName;
        else $justName = mb_substr($baseName, 0, -(mb_strlen($fileExt, 'UTF-8')+1),'UTF-8');
        $retName = $justName . ( $fileExt ? ".$fileExt" : '');
        $no = 0;

        while($no <=1000) {
            if (!is_array($namesArr) || !in_array($retName,$namesArr)) break;
            $no++;
            $retName = $justName . '_'. $no . ( $fileExt ? ".$fileExt" : '');
        }
        return $retName;
    }

    public static function fileExtension($filename) {
        $pinfo = pathinfo($filename);
        return strtolower($pinfo['extension']);
    }

    /**
    * Загружаю данные из XSLX как строки с полями через ТАБ.
    *
    * @param mixed $srcName имя XLSX файла
    * @param mixed $skipRow сколько первых строк пропустить (заголовок)
    * @param mixed $sheetId номер или имя листа
    * @since 1.04
    */
    public static function importXls($srcName, $skipRow=1, $sheetId=0) {
        if (!class_exists('PHPExcel_IOFactory')) {
            require_once('PHPExcel.php');
            require_once('PHPExcel/IOFactory.php');
        }
        try {
            $objReader = PHPExcel_IOFactory::createReaderForFile($srcName);
            $objReader->setReadDataOnly(TRUE);
            $objPHPExcel = $objReader->load($srcName);

            if(is_string($sheetId)) {
                $sheet = $objPHPExcel->getSheetByName($sheetId);
            }
            elseif(is_int($sheetId))
                $sheet = $objPHPExcel->getSheet($sheetId);
            else {
                $sheet = $objPHPExcel->getSheet(0);
            }
            $lastRow = $sheet->getHighestRow();
            $lastCol = PHPExcel_Cell::columnIndexFromString($sheet->getHighestColumn());
            $srcCurRow = $skipRow + 1;
        } catch(Exception $e) {
            # echo ($err_message = $filename . ' - XLS opening exception raised: ' .$e->getMessage());
            return FALSE;
        }
        $ret = [];
        for($krow = $srcCurRow; $krow<=$lastRow; $krow++) {
            $cols = [];
            for ($icol=0;$icol<$lastCol; $icol++) {
                $value = '';
                try {
                    $cell = @$sheet->getCellByColumnAndRow($icol, $krow);
                    $value = $cell->getCalculatedValue();
                }
                catch(Exception $e) {
                    try {
                        $cell = $sheet->getCellByColumnAndRow($icol, $krow);
                        $value = $cell->getValue();
                    }
                    catch(Exception $e) {
                    }
                }
                $cols[] = $value;
            }
            $ret[] = implode("\t", $cols);
        }

        if(count($ret)) $ret = implode("\n", $ret);
        else $ret = '';

        # file_put_contents('tmp/_test.txt', $ret); # test
        return $ret;
    }

    # вернет папку для сохранения нового файла (по плагину и тек.году)
    public static function getFilesFolder($module) {
        $retFodler = AppEnv::getAppFolder(self::FOLDER_SCANS . "$module/" . date('Y'));
        if(!is_dir($retFodler)) {
            $created = mkdir($retFodler,077,TRUE);
        }
        if(is_dir($retFodler)) return "$retFodler/";
        return FALSE;
    }
    /**
    * сохранение нвого загруженого файла для любого модуля
    * @param mixed $params, по умолчанию - вызов из AJAX, все данные в appEnv::$_p, $_FILES
    * но если надо залить файл из другого модуля - передан массив:
    * 'doctype' => doc_type, 'filename' => "file.ext', 'filebody' OR 'fullpath' - для передачи содержимого
    */
    public static function addAnyFile($params=FALSE) {
        if (self::$debug_Uploads)  {
            WriteDebugInfo("addAnyFile, $skipCheck=[$skipCheck], _FILES=",$_FILES);
            WriteDebugInfo('appEnv::_p:  ',appEnv::$_p);
            writeDebugInfo("isApicall: ", appEnv::isApiCall());
        }
        appEnv::avoidExternalHeaders();
        appEnv::avoidExternalHeaders();
        if(!$params) $params =& AppEnv::$_p;
        if (isset($params['plg'])) $module = $params['plg'];
        else {
            exit("wrong addAnyFile call/plg undefined");
        }

        $parentid = $params['id'] ?? $params['objectid'] ?? $params['cardid'] ?? 0;

        $flFolder = self::getFilesFolder($module);
        if(!$flFolder) exit('Не могу создать папку для файла');

        if(self::$debug_Uploads) writeDebugInfo("policy id: [$parentid], module:$module");
        $bkend = appEnv::getPluginBackend($module);
        $return = FALSE;


        if (self::$debug_Uploads) WriteDebugInfo("policyid: [$parentid], attachfile info: ", $_FILES);
        $fl = isset($_FILES['attachfile']) ? $_FILES['attachfile'] : array();

        if(empty($fl['size'])) {
            appEnv::echoError('err-upload_error');
            exit;
        }
        $name = $fl['name'];
        $ext = self::fileExtension($name);
        $tmpname = $fl['tmp_name'];
        $filesize = filesize($tmpname);
        if (!is_dir($flFolder)) {
            if (is_file($tmpname)) @unlink($tmpname);
            if (appEnv::isApiCall()) {
                return array(
                  'result' =>'ERROR',
                  'message' => appEnv::getLocalized('Загрузка временно невозможна')
                );
            }
            appEnv::echoError('Загрузка невозможна: не создана папка для файлов '.$flFolder);
            exit;
        }

        if (self::$debug) writeDebugInfo("tmpname: $tmpname, filesize: ", $filesize);

        if(!$parentid) {
            if ($return || appEnv::isApiCall()) return ['result'=>'ERROR', 'message'=>'policy ID not passed'];
            appEnv::echoError('policy ID not passed');
            exit;
        }
        /*
        if (!empty($params['id'])) $access = 2; # вызов при онлайн-оплате или др.сторонних операциях, права не смотрим)
        else $access = $bkend->checkDocumentRights($parentid);
        if(self::$debug_Uploads) writeDebugInfo("acces for document: [$access]");
        if (!$access) {
            if ($return || appEnv::isApiCall()) {
                return ['result'=>'ERROR', 'message' => appEnv::getLocalized('err-no-rights')];
            }
            appEnv::echoError('err-no-rights');
            exit;
        }
        */
        if ($filesize <=0 ) {
            if( self::$debug_Uploads ) WriteDebugInfo("upload abort - file size=0");
            if ($return || appEnv::isApiCall()) {
                return array(
                  'result' =>'ERROR',
                  'message' => 'Ошибка при загрузке файла, либо передан пустой файл'
                );
            }
            exit('Ошибка при загрузке файла, либо передан пустой файл');
        }

        $filehash = md5_file($tmpname);
        if (!$skipCheck && self::fileAlreadyInObject($module, $parentid,$filesize, $filehash)) {
            if ($return || appEnv::isApiCall()) {
                return [
                  'result' =>'ERROR',
                  'message' => appEnv::getLocalized('err_uploaded_file_already_exists')
                ];
            }
            appEnv::echoError('err_uploaded_file_already_exists');
            exit;
        }

        $inserted_id = 0;

        try {
            $savepath = str_replace("\\", '/', $flFolder);
            $arFile = [
               'module' => $module,
               'objid' => $parentid,
               'filename' => $name,
               'fileext' => $ext,
               'filepath' => $savepath . '_to_change_',
               'filesize' => $filesize,
               'filehash' => $filehash,
               'createdby'=> appEnv::getUserId(),
               'datecreated' => '{now}',
            ];
            if (self::$debug_Uploads) {
                WriteDebugInfo("savepath: $savepath, Data array to insert ",$arFile);
            }

            $result = $inserted_id = appEnv::$db->insert(PM::T_ANYFILES, $f);
            if (!$result) {
                $err = 'Ошибка при занесении в БД данных о файле '.appEnv::$db->sql_error();
                if (self::$debug_Uploads) WriteDebugInfo($err, ' параметры:', $arFile);
                if ($return || appEnv::isApiCall()) {
                    return [
                      'result' =>'ERROR',
                      'message' => 'Ошибка при занесении в БД данных'
                    ];
                }
                appEnv::echoError($err);
                exit;
            }
            $longid = str_pad($inserted_id,6,'0',STR_PAD_LEFT);
            $movename = "$parentid-$longid.$ext";
            $movefilePath = $savepath . $movename;

            //сохраняем для отката
            # меняю имя файла на реальное, с id из таблицы
            appEnv::$db->update(PM::T_UPLOADS, ['filepath'=>$movefilePath], array('id'=>$scanid));
            if(self::$debug_Uploads) WriteDebugInfo("договор $parentid: добавлена запись о загруж.файле $name/$scanid, сохр.под именем $movename");
            # $this->rollback[] = array('id'=>$f['id'],'tablename'=>'bn_policyscan');

            if (!copy($tmpname,$movefilePath)) {
                WriteDebugInfo("ALARM: полис $parentid, удаление скана:$scanid после неудачного переноса файла из $tmpname в $movefilePath");
                appEnv::$db->delete(PM::T_UPLOADS, array('id'=>$inserted_id));
                @unlink($tmpname);
                if (appEnv::isApiCall()) {
                    return array(
                      'result' =>'ERROR',
                      'message' => ('Внутренняя ошибка при копировании загруженного файла')
                    );
                }
                appEnv::echoError('err_moving_uploaded_file');
            }
            else @unlink($tmpname);

            $logText = "Добавлен файл $name";
            appEnv::logEvent($bkend->getLogPref().'FILE ADD',$logText,0,$parentid);

            $gridid = 'grid_agrscans';
            $ret = "1\ttrigger\f#{$gridid}\freloadGrid"; # скомандует обновить грид со сканами

            if (appEnv::isApiCall()) {
                return [
                    'result' => 'OK',
                    'message' => 'Файл успешно загружен',
                    'data' => ['fileid' => $inserted_id] # API- возвращаю ИД записи о файле
                ];
            }

            if ($return || is_array($params)) { # вызов из другого модуля, не AJAX
                return true;
            }

            exit($ret); # AJAX response
        }
        catch (Exception $e) {
            $err = $e->getMessage();
            if ($return || appEnv::isApiCall() ) {
                return [
                    'result' => 'ERROR',
                    'message' => 'Вызвано исключение '.$err
                ];
            }

            exit ($err);
        }
    }

    public static function fileAlreadyInObject($module, $parentid, $filesize, $filehash) {
        $arDta = AppEnv::$db->select(PM::T_ANYFILES,[
          'where'=> [ 'module'=>$module,'objid'=>$parentid,'filehash'=> $filehash ],
          'singlerow' => 1]);
        $ret = (!empty($arDta['filesize']) && $arDta['filesize'] == $filesize);
        return $ret;
    }

    # генерация AJAX ответа для заполнения грида jqGrid с файлами
    public static function getGridContents() {
        writeDebugInfo("getGridContents params: ", AppEnv::$_p);
        $module = AppEnv::$_p['plg'] ?? '';
        $objid = AppEnv::$_p['id'] ?? 0;
        $arDta = AppEnv::$db->select(PM::T_ANYFILES, [
          'where'=>['module'=>$module,'objid'=>$objid],
          'fields'=>'filename,filesize,filepath','orderby'=>'id']
        );
        $rows = is_array($arDta) ? count($arDta) : 0;
        $response = [];
        $reccnt = $response['records'] = $rows;
        if(is_array($arDta) && count($arDta)) {
            # ob_start();
            foreach($dt as $row) {
                $rid = $row['id'];
                $url = "<a href='javascript://void(0)' onclick=\"bugi.getOneFile($rid)\">Просмотреть</a>";
                $response['rows'][] = [
                   'id'   => $row['id'],
                   'cell' => [ $row['filename'], number_format($row['filesize'],0,0,' '), $url ]
                ];
            }
        }
        # if(($console=ob_get_clean())) WriteDebugInfo('loadpolicyscans, parasite echo/errors:',$console);
        # writeDebugInfo("files response ", $response);
        exit(json_encode($response, JSON_UNESCAPED_UNICODE));
    }

    # вернет список всех загруженных файлов документов, приказов с группировками для формирования SELECT
    public static function getDocFileList($category=FALSE, $module = FALSE) {
        $cacheid = "docfilesList-$category-$module";
        if(!isset(AppEnv::$_cache[$cacheid])) {
            $where = [];
            if(!empty($category)) $where['category'] = $category;
            if(!empty($module)) $where['moduleid'] = $module;
            if(count($where)==0) $where = '1';

            $arFiles = \AppEnv::$db->select(PM::T_UPLOADEDFILES ,['where'=>$where,'orderby'=>'category,description']);
            $ret = [ [0,'Не выбрано'] ];
            $curCat = '---';
            if(is_array($arFiles) && count($arFiles)) foreach($arFiles as $item) {
                if($item['category'] != $curCat) {
                    $curCat = $item['category'];
                    if(empty($category))
                        $ret[] = ['<', self::decodeFileCategory($curCat)];
                }
                $ret[] = [ $item['id'], $item['description'] ];
            }
            AppEnv::$_cache[$cacheid] = $ret;
        }
        return AppEnv::$_cache[$cacheid];
    }

    # вернет только список фацйлов категории "правила страхования"
    public static function getRulesFileList() {
        $ret = self::getDocFileList('insrules');
        return $ret;
    }
    public static function decodeFileError($errcode) {
        switch($errcode) {
            case 1: case 2: return 'Превышение макс.размера загружаемого файла';
            case 3: return 'Файл загружен частично';
            case 4: return 'Файл не загружен';
            case 6: return 'Нет папки временной загрузки';
            case 7: return 'Нет прав на запись';
            case 8: return 'Недопустимое расширение файла';

        };
    }
    public static function getFocFileInfo($fileId) {
        $arRet = \AppEnv::$db->select(PM::T_UPLOADEDFILES ,array('where'=>array('id'=>$fileId),'singlerow'=>1));
        if(!empty($arRet['id'])) {
            $folder = AppEnv::getFocumentsFolder();
            $arRet['full_path'] = $folder. 'filebody-'.str_pad($fileId, 8,'0',STR_PAD_LEFT) . '.dat';
        }
        return $arRet;
    }
    public static function getFileCategories() {
        return self::$docCategories;
    }
    public static function decodeFileCategory($catid) {
        return (self::$docCategories[$catid] ?? "[$catid]");
    }

    # вернёт TRUE если переданная строка - URL адрес
    public static function isUrl($strPar) {
        if(is_numeric($strPar)) return FALSE;
        if(!is_string($strPar)) return FALSE;
        $sLen = strlen($strPar);
        if($sLen>7 && substr($strPar,0,7) === 'http://') return TRUE;
        if($sLen>8 && substr($strPar,0,8) === 'https://') return TRUE;
        return FALSE;
    }
    /**
    * эмулятор конвертации heic в jpg (простое переименование)
    * @param $fileName имя загруженного файла
    * @param $tmpFilename полный путь к файлу на диске
    * @return "официальное имя" нового файл ЛИБО FALSE (неудача)
    */
    public static function justRename($fileName, $tmpFilename) {
        $ext = '.heic';

        $fileExt = mb_substr($fileName,-5,NULL, MAINCHARSET);
        if(strtolower($fileExt) !== $ext) return FALSE; # не наш тип
        $ret = mb_substr($fileName,0,-strlen($ext),MAINCHARSET) . '.jpg';
        $newTmpName = $tmpFilename . '.jpg';
        $newFileName = mb_substr($fileName,0,-strlen($ext),MAINCHARSET) . '.jpg';

        if(is_writable($tmpFilename)) {
            @copy($tmpFilename, $newTmpName);
            return [$newFileName, $newTmpName];
        }
        else return FALSE;
    }

    # {upd/2024-03-21} реальный вызов конвертера heic -> jpg (вызов heif-convert, linux-only!)
    public static function convertHeic($fileName, $tmpFilename) {
        $ext = '.heic';
        $quality = \AppEnv::getConfigValue('convert_heic', 0);
        if(intval($quality)<=0) return FALSE; # фича не активироана к настройках

        # $fileExt = mb_substr($fileName,-5,NULL, MAINCHARSET);
        # if(strtolower($fileExt) !== $ext) return FALSE; # не наш тип
        $newTmpName = $tmpFilename . '.jpg';
        $newFileName = mb_substr($fileName,0,-strlen($ext),MAINCHARSET) . '.jpg';
        if(is_writable($tmpFilename)) {
            # вместо исходного $tmpFilename положить сконвертированный файл с таким же именем
            $shellCmd = "heif-convert -q $quality $tmpFilename $newTmpName";
            $echoed = $exitCode = '';
            $result = exec($shellCmd, $echoed, $exitCode);
            if(self::$logHeic) writeDebugInfo("after call $shellCmd: echoed=",$echoed, "\n  exitcode: $exitCode");
            if(is_file($newTmpName)) {
                # @unlink($tmpFilename);
                if(self::$logHeic) writeDebugInfo("out file created, $newTmpName");
                return [$newFileName, $newTmpName];
            }
            writeDebugInfo("$newTmpName: out Jpg file NOT created, check heif-convert if works!");

            return FALSE;
        }
        else {
            if(self::$logHeic) writeDebugInfo("$newTmpName: out file NOT writebale: check folder rights!");
            return FALSE;
        }
    }
    public static function isConvertable($fileName) {
        $fext = self::fileExtension($fileName);
        if(isset(self::$imageConverters[$fext])) {
            if($fext === 'heic') {
                $ret = AppEnv::getConfigValue('convert_heic',0);
            }
            else $ret = TRUE;
        }
        else $ret = FALSE;
        return $ret;
    }
    /**
    * конвертирует файл, меняет tmp_name,name,size в переданном массиве
    *
    * @param mixed $arFile
    */
    public static function convertFile(&$arFile) {
        # TODO
    }
    # Очитска директории от старых файлов (соотв.маске если передать, дни хранения (0.5 = 12 часов)
    public static function cleanFolder($folder, $mask='', $days=FALSE, $recursive = FALSE, $exclude=FALSE) {
        $emul = 0;
        $deleted = 0;
        $excList = [];
        if(!empty($exclude)) {
            if(is_string($exclude)) $excList = preg_split("/[ ,;]/",$exclude, -1, PREG_SPLIT_NO_EMPTY);
            elseif(is_array($exclude)) $excList = $exclude;
        }
        $titleok = 'deleted';
        $titleer = 'deleting error !';
        if ($days== FALSE) $days = 1; # 1 day

        if ($days <=0) return '';
        $ret = '';

        if (empty($mask)) $mask = '*';
        if(floor($days) != $days) $dateString = '-' . round($days*24,2). ' hours'; # non-integer days convert to hours
        else $dateString = "-$days days";
        $watermark = strtotime($dateString);

        $ret = 0;

        $darr = [];
        if(substr($folder,-1) == '/'){ $folder = substr($folder,0,-1);  }
        if(empty($mask)) $mask = '*.*';

        if (is_dir($folder)) {
          foreach (glob($folder.'/{'.$mask.'}', GLOB_BRACE) as $filename) {
            if ( is_file($filename) && filemtime($filename) < $watermark ) {
              $darr[] = $filename;
            }
          }
        }

        if(count($darr)) foreach($darr as $fname) {
          $fSize = filesize($fname);
          if($emul) $ok = 1;
          else $ok = @unlink($fname);
          if($ok) $ret += $fSize;
        }
        # if ($ret) $ret = "$folder : <br>$ret";
        # recursive cleanup in sub-folders
        if ($recursive) foreach(glob("$folder/*") as $dirElem) {
            if (is_dir($dirElem)) {
                $baseDirName = basename($dirElem);
                $ret += $this->cleanFolder($dirElem, $mask, $days, $recursive, $excList);
            }
        }
        return $ret;
    }

    /**
    * Вытщит имя основного PDF шаблона из XML настройки печати
    * @param mixed $xmlFileName - полный путь/имя XML файла
    */
    public static function getPdfTemplateFromXml($xmlFileName) {
        $xml = @simplexml_load_file($xmlFileName);
        $folder = realpath(dirname($xmlFileName));

        # если ничего не вытащим из XML, будем считать что PDF имеет то же имя
        $ret = substr($xmlFileName, 0, -4) . '.pdf';

        if(($xml)) {
            if(isset($xml->templatefiles)) {
                # exit(__FILE__ .':'.__LINE__.' data:<pre>' . print_r($xml->templatefiles,1) . '</pre>');
                foreach($xml->templatefiles->children() as $oneItem) {
                    if(isset($oneItem['src'])) {
                        return $folder . '/'. (string)$oneItem['src'];
                    }
                }
            }
        }
        return $ret;
        # return (is_file($ret)? $ret : '');

    }
}


<?php
/**
* @package waCRUDer
* @name waCRUDer.write.xml.php
* Writing table definition to XML file
*/

class WaCRUDerWriteXml extends WaCRUDerWriteDef { # reading table definition from "TPL" file

	static $OUT_CHARSET = 'UTF-8';
	const  XML_STARTING = '<?xml version="1.0" encoding="UTF-8"?>';
	static $EOL = "\r\n";

    private $fout = 0;

	protected $_err_code = 0;
    public function getFileExt() { return 'xml'; }

    public static function encodeValue($value) {
        $safeStr = htmlspecialchars($value,ENT_COMPAT, self::$OUT_CHARSET);
        # $safeStr = str_replace('"','APO', $safeStr);
        return $safeStr;
    }
	public function write($obj, $outfile, $srccset='') {
		$this->fout = fopen($outfile, 'w');
		if (!$this->fout) {
			$this->_err_code = WaCRUDer::ERR_FILE_NOT_WRITABLE;
			return false;
		}

		if (!empty($obj->charset)) $srccset = $obj->charset;

		file_put_contents("$outfile.obj", print_r($obj, 1));

		fwrite($this->fout, self::XML_STARTING . self::$EOL);
		fwrite($this->fout, '<WACRUDER>' . self::$EOL);

/*		if (!empty($obj->baseuri))
			fwrite($this->fout, "  <baseuri>" . $obj->baseuri . '</baseuri>' . self::$EOL);
*/
		if (!empty($obj->id))  $this->_wtag('id',$obj->id);
		if (!empty($obj->tabletype))  $this->_wtag('type',$obj->tabletype);
		if (!empty($obj->desc))  $this->_wtag('desc',waCRUDer::toUtf($obj->desc, $srccset));
		if (!empty($obj->shortdesc))  $this->_wtag('shortdesc',waCRUDer::toUtf($obj->shortdesc, $srccset));

		if (!empty($obj->browsefilter) && is_array($obj->browsefilter) && count($obj->browsefilter))
            $this->_wtag('browsefilter',$obj->browsefilter);

		if (!empty($obj->browsefilter_fn)) $this->_wtag('browsefilterfn',$obj->browsefilter_fn);

		if (empty($obj->_drawbrheader)) $this->_wtag('hidegridheader',1);

		if (!empty($obj->browseorder)) $this->_wtag('hidegridheader',$obj->browseorder);

		if (!empty($obj->tbrowseid) && $obj->tbrowseid!==$obj->id)
			$this->_wtag('browseid',$obj->tbrowseid);

		if ($obj->rpp != WaTableDef::$default_rpp) $this->_wtag('rpp',$obj->rpp);

		if (!empty($obj->browsewidth)) $this->_wtag('browsewidth',$obj->browsewidth);

		if (count($obj->parenttables)) $this->_wtag( 'parenttables', $obj->parenttables);

		if (!empty($obj->editformwidth) && $obj->editformwidth !=='100%')
            $this->_wtag( 'editformwidth', $obj->editformwidth);

		if (!empty($obj->search))  $this->_wtag( 'search', $obj->search);

		if (!empty($obj->searchcols))  $this->_wtag( 'searchcols', $obj->searchcols);

		if (!empty($obj->dropunknown)) $this->_wtag( 'dropunknown', $obj->dropunknown);
		if (!empty($obj->debug)) $this->_wtag( 'debug', $obj->debug);

		if (!empty($obj->safrm)) $this->_wtag( 'safrm', $obj->safrm);
		if (!empty($obj->confirmdel)) $this->_wtag( 'confirmdel', $obj->confirmdel);
		if (!empty($obj->windowededit)) $this->_wtag( 'windowededit', $obj->windowededit);
		if (!empty($obj->rowclassfunc)) $this->_wtag( 'rowclassfunc', $obj->rowclassfunc);
		if (!empty($obj->pagelinks)) $this->_wtag( 'pagelinks', $obj->pagelinks);
		if (!empty($obj->_adjacentLinks) && $obj->_adjacentLinks!=WaTableDef::$default_adjlinks)
			$this->_wtag( 'adjacentlinks', $obj->_adjacentLinks);
		if (!empty($obj->brenderfunc)) $this->_wtag( 'brenderfunc', $obj->brenderfunc);
		if (!empty($obj->recdeletefunc)) $this->_wtag( 'recdeletefunc', $obj->recdeletefunc);
		if (!empty($obj->updatefunc)) $this->_wtag( 'updatefunc', $obj->updatefunc);
		if (!empty($obj->editorform)) $this->_wtag( 'editorform', $obj->editorform);
		if (!empty($obj->editsubform)) $this->_wtag( 'editsubform', $obj->editsubform);
		if (!empty($obj->aftereditsubform)) $this->_wtag( 'aftereditsubform', $obj->aftereditsubform);
		if (!empty($obj->editmode)) $this->_wtag( 'editmode', $obj->editmode);
		if (!empty($obj->beforeedit)) $this->_wtag( 'beforeedit', $obj->beforeedit);
		if (!empty($obj->clonable))
			$this->_wtag( 'editmode', $obj->editmode, array('infield'=>$obj->clonable_field));
		if (!empty($obj->onsubmit)) $this->_wtag( 'onsubmit', $obj->onsubmit);
		if (!empty($obj->afteredit)) $this->_wtag( 'afteredit', $obj->afteredit);
		if (!empty($obj->afterupdate)) $this->_wtag( 'afterupdate', $obj->afterupdate);
		if (!empty($obj->_auditing)) $this->_wtag( 'auditing', $obj->_auditing);


        # save field definitions
        fwrite($this->fout, '  <fields>' . self::$EOL);
        foreach($obj->fields as $fid => $fld) {
            # fwrite($this->fout, self::$EOL . "field $fid:" . print_r($fld,1)); # debug
            if (!empty($fld->derived)) continue;
			$farr = array('id'=>$fid);
			if (!empty($fld->name)) $farr['name'] = $fld->name;
			if (!empty($fld->type)) $farr['type'] = $fld->type;
			if (!empty($fld->subtype)) $farr['subtype'] = $fld->subtype;
            if ( in_array($fid, $obj->_pkfields) )
                $farr['primary'] = '1';
			if (!empty($fld->_autoinc)) $farr['autoinc'] = $fld->_autoinc;
			if (!empty($fld->length)) $farr['length'] = $fld->length;
			if (!empty($fld->desc)) $farr['desc'] = waCRUDer::toUtf($fld->desc, $srccset);
			else $farr['desc'] = $fid;
			if (!empty($fld->shortdesc)) {
				$farr['shortdesc'] = waCRUDer::toUtf($fld->shortdesc, $srccset);
				if ( $farr['shortdesc'] === $farr['desc']) unset($farr['shortdesc']);
			}
			if (!empty($fld->notnull)) $farr['notnull'] = $fld->notnull;
			if (!empty($fld->defvalue)) $farr['defvalue'] = $fld->defvalue;
			if (isset($fld->showcond)) $farr['showcond'] = $fld->showcond;
			if ($fld->showformula!=='') $farr['showformula'] = $fld->showformula;
			if (isset($fld->showattr) && count($fld->showattr)) {
                $farr['showattr'] = implode(',', $fld->showattr);
                if ($farr['showattr'] === ',,,') unset($farr['showattr']);
            }
			if (isset($fld->editcond)) $farr['editcond'] = $fld->editcond;
			if (isset($fld->edittype)) $farr['edittype'] = $fld->edittype;
			if (!empty($fld->editoptions)) {
                # WriteDebugInfo("$fid: editoptions:", $fld->editoptions);
                if (is_array($fld->editoptions)) {
                    if (in_array($fld->edittype, ['SELECT', 'BLIST'])) {
                        $eOptions = array_shift($fld->editoptions);
                        $farr['editoptions'] = waCRUDer::toUtf(self::encodeValue($eOptions));
                        # $farr['editoptions'] = array_shift($fld->editoptions);
                    }
					if (!empty($fld->editoptions[0]))
						$farr['editattribs'] = waCRUDer::toUtf(self::encodeValue($fld->editoptions[0]), $srccset);
				}
                if (!empty($fld->idx_name)) $farr['idxname'] = $fld->idx_name;
			}

			if (!empty($fld->inputtags)) $farr['inputtags'] = $fld->inputtags;

			if (!empty($fld->showhref)) $farr['showhref'] = $fld->showhref;
			if (!empty($fld->hrefaddon)) $farr['hrefaddon'] = $fld->hrefaddon;
			if (!empty($fld->idx_name)) $farr['idx_name'] = $fld->idx_name;
			if (!empty($fld->idx_unique)) $farr['idx_unique'] = $fld->idx_unique;
			if (!empty($fld->afterinputcode)) $farr['afterinputcode'] = $fld->afterinputcode;

			$strk = "    <field";
			foreach($farr as $id=>$val) { $strk .= " $id=\"$val\""; }
			$strk .=" />";
			# indexes

			fwrite($this->fout, $strk.self::$EOL);
		}
		fwrite($this->fout, '  </fields>' . self::$EOL);

        if (!empty($obj->_jscode))
            fwrite($this->fout, "<script><![CDATA[" . self::$EOL
                . waCRUDer::toUtf($obj->_jscode, $srccset) . self::$EOL
                . ']]></script>' . self::$EOL
            );


		if (isset($obj->indexes)) {
            $idxaccum = '';
			foreach($obj->indexes as $idx) {
                if ($idx->derived) continue;
                # WriteDebugInfo('one index:', $idx);
				$idxstrg = "name=\"$idx->name\" expr=\"$idx->expr\"";

				if ($idx->unique) $idxstrg .= " unique=\"$idx->unique\"";
				if ($idx->descending) $idxstrg .= " descending=\"$idx->descending\"";

				$idxaccum .= "    <index  $idxstrg />" . self::$EOL;
			}
			if ($idxaccum)
				fwrite($this->fout, "  <indexes>" . self::$EOL . $idxaccum . "  </indexes>" . self::$EOL);
		}
		if (count($obj->childtables)) {
			fwrite($this->fout, "  <childtables>" . self::$EOL);
			foreach($obj->childtables as $item) {  # table,field,childfield,condition,protect,_func
				if (!empty($item['_func'])) contiue;
				$cttag = "    <childtable name=\"$item[table]\" field=\"$item[field]\" childfield=\"$item[childfield]\"";
				if (!empty($item['condition'])) $cttag .= " condition=\"$item[condition]\"";
				if (!empty($item['protect'])) $cttag .= " protect=\"" . waCRUDer::toUtf($item['protect'], $srccset). '"';
				$cttag .= " />";
				fwrite($this->fout, $cttag . self::$EOL);
			}
			fwrite($this->fout, "  </childtables>" . self::$EOL);
		}
		if (!empty($obj->childtables_fn)) $this->_wtag('childtablesfn', $obj->childtables_fn);

		if (count($obj->ftindexes)) {
			fwrite($this->fout, "  <ftindexes>" . self::$EOL);
			foreach($obj->ftindexes as $id => $item) {  # table,field,childfield,condition,protect,_func
				fwrite($this->fout, "    <ftindex name=\"$id\" fields=\"$item\" >" . self::$EOL);
			}
			fwrite($this->fout, "  </ftindexes>" . self::$EOL);
		}
		# custom columns
		if (isset($obj->customcol)) {
            $accum = '';
			foreach($obj->customcol as $item) {
                if (!empty($item['derived'])) continue;
#                WriteDebugInfo('one customcol:', $item);
				$strg = "htmlcode=\"" . waCRUDer::toUtf(self::encodeValue($item['htmlcode']),$srccset) . "\"";
				if (!empty($item['title'])) $strg .= " title=\"" . waCRUDer::toUtf($item['title'], $srccset) . '"';
				if (!empty($item['addon'])) $strg .= " addon=\"" . waCRUDer::toUtf($item['addon'], $srccset) . '"';

				$accum .= "    <customcol  $strg />" . self::$EOL;
			}
			if ($accum)
				fwrite($this->fout, "  <customcolumns>" . self::$EOL . $accum . "  </customcolumns>" . self::$EOL);

		}
        if ($obj->recursive) {
        	$attr = empty($obj->recursive_show) ? false: array('show'=>$obj->recursive_show);
        	$this->_wtag('recursive', $obj->recursive, $attr);
		}
		if ($obj->_multiselect) {
        	$attr = empty($obj->_multiselectFunc) ? false: array('func'=>$obj->_multiselectFunc);
        	$this->_wtag('multiselect', 1, $attr);
		}

		fwrite($this->fout, '</WACRUDER>' . self::$EOL);
		fclose($this->fout);

		$this->_err_code = 0;
		return true;
	}

	private function _wtag($tag, $value, $attribs = false, $level=1) {
        if (is_array($value)) $value = implode(',', $value);
        $attr = '';
        if (is_array($attribs)) {
        	foreach($attribs as $k=>$v) {
                if (is_array($v)) $v = implode(',', $v);
        		$attr.= " $k=\"" . self::encodeValue($v) . '"';
			}
		}
		fwrite($this->fout, str_repeat('  ',$level) ."<$tag{$attr}>" . self::encodeValue($value, TRUE). "</$tag>" . self::$EOL);
	}
	/**
	* encode "<", ">", ["] and ['] into hex strings to avoid bad XML values inside " ... "
	*
	* @param mixed $src
	*/
	public static function XmlSerialize($src) {
		return str_replace(
		   array('<', '>', '"', "'")
		  ,array('/x01', '/x02', '/x03', '/x04')
		  ,$src
		);

	}
}
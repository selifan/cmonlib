<?php
/**
* @package ImageFx
* @name imageFx.php
* Creating effects over images
* @author Alexander Selifonov <alex@selifan.ru>
* @copyright Alexander Selifonov <alex@selifan.ru>
* @link https://www.github.com/selifan
* @version 0.1.001
* modified 2016-10-25
*/
class ImageFx {

	private $_width=0, $_height=0;
	private $_method = '';
	private $_bwmode = 0;
	private $_thresholds = array();
	private $_outfile = '';
	private $_imgh = 0, $hOut = 0;
	private $_chained = false;
	private $_verbose = 1; # show some executing-time messages
	private $_factor = 0;
	static $MAX_COLORVALUE = 255;

	public function setChained($b_chain = true) {
		$this->_chained = $b_chain;
	}
	public function apply($image, $outname='', $params=0) {

		$started = self::microtimeFloat();

		$this->_imgh = (is_file($image)) ? $this->_openImageFile($image) : $image;
		if (!is_resource($this->_imgh)) die ('Source file not supported or is not an image');
		$this->_hOut = imagecreatetruecolor($this->_width,$this->_height);
		imagecopy($this->_hOut, $this->_imgh,0,0,0,0,$this->_width, $this->_height);
		if (is_array($params)) {
			if (isset($params[0])) {
				foreach($params as $pno => $area) {
					if (is_numeric($pno)) $this->_handleArea($area);
				}
			}
			else $this->_handleArea($params);
		}
#		if (!empty($params['output'])) $this->_outfile = trim($params['output']);
		$this->_outfile = ($outname) ? $outname : "$image.jpg";
		$elapsed = number_format(self::microtimeFloat() - $started, 3);
		$result = imagejpeg($this->_hOut, $this->_outfile, 75);
		if (is_file($image)) imagedestroy($this->_imgh);
		imagedestroy($this->_hOut);
		if ($this->_verbose)
		 	echo ("$this->_outfile created, dimensions: $this->_width x $this->_height, work time: $elapsed sec.");
		return $result; # if working from
	}

	/**
	* applies chosen FX on one rectangle area
	*
	* @param mixed $params associative array witj 'points','method', 'thresholds', 'bw' elements
	*/
	private function _handleArea($params) {

		$this->_method = isset($params['method']) ? trim($params['method']) : '';
		$this->_bwmode = isset($params['bw']) ? $params['bw'] : 0;
		$this->_thresholds = (isset($params['thresholds']) && is_array($params['thresholds'])) ?
			$params['thresholds'] : array(4, 25, 70, 120, 195);
		$xfrom = $yfrom = 0;
		$this->_factor = isset($params['factor']) ? $params['factor'] : 0.5;

		$xto = $this->_width - 1;
		$yto = $this->_height - 1;
		if (!empty($params['areatype']) && $params['areatype'] === 'polygon') {
			if(!isset($params['points']) || !is_array($params['points']) || count($params['points']) < 6)
				return;
			$poly = array();
			$minX = $this->_width - 1;
			$minY = $this->_height - 1;
			$maxX = $maxY = 0;
			for($kk=0; $kk<count($params['points'])-1; $kk+=2) {
				$poly[] = array(
				   ($thisX = $this->_calcX($params['points'][$kk])),
				   ($thisY = $this->_calcY($params['points'][$kk+1]))
				);
				$minX = min($minX, $thisX);
				$maxX = max($maxX, $thisX);
				$minY = min($minY, $thisY);
				$maxY = max($maxY, $thisY);
			}
			if ($this->_verbose) echo "min & max for poly: $minX,$minY - $maxX, $maxY\n";
#			echo 'created poly:'; print_r($poly); return;
			for ($x = $minX; $x<= $maxX; $x++) {
				for ($y = $minY; $y<= $maxY; $y++) {
					if ($this->insidePolygon(array($x,$y), $poly))
						$this->_applyToPixel($x,$y);
				}
			}
		}
		else { # simple rectangle defined by left,upper,right, bottom coords
			if (isset($params['points'])) {
				$xfrom = isset($params['points'][0]) ? $this->_calcX($params['points'][0]) : 0;
				$yfrom = isset($params['points'][1]) ? $this->_calcY($params['points'][1]) : 0;
				$xto = isset($params['points'][2]) ? $this->_calcX($params['points'][2]) : $xfrom;
				$yto = isset($params['points'][3]) ? $this->_calcY($params['points'][3]) : $yfrom;
			}
	#		die ("widtx x height = $this->_width x $this->_height, final coords: $xfrom, $yfrom, $xto, $yto\r\n");
			for($x = $xfrom; $x < $xto; $x++) {

				for($y = $yfrom; $y < $yto; $y++) {
                	$this->_applyToPixel($x, $y);
				}
			}
		}
	}

	private function _applyToPixel($x,$y) {

		if ($this->_chained) {
			$pix = imagecolorat($this->_hOut,$x, $y);
			$clr = imagecolorsforindex($this->_hOut, $pix);
		}
		else {
			$pix = imagecolorat($this->_imgh,$x, $y);
			$clr = imagecolorsforindex($this->_imgh, $pix);
		}

		$mcolor = array($clr['red'], $clr['green'], $clr['blue']);
#		$a = $clr['alpha']; # TODO: use alpha if in and out images have alpha channel
		if ($this->_bwmode)
			$mcolor = $this->getGrayScale($mcolor);
		switch(strtolower($this->_method)) {
			case 'quantize':
				$mcolor = $this->_quantizeColor($mcolor);
				break;
			case 'colormask':
				$mcolor = $this->_colorMask($mcolor);
				break;
			default:
				break;
		}
		$outpix = imagecolorallocate($this->_hOut, $mcolor[0], $mcolor[1], $mcolor[2]);
		imagesetpixel ($this->_hOut, $x ,$y, $outpix);
	}

	private function _calcX($xpos) {
		$ret = floatval($xpos);
		if ($ret>0 && $ret <1) $ret = round($this->_width * $ret);
		elseif (substr($xpos,-1)==='%') $ret = round($this->_width * $ret/100);
		return (min($ret,$this->_width-1));
	}

	private function _calcY($ypos) {
		$ret = floatval($ypos);
		if ($ret>0 && $ret <1) $ret = round($this->_height * $ret);
		elseif (substr($ypos,-1)==='%') $ret = round($this->_height * $ret/100);
		return min($ret,$this->_height-1);
	}

	private function _quantizeColor($clr) {
		$ret = array();
		foreach($clr as $no => $color) {
			if ($color < $this->_thresholds[0]/5) $ret[$no] = 0;
			else {
				$ret[$no] = self::$MAX_COLORVALUE;
				foreach($this->_thresholds as $limvalue) {
					if ($color <= $limvalue) {
						$ret[$no] = $limvalue;
						break;
					}
				}
			}
		}
		return $ret;
	}

	/**
	* apply "factor" values to each color in the pixel
	*
	* @param mixed $clr array, [r,g,b] values
	*/
	private function _colorMask($clr) {
		$ret = array();
		$tfact = is_scalar($this->_factor)? array($this->_factor,$this->_factor, $this->_factor)
		  : array_values($this->_factor);
		foreach($clr as $no => $color) {
			$nf = isset($tfact[$no]) ? $tfact[$no] : 0;
			$ret[$no] = min($color * $nf, self::$MAX_COLORVALUE);
		}
		return $ret;
	}
	public function getGrayScale($clr) {
		$y = min(1, (0.3 * $clr[0]/self::$MAX_COLORVALUE + 0.59 * $clr[1]/self::$MAX_COLORVALUE
		    + 0.11 * $clr[2]/self::$MAX_COLORVALUE));
        if (is_array($this->_bwmode))
            $ret = array(
                round($y * $this->_bwmode[0])
                ,(isset($this->_bwmode[1]) ? round($y * $this->_bwmode[1]) : 0)
                ,(isset($this->_bwmode[2]) ? round($y * $this->_bwmode[2]) : 0)
            );
        else
		    $ret = array(($rnew=round($y*self::$MAX_COLORVALUE)), $rnew, $rnew);
		return $ret;
	}
	private function _openImageFile($src) {
        $fext = strtolower( substr($src, strrpos($src, '.')));
        $justname = basename($src);
        $img = false;

        switch( $fext) {
        	case '.jpg': case '.jpeg':
				$img = imagecreatefromjpeg($src);
				break;
        	case '.png':
				$img = imagecreatefrompng($src);
				break;
        	case '.gif':
				$img = imagecreatefromgif($src);
				break;
        	case '.gd2':
				$img = imagecreatefromgd2($src);
				break;
		}
		if ($img) {
			$this->_width = imagesx($img);
			$this->_height = imagesy($img);
		}
		return $img;
	}

	private static function microtimeFloat()
	{
	    list($usec, $sec) = explode(" ", microtime());
	    return ((float)$usec + (float)$sec);
	}
	/**
	* Detects if passed point is inside polygon
	* source: http://tutorialspots.com/php-detect-point-in-polygon-506.html
	* @param mixed $point
	* @param mixed $polygon
	*/
	public function insidePolygon($point, $polygon) {
	    if($polygon[0] != $polygon[count($polygon)-1])
	        $polygon[count($polygon)] = $polygon[0];
	    $j = 0;
	    $oddNodes = false;
	    $x = $point[1];
	    $y = $point[0];
	    $n = count($polygon);
	    for ($i = 0; $i < $n; $i++)
	    {
	        $j++;
	        if ($j == $n) {
	        	$j = 0;
	        }
	        if ((($polygon[$i][0] < $y) && ($polygon[$j][0] >= $y)) ||
	           (($polygon[$j][0] < $y) && ($polygon[$i][0] >= $y))) {
	            if ($polygon[$i][1] + ($y - $polygon[$i][0]) / ($polygon[$j][0] - $polygon[$i][0]) * ($polygon[$j][1] -
	                $polygon[$i][1]) < $x) {
	                $oddNodes = !$oddNodes;
	            }
	        }
	    }
	    return $oddNodes;
	}

}
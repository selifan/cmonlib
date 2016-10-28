<?php
/**
* @package ImageFx
* @name imageFx.php
* Creating effects over images
* @author Alexander Selifonov <alex@selifan.ru>
* @copyright Alexander Selifonov <alex@selifan.ru>
* @link https://www.github.com/selifan
* @version 0.02.003
* modified 2016-10-28
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
	private $_mozaic = array('stepx'=>0, 'stepy'=>0, 'avgcolor'=>false, 'curcoord'=>array(-1,-1));
    private $_imgSrc = false;
    private $_stroke = false;
    private $_sendToStream = false; # output generated image directly to client response

	static $MAX_COLORVALUE = 255;
	static $DEFAULT_STEP   = 20;
    static $JPG_QUALITY = 75;

	public function setChained($b_chain = true) {
		$this->_chained = $b_chain;
		return $this;
	}

	public function sendToStream($b_send = true) {
		$this->_sendToStream = $b_send;
		return $this;
	}
	public function apply($image, $outname='', $params=0) {

		$started = self::microtimeFloat();

        if ($this->_sendToStream) $this->_verbose = 0;

		$this->_imgh = (is_file($image)) ? $this->_openImageFile($image) : $image;
		if (!is_resource($this->_imgh)) die ('Wrong source image file name or resource passed');
		$this->_hOut = imagecreatetruecolor($this->_width,$this->_height);
		imagecopy($this->_hOut, $this->_imgh,0,0,0,0,$this->_width, $this->_height);

		$this->_imgSrc = ($this->_chained) ? $this->_hOut : $this->_imgh;

		if (is_array($params)) {
			if (isset($params[0])) {
				foreach($params as $pno => $area) {
					if (is_numeric($pno)) $this->_handleArea($area);
				}
			}
			else $this->_handleArea($params);
		}
#		if (!empty($params['output'])) $this->_outfile = trim($params['output']);
		$this->_outfile = ($outname) ? $outname : "generated.jpg";
		$elapsed = number_format(self::microtimeFloat() - $started, 3, '.', '`');

		$outext = strtolower(substr($this->_outfile, strrpos($this->_outfile, '.')+1));
        $justname = basename($this->_outfile);
		switch($outext) {
            case 'png':
				imagealphablending($this->_hOut, false);
				imagesavealpha($this->_hOut, true); // saving transparent pixels

                if ($this->_sendToStream) {
					if (!empty($_SERVER['REMOTE_ADDR']) && !headers_sent()) {
						header('Content-Type: image/png');
        				header("Content-Disposition: attachment; filename=\"$justname\"");
					}
					$result = imagepng($this->_hOut, null, 0);
				}
				else
					$result = imagepng($this->_hOut, $this->_outfile, 0);
				break;

            case 'gif':
                if ($this->_sendToStream) {
					if (!empty($_SERVER['REMOTE_ADDR']) && !headers_sent()) {
						header('Content-Type: image/gif');
        				header("Content-Disposition: attachment; filename=\"$justname\"");
					}
					$result = imagegif($this->_hOut);
				}
				else
					$result = imagegif($this->_hOut, $this->_outfile);
				break;

			case 'jpg': case 'jpeg': default:
                if ($this->_sendToStream) {
					if (!empty($_SERVER['REMOTE_ADDR']) && !headers_sent()) {
						header('Content-Type: image/jpeg');
        				header("Content-Disposition: attachment; filename=\"$justname\"");
					}
					$result = imagejpeg($this->_hOut, null, self::$JPG_QUALITY);
				}
				else
					$result = imagejpeg($this->_hOut, $this->_outfile, self::$JPG_QUALITY);
				break;
		}

		imagedestroy($this->_hOut);
		if (is_file($image)) imagedestroy($this->_imgh);

		$this->_imgh = $this->_hOut = $this->_imgSrc = null;

		if ($this->_verbose)
		 	echo ("$this->_outfile created, $this->_width x $this->_height px, work time: $elapsed sec.");
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
		if ($this->_method === 'mozaic') {
            if (!isset($params['step'])) {
            	$this->_mozaic['stepx'] = $this->_mozaic['stepy'] = $this->_calcX(self::$DEFAULT_STEP);
			}
			elseif(is_array($params['step'])) {
				$this->_mozaic['stepx'] = $this->_calcX($params['step'][0]);
				$this->_mozaic['stepy'] = isset($params['step'][1]) ?
					$this->_calcY($params['step'][1]) : $this->_calcY($params['step'][0]);
			}
			elseif(is_numeric($params['step'])) {
            	$this->_mozaic['stepx'] = $this->_mozaic['stepy'] = $this->_calcX($params['step']);
			}
            $this->_stroke = isset($params['stroke']) ? $params['stroke'] : false;

            # avoid too small cell sizes:
			$this->_mozaic['stepx'] = max(4,$this->_mozaic['stepx']);
			$this->_mozaic['stepy'] = max(4,$this->_mozaic['stepy']);

			$this->_mozaic['avgcolor'] = false;
			$this->_mozaic['curcoord'] = array(-1,-1);
		}

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
			#echo 'created poly:'; print_r($poly); return;
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

			for($x = $xfrom; $x <= $xto; $x++) {

				for($y = $yfrom; $y <= $yto; $y++) {
                	$this->_applyToPixel($x, $y);
				}
			}
		}
	}

	private function _applyToPixel($x,$y) {

		$clr = imagecolorsforindex($this->_imgSrc, imagecolorat($this->_imgSrc,$x, $y));

		$mcolor = array($clr['red'], $clr['green'], $clr['blue'], $clr['alpha']);
#		$a = $clr['alpha']; # TODO: use alpha if in and out images have alpha channel
		if ($this->_bwmode)
			$mcolor = $this->getGrayScale($mcolor);

		switch(strtolower($this->_method)) {
            case 'grayscale':
            	break;
			case 'quantize':
				$mcolor = $this->_quantizeColor($mcolor);
				break;
			case 'colormask':
				$mcolor = $this->_colorMask($mcolor);
				break;
			case 'mozaic':
				$mcolor = $this->_mozaic($mcolor, $x, $y);
				break;
			default:
				break;
		}
		$outpix = imagecolorallocate($this->_hOut, $mcolor[0], $mcolor[1],$mcolor[2]);
		imagesetpixel ($this->_hOut, $x ,$y, $outpix);
	}

	private function _calcX($xpos) {
		$ret = floatval($xpos);
		if ($ret>0 && $ret <1) $ret = round($this->_width * $ret);
		elseif (substr($xpos,-1)==='%') $ret = round($this->_width * $ret/100);
		return min($ret,$this->_width-1);
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
		for($no=0; $no<3; $no++) { // $clr as $no => $color)
			$nf = isset($tfact[$no]) ? $tfact[$no] : 0;
			$ret[$no] = min($clr[$no] * $nf, self::$MAX_COLORVALUE);
		}
		if (isset($clr[3])) $ret[] = $clr[3];
		return $ret;
	}
	/**
	* convert "colored" pixel to "grayscale", in fact to mono-color :
	* if $this->_factor is a 3-element array, it's treated as final color.
	* For example with [255,0,0], "gray scale" will be "green scale" etc.
	* @param mixed $clr 3-element color values array [r,g,b]
	*/
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

		if (isset($clr[3])) $ret[] = $clr[3];
		return $ret;
	}
	/**
	* draw a rectangles of "average" color
	*
	* @param mixed $clr original pixel (in fact not needed here!)
	* @param mixed $x  pixel coordinate, s and y
	* @param mixed $y
	*/
	private function _mozaic($clr, $x, $y) {
		$cubeX = min($this->_width-1, floor($x / $this->_mozaic['stepx']) * $this->_mozaic['stepx']);
		$cubeY = min($this->_height-1, floor($y / $this->_mozaic['stepy']) * $this->_mozaic['stepy']);
		if ($this->_mozaic['curcoord'][0] != $cubeX || $this->_mozaic['curcoord'][1] != $cubeY) {
			$this->_mozaic['curcoord'] = array($cubeX, $cubeY);
			$x2 = min(($this->_width-1) , ($cubeX+$this->_mozaic['stepx']-1));
			$y2 = min(($this->_height-1), ($cubeY+$this->_mozaic['stepy']-1));

			$clr1 = imagecolorsforindex($this->_hOut, imagecolorat($this->_imgSrc,$cubeX, $cubeY));
			$clr2 = imagecolorsforindex($this->_hOut, imagecolorat($this->_imgSrc,$x2, $cubeY));
			$clr3 = imagecolorsforindex($this->_hOut, imagecolorat($this->_imgSrc,$cubeX, $y2));
			$clr4 = imagecolorsforindex($this->_hOut, imagecolorat($this->_imgSrc,$x2, $y2));

			$this->_mozaic['avgcolor'] = array(
				 round(($clr1['red']+$clr2['red']+$clr3['red']+$clr4['red'])/4)
				,round(($clr1['green']+$clr2['green']+$clr3['green']+$clr4['green'])/4)
				,round(($clr1['blue']+$clr2['blue']+$clr3['blue']+$clr4['blue'])/4)
				,round(($clr1['alpha']+$clr2['alpha']+$clr3['alpha']+$clr4['alpha'])/4)
			);
		}

		$ret = $this->_mozaic['avgcolor'];
		if (!empty($this->_stroke)) {
			$sttype = isset($this->_stroke['type']) ? $this->_stroke['type'] : '3D';
			$stsize = isset($this->_stroke['size']) ? $this->_stroke['size'] : 1;
			$stcolor = isset($this->_stroke['color']) ? $this->_stroke['color'] : array(60,60,60); # R,G,B
			switch($sttype) {
				case '3D': case 'bewel':
					if ($y < ($cubeY + $stsize)) { # top border
						$ret = $this->_lighten($ret, 0.3);
					}
					if ($x < ($cubeX+$stsize)) { # left border
						$ret = $this->_lighten($ret, 0.3);
					}
					if ($x >= $cubeX+$this->_mozaic['stepx']-$stsize) { # right border
						$ret = $this->_darken($ret, 0.3);
					}
					if ($y >= $cubeY+$this->_mozaic['stepy']-$stsize) { # bottom border
						$ret = $this->_darken($ret, 0.3);
					}
			}
		}
		return $ret;
	}
	# make color "lighter" by factor, using "limiter" to avoid greater than 255 values
    private function _lighten($color, $factor) {
    	for($i=0; $i<3; $i++) {
    		$rest = self::$MAX_COLORVALUE - $color[$i];
    		$color[$i] += floor($rest * min(1,$factor));
		}
		return $color;
	}
	# make color "darker" by factor, factor = 0...1 (more is darker)
    private function _darken($color, $factor) {
    	for($i=0; $i<3; $i++) {
    		$color[$i] = round($color[$i] * (1-$factor));
		}
		return $color;
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
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
	private $_verbose = 1; # show some executing-time messages
	static $MAX_COLORVALUE = 255;
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
	* @param mixed $params associative array witj 'area','method', 'thresholds', 'bw' elements
	*/
	private function _handleArea($params) {

		$this->_method = isset($params['method']) ? trim($params['method']) : '';
		$this->_bwmode = !empty($params['bw']);
		$this->_thresholds = (isset($params['thresholds']) && is_array($params['thresholds'])) ?
			$params['thresholds'] : array(4, 25, 70, 120, 195);
		$xfrom = $yfrom = 0;
		$xto = $this->_width - 1;
		$yto = $this->_height - 1;
		if (isset($params['area'])) {
			$xfrom = isset($params['area'][0]) ? $this->_calcX($params['area'][0]) : 0;
			$yfrom = isset($params['area'][1]) ? $this->_calcY($params['area'][1]) : 0;
			$xto = isset($params['area'][2]) ? $this->_calcX($params['area'][2]) : $xfrom;
			$yto = isset($params['area'][3]) ? $this->_calcY($params['area'][3]) : $yfrom;
		}
#		die ("widtx x height = $this->_width x $this->_height, final coords: $xfrom, $yfrom, $xto, $yto\r\n");
		for($x = $xfrom; $x < $xto; $x++) {
			for($y = $yfrom; $y < $yto; $y++) {
				$pix = imagecolorat($this->_imgh,$x, $y);
				$clr = imagecolorsforindex($this->_imgh, $pix);
#				$r = ($pix >> 16) & 0xFF; $g = ($pix >> 8) & 0xFF; $b = $pix & 0xFF;
				$mcolor = array($clr['red'], $clr['green'], $clr['blue']);
				$a = $clr['alpha'];
				if ($this->_bwmode)
					$mcolor = self::getGrayScale($mcolor);
#				$outpix = imagecolorallocate($this->_hOut, $r, $g, 0); # cut BLUE form source
				switch($this->_method) {
					case 'quantize':
						$mcolor = $this->_quantizeColor($mcolor);
						break;
					case 'darken':
						$factor = isset($params['factor']) ? floatval($params['factor']) : 0.5;
						$mcolor = $this->_darkenColor($mcolor, $factor);
						break;
					default:
						break;
				}
				$outpix = imagecolorallocate($this->_hOut, $mcolor[0], $mcolor[1], $mcolor[2]);
				imagesetpixel ($this->_hOut, $x ,$y, $outpix);
			}
		}

	}
	private function _calcX($xpos) {
		$ret = floatval($xpos);
		if ($ret>0 && $ret <1) $ret = $this->_width * $ret;
		elseif (substr($xpos,-1)==='%') $ret = $this->_width * $ret/100;
		return (min($ret,$this->_width-1));
	}

	private function _calcY($ypos) {
		$ret = floatval($ypos);
		if ($ret>0 && $ret <1) $ret = $this->_height * $ret;
		elseif (substr($ypos,-1)==='%') $ret = $this->_height * $ret/100;
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

	private function _darkenColor($clr, $factor) {
		$ret = array();
		foreach($clr as $no => $color) {
			$ret[$no] = min($color * $factor, self::$MAX_COLORVALUE);
		}
		return $ret;
	}
	public static function getGrayScale($clr) {
		$y = min(1, (0.3 * $clr[0]/self::$MAX_COLORVALUE + 0.59 * $clr[1]/self::$MAX_COLORVALUE
		    + 0.11 * $clr[2]/self::$MAX_COLORVALUE));
		$ret = array(round($y*self::$MAX_COLORVALUE), round($y*self::$MAX_COLORVALUE),
		    round($y*self::$MAX_COLORVALUE));
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

}
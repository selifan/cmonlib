<?php
/**
* Web Service wrapper
* @author Alex "Selifan"
* @license MIT
*/
class WaService {

	const ERR_TOKEN_NOT_PASSED = 1301;
	const ERR_TOKEN_WRONG      = 1302;
	const WRK_DEBUG = 0;
	private $output = 'json';
	private $_username = '';
	// error messages can be localized by WaService::localize($array)
	private static $_error_messages = array(
		'e1301' => 'User token not passed. Authorization failed',
		'e1302' => 'Wrong User token passed. Authorization failed',
		'e1303' => 'Another error'
	);
	private $_module = null;
	private $_request = array();
	private $users = array();
	private $_data = array();
	private $_errorcode = 0;
	private $_logging = 0;

	/**
	* Adding one user info
	*
	* @param mixed $username User name
	* @param mixed $token unique user Token
	* @param mixed $ipaddresses allowed remote IP addresses for this user
	*/
	public function addUser($token, $username, $ipaddresses='') {
		$this->users[$token] = array(
			'username' => $username,
			'ipaddr'   => $ipaddresses
		);
#		echo "user $token added<br>";
	}

	public function setLogging($param) {
		$this->_logging = $param;
	}
	public function attachModule(&$moduleObj) {
		$this->_module =& $moduleObj;
	}

	public static function localize($strarr) {

		if (is_array($strarr)) self::$_error_messages = array_merge(self::$_error_messages, $strarr);
	}

	public function setError($errno) {
		$this->_errorcode = $errno;
	}

	public function getErrorMessage() {
		if (isset(self::$_error_messages['e'.$this->_errorcode]))
			return self::$_error_messages['e'.$this->_errorcode];
		return $this->_errorcode;
	}

	/**
	* Entry point : handle http request with POSTed data (json in $_POST['jsondata'] or assoc.array)
	* @param mixed $params user params or null to fetch $_GET + $_POST
	* @return json string with answer ('result' => 'OK' or 'ERROR')
	*
	*/
	public function request($params = null) {
		$this->_request = ($params) ? $params : array_merge($_GET,$_POST);
		if (isset($this->_request['jsondata'])) {
			$this->_data = json_decode($this->_request['jsondata'], true);
		}
		else
			$this->_data = $this->_request;

		if(self::WRK_DEBUG)
			file_put_contents('_waservice_params.log', print_r($this->_data,1));

		$action = isset($this->_data['action']) ? $this->_data['action'] : '';

		if (isset($this->_data['resulttype'])) {
			$this->output = $this->_data['resulttype'];
		}

		$ok = $this->authorize();
		if (!$ok) {
			$result = array('result'=>'ERROR', 'message'=>$this->getErrorMessage($this->_errorcode));
		}
		else {
			$action = isset($this->_data['action']) ? $this->_data['action'] : '';
			if (empty($action))
				$result = array('result'=>'ERROR','errormessage'=>'No action passed');
			else {

				$result = array('result'=>'OK');
				if (method_exists($this->_module, $action)) {
					$rdata = $this->_module->$action($this->_data);
					if ($rdata)
						$result['data'] = $rdata;
					else $result = array(
					  'result' => 'ERROR',
					  'message' => $this->_module->getErrorMessage()
					  );
				}
				else $result = array('result'=>'ERROR', 'message'=>'Undefined action : '.$action);

			}
		}
		if (($this->_logging) && is_callable($this->_logging)) {
			$logstr = ($this->_username) ? "User: " . $this->_username : '';
			$logstr .= " request: ".$action . ", result: ".$result['result'] .
				(!empty($result['message']) ? (', message: '.$result['message']) : '');
			call_user_func($this->_logging, $logstr);
		}
		return $this->prepareResponse($result);
	}

	public function prepareResponse($result) {

		if ($this->output==='print_r')
			$ret = '<pre>'.print_r($result,1) . '</pre>';

		elseif ($this->output==='json')
			$ret = constant('JSON_UNESCAPED_UNICODE') ? json_encode($result, JSON_UNESCAPED_UNICODE)
			: json_encode($result);

		return $ret;
	}

	/**
	* Authorize user by checking passed token
	*
	*/
	public function authorize() {

		if (!isset($this->_data['usertoken'])) {
			$this->_errorcode = self::ERR_TOKEN_NOT_PASSED;
			return false;
		}
		$token = $this->_data['usertoken'];
		if (!isset($this->users[$token])) {
			$this->_errorcode = self::ERR_TOKEN_WRONG;
			return false;
		}
		$this->_username = $this->users[$token]['username'];
		return true;
	}

}
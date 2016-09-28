<?php
/**
* Web Service wrapper
*
*/
class WaService {

	private $_module = null;
	private $_request = array();
	private $_data = array();

	public function attachModule(&$moduleObj) {
		$this->_module = $moduleObj;
	}

	/**
	* Entry point : handle http request with POSTed data (json in $_POST['jsondata'] or assic.array)
	* @return json string with answer ('result' => 'OK' or 'ERROR')
	*
	*/
	public function request() {
		if (isset($_POST['jsondata'])) {
			$this->_data = json_decode($_POST['jsondata'], true);
		}
		else
			$this->_data = $_POST['data'];

		$action = isset($this->_data['action']) ? $this->_data['action'] : '';

		if (empty($action))
			$result = array('result'=>'ERROR','errormessage'=>'No action passed');
		else {
			$result = array('result'=>'empty');
			switch($action) {

				case 'getlists' :
					$result = $this->_module->getLists();
					break;

				case 'calculate':
					$result = $this->_module->calculate();
					break;

			}
		}
		return self::prepareResponse($result);
	}

	/**
	* Getting all lists used for request params
	*
	*/
	public function getLists() {

	}

}
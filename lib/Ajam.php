<?php

/*** *** *** *** *** *** *** *** *** *** *** *** *** *** *** *** 
Class to work with AJAM in Asterisk using PHP 5.x

Details:

You need to edit your Asterisk configuration files to enable the following

In http.conf:
	[general]
	enabled = yes
	prefix=asterisk
	enablestatic = yes

In manager.conf
	[general]
	enabled = yes
	webenabled = yes

In manager.conf create the manager user

Asterisk-1.4.x
	[admin]
	secret = test
	read = system,call,log,verbose,command,agent,user,config
	write = system,call,log,verbose,command,agent,user,config

Asterisk-1.6.x
	[admin]
	secret = test
	read=system,call,log,verbose,agent,user,config,dtmf,reporting,cdr,dialplan
	write=system,call,agent,user,config,command,reporting,originate

Asterisk-1.8 and higher
	[admin]
	secret = test
	read = system,call,log,verbose,command,agent,user,config,dtmf,reporting,cdr,dialplan,originate
	write = system,call,log,verbose,command,agent,user,config,dtmf,reporting,cdr,dialplan,originate

Make avaliable the ajam_cookie file/directory with read/write
permission to http user

License:
	@since 26/01/2009
	@author Carlos Alberto Cesario 
	@package AJAM
	@license http://www.gnu.org/copyleft/gpl.html GPL
	@filesource
	@access public

Version History:
	2009-01-26	version 0.2.1 by Carlos Alberto Cesario
	2014-01-15	version 0.2.2 by lgaetz
					forked from github project mta59066/php-ajam and license information added back
					added test to $config_arr['debug'] to see if user set to false

*** *** *** *** *** *** *** *** *** *** *** *** *** *** *** ***/ 


class Ajam
{

	/**
	 * Asterisk manager url address
	 *
	 * @var string
	 * @access private
	 */
	private $_urlraw = null;

	/**
	 * Asterisk manager user
	 *
	 * @var string
	 * @access private
	 */
	private $_usermanager;

	/**
	 * Asterisk manager secret
	 *
	 * @var string
	 * @access private
	 */
	private $_secretmanager;

	/**
	 * AJAM cookie file
	 *
	 * @var string
	 * @access private
	 */
	private $_cookiefile;

	/**
	 * AJAM authentication type
	 * plaintext or md5
	 *
	 * @var string
	 * @access private
	 */
	private $_authtype;

	/**
	 * Get result commands
	 *
	 * @var array
	 * @access private
	 */
	private $_result;

	/**
	 * Enable or disable debung commands
	 *
	 * Default: Disabled
	 *
	 * @var boolean
	 * @access private
	 */
	private $_debug;

	/**
	 * Ajam::setResult()
	 * Define variable $_result with a AJAM command result
	 *
	 * @access private
	 * @param array $data_arr Result of AJAM command
	 * @return void
	 */
	private function setResult($data_arr)
	{
		$this->_result = $data_arr;
	}

	/**
	 * Ajam::getResult()
	 *
	 * Get data of variable $_result
	 *
	 * @access public
	 * @return array
	 */
	public function getResult()
	{
		return $this->_result;
	}

	/**
	 * Ajam::__construct()
	 *
	 * Constructor method of class AJAM
	 *
	 * @access public
	 * @param aray $config_arr
	 * @return void
	 */
	public function __construct($config_arr)
	{
		// Check if phpversion is 5 or higher
		if (version_compare(PHP_VERSION, "5", "lt")) {
			die('Requires PHP 5 or higher');
		}
		// Verify if function curl_exists
		// its is needed by AJAM HTTP access
		if (!function_exists('curl_init')) {
			die('Php Curl module unavailable');
		}
		// Verify if the config variables is defined
		if (!$config_arr) {
			die('Error, please set the conection params');
		} else {
			$this->_urlraw = $config_arr['urlraw'];
			$this->_usermanager = $config_arr['admin'];
			$this->_secretmanager = $config_arr['secret'];
			$this->_authtype = $config_arr['authtype'];

			if (!$config_arr['cookiefile']) {
				$this->_cookiefile = 'ajam_cookie';
			} else {
				$this->_cookiefile = $config_arr['cookiefile'];
			}
			// Verify if cookie file exists
			// and have permissions to read/write
			if (file_exists($this->_cookiefile)) {
				if (!is_writable($this->_cookiefile)) {
					die("Change permission to read/write in file: $this->_cookiefile");
				}
				// If file not exists I try create it
			} else {
				if (@fopen($this->_cookiefile, 'w')) {
					@fclose($this->_cookiefile);
				} else {
					die("Change permission to read/write in file: $this->_cookiefile");
				}
			}
			if (isset($config_arr['debug']) && $config_arr['debug'] != false) {
				$this->_debug = true;
			}
		}
	}

	/**
	 * Ajam::parseRaw()
	 *
	 * Parser of result AJAM command
	 *
	 * @access private
	 * @param string $data Result of AJAM command
	 * @return array
	 */
	private function parseRaw($data)
	{
		$rows = explode("\n", $data);
		$int_cont = 0;
		$arr_ret['data'] = array();

		foreach ($rows as $str_line) {
			if ((preg_match('/Response:|Message:|Privilege:/i', $str_line))) {
				list($str_key, $str_val) = explode(": ", $str_line);

				/**
				 * There I create a array
				 * setting the response with your value
				 *
				 * Ex.
				 * $arr['Response'] = 'Success';
				 */
				$str_key = trim($str_key);
				$str_val = trim($str_val);
				if ($str_key) {
					$arr_ret[$str_key] = $str_val;
				}
			} else {
				$arr_ret['data'][$int_cont] = array();
				/**
				 * If the line is blank or have string
				 * END COMMAND, this line is set to null value
				 * and this line will not be added in array
				 */
				if ((preg_match('/^\s+|END COMMAND/i', $str_line))) {
					$int_cont++;
					$str_line = null;
				} else {
					/**
					 * If match : (two dots) in line,
					 * I again split the line by separator : to
					 * create a new array with the key and your value
					 */
					if (preg_match('/: /', $str_line)) {
						list($str_key, $str_val) = explode(": ", $str_line);
						$str_key = trim($str_key);
						$str_val = trim($str_val);

						/**
						 * If the line is not zero, and the array does not
						 * contain any item, and the key contains no value,
						 * I create an array with the key and value
						 *
						 * Eg.
						 * $arr_ret['data'][0] = [Code][010];
						 */
						if ((!is_null($str_line)) && (count($arr_ret["data"][$int_cont]) == 0) && ($str_key)) {
							$arr_ret["data"][$int_cont] = array($str_key => $str_val);
						} else {
							if ($str_key) {
								/**
								 * Else, I only increment the array
								 * with other key and value
								 */
								$arr_ret["data"][$int_cont][$str_key] = $str_val;
							}
						}
					} else {
					/**
					 * Otherwise I create an array with the entire line
					 */
						if ((!is_null($str_line)) && (count($arr_ret["data"][$int_cont]) == 0)) {
							$arr_ret["data"][$int_cont] = array(trim($str_line));
						} else {
							array_push($arr_ret["data"][$int_cont], trim($str_line));
						}
					}
				}
			}
		}
		array_pop($arr_ret["data"]);
		unset($int_cont);
		return $arr_ret;
	}

	/**
	 * Ajam::doLogin()
	 *
	 * Make the AJAM login
	 *
	 * @access private
	 * @param string $authtype Use plaintext or md5
	 * @return boolean
	 */
	private function doLogin($authtype)
	{
		switch (strtolower($authtype)) {
			case "plaintext":

				$query = $this->buildQuery("Login", array('Username' => $this->_usermanager,
					'Secret' => $this->_secretmanager));

				$data = $this->getResponse("login", $query);
				$raw_result = $this->parseRaw($data);
				if (isset($raw_result['Response']) && $raw_result['Response'] == "Success") {
					if ($this->_debug) {
						print_r($raw_result);
						echo "Login success: Type: 'plaintext' \n";
						echo "User> $this->_usermanager  -- Pass> $this->_secretmanager \n";
					}
					return true;
				} else {
					die('Error in login process: check username, password, url');
					return false;
				}

			case "md5":
				$query = $this->buildQuery("Challenge", array('Authtype' => 'md5'));
				$data = $this->getResponse("command", $query);
				$raw_result = $this->parseRaw($data);

				if ($raw_result['Response'] != "Success") {
					return false;
				} else {
					$challenge = $raw_result['data'][0]['Challenge'];

					$md5_key = md5($challenge . $this->_secretmanager);

					$query = $this->buildQuery("Login", array('AuthType' => 'MD5',
						'Username' => $this->_usermanager,
						'Key' => $md5_key));

					$data = $this->getResponse("login", $query);
					$raw_result = $this->parseRaw($data);
					if ($raw_result['Response'] == "Success") {
						if ($this->_debug) {
							print_r($raw_result);
							echo "Login success: Type: 'md5' \n";
							echo "User> $this->_usermanager  -- Pass> $this->_secretmanager \n";
						}
						return true;
					} else {
						die('Error in login process');
						return false;
					}
				}
		}
	}

	/**
	 * Ajam::getResponse()
	 *
	 * Execute HTTP post data to AJAM and get
	 * the command result
	 *
	 * @access private
	 * @param string $action_str Use login or command
	 * @param string $query_str Complete url query
	 * @return string
	 */
	function getResponse($action_str, $query_str)
	{
		switch (strtolower($action_str)) {
			case "login":
				$cur_resource = curl_init($query_str);
				@curl_setopt($cur_resource, CURLOPT_RETURNTRANSFER, 1);
				@curl_setopt($cur_resource, CURLOPT_COOKIESESSION, 1);
				@curl_setopt($cur_resource, CURLOPT_COOKIEJAR, $this->_cookiefile);
				$data = curl_exec($cur_resource);
				@curl_close($cur_resource);
				break;
			case "command":
				$cur_resource = curl_init($query_str);
				@curl_setopt($cur_resource, CURLOPT_RETURNTRANSFER, 1);
				@curl_setopt($cur_resource, CURLOPT_COOKIEFILE, $this->_cookiefile);
				$data = curl_exec($cur_resource);
				@curl_close($cur_resource);
				break;
		}
		return $data;
	}

	/**
	 * Ajam::checkCon()
	 *
	 * Verify if connection session
	 * is logged or no, this method
	 * use the AJAM ping command
	 *
	 * @access private
	 * @return boolean
	 */
	private function checkCon()
	{
		$query = $this->buildQuery("Ping", null);
		$data = $this->getResponse("command", $query);
		$raw_result = $this->parseRaw($data);
		if (isset($raw_result['Response']) && $raw_result['Response'] == 'Success') {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Ajam::buildQuery()
	 *
	 * Build a complete URL AJAM query
	 * Ex.
	 * http://localhost:8088/asterisk/rawman?action=Ping
	 *
	 * @access private
	 * @param string $action_str AJAM Command
	 * @param array $params_arr Command params
	 * @return string
	 */
	private function buildQuery($action_str, $params_arr = array())
	{
		$query = $this->_urlraw . "?action=" . $action_str;
		if ($params_arr) {
			foreach (array_keys($params_arr) as $key) {
				$query .= "&$key=" . $params_arr[$key];
			}
		}
		if ($this->_debug) {
			echo "QUERY $query \n";
		}
		// Replace blank space to %20 to be
		// a valid Url address
		return str_replace(' ', '%20', $query);
	}

	/**
	 * Ajam::doCommand()
	 *
	 * @access public
	 * @param string $action_str AJAM Command
	 * @param mixed-array/null $params_arr Command params
	 * @return array
	 */
	public function doCommand($action_str, $params_arr = array())
	{
		if (!$this->checkCon()) {
			if ($this->_debug) {
				echo "Need login \n";
			}
			$this->doLogin($this->_authtype);
		}

		if (($action_str)) {
			$query = $this->buildQuery($action_str, $params_arr);
			$data = $this->getResponse("command", $query);
			$raw_result = $this->parseRaw($data);

			if ($this->_debug) {
				print_r($raw_result);
			}

			return $this->setResult($raw_result);
		} else {
			die('Action/Command is required');
		}
	}

}

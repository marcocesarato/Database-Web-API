<?php
/**
 * Database Web API Client
 *
 * @package    Database API Platform
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */

set_time_limit(3600); // Depends from data weight

class APIClient
{
	// Public
	public $DEBUG = false;
	public $URL = '';
	public $ACCESS_TOKEN = '';
	public $DATASET = '';

	// Protected
	protected static $instance = null;

	/**
	 * Returns static reference to the class instance
	 */
	public static function getInstance() {
		if (null === self::$instance) {
			self::$instance = new static();
		}
		return self::$instance;
	}

	/**
	 * Singleton constructor
	 */
	protected function __construct() {
	}

	private function __clone() {
	}

	private function __wakeup() {
	}

	/**
	 * Fetch data
	 * @param $table
	 * @param string $format
	 * @param array $where
	 * @return bool|mixed
	 */
	public function fetch($table, $format = 'json', $params = array()) {

		$this->URL = trim($this->URL, '/');
		$this->ACCESS_TOKEN = empty($this->ACCESS_TOKEN) ? '' : $this->ACCESS_TOKEN.'/';

		$params_query = !empty($params) ? self::_buildQuery($params) : '';
		$url = $this->URL . '/'. $this->ACCESS_TOKEN. $this->DATASET . '/' . $table . '.' . $format . '?' . $params_query;
		$request = self::_request($url);
		$this->_debug("APIClient fetch: Sent GET REQUEST to ".$url);

		$data = $request['content'];

		if($format == 'json')
			$data = @json_decode($data);
		if($format == 'xml')
			$data = @simplexml_load_string($data);

		if(empty($data)) return false;
		return $data;
	}

	/**
	 * Search object in array
	 * @param $array
	 * @param $key
	 * @param $value
	 * @return mixed
	 */
	public function searchElement($array, $key, $value){
		foreach($array as $elem) {
			if(is_object($elem)) {
				if (!empty($elem->{$key}) && $value == $elem->{$key}) {
					return $elem;
				}
			} else {
				if (!empty($elem[$key]) && $value == $elem[$key]) {
					return $elem;
				}
			}
		}
		$this->_debug("APIClient searchElement: Elemento non trovato! [".$key." = ".$value."]");
		return null;
	}

	/**
	 * Build url query params
	 * as http_build_query build a query url the difference is
	 * that this function is array recursive and compatible with PHP4
	 * @author Marco Cesarato <cesarato.developer@gmail.com>
	 * @param $query
	 * @param string $parent
	 * @return string
	 */
	private static function _buildQuery($query, $parent = null){
		$query_array = array();
		foreach($query as $key => $value){
			$_key = empty($parent) ?  urlencode($key) : $parent . '[' . urlencode($key) . ']';
			if(is_array($value)) {
				$query_array[] = self::_buildQuery($value, $_key);
			} else {
				$query_array[] = $_key . '=' . urlencode($value);
			}
		}
		return implode('&', $query_array);
	}

	/**
	 * HTTP Request
	 * @param $url
	 * @return mixed
	 */
	private static function _request($url) {
		$options = array(
			CURLOPT_RETURNTRANSFER => true,     // return web page
			CURLOPT_HEADER => true,             // return headers in addition to content
			CURLOPT_FOLLOWLOCATION => true,     // follow redirects
			CURLOPT_ENCODING => "",             // handle all encodings
			CURLOPT_AUTOREFERER => true,        // set referer on redirect
			CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
			CURLOPT_TIMEOUT => 120,             // timeout on response
			CURLOPT_MAXREDIRS => 10,            // stop after 10 redirects
			CURLINFO_HEADER_OUT => true,
			CURLOPT_SSL_VERIFYPEER => false,    // Validate SSL Cert
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_HTTPHEADER => array("Accept-Language: " . @$_SERVER['HTTP_ACCEPT_LANGUAGE']),
			CURLOPT_USERAGENT => @$_SERVER['HTTP_USER_AGENT'],
		);

		$ch = curl_init($url);
		curl_setopt_array($ch, $options);
		$rough_content = curl_exec($ch);
		$err = curl_errno($ch);
		$errmsg = curl_error($ch);
		$header = curl_getinfo($ch);
		curl_close($ch);

		$header_content = substr($rough_content, 0, $header['header_size']);
		$body_content = trim(str_replace($header_content, '', $rough_content));

		$header['errno'] = $err;
		$header['errmsg'] = $errmsg;
		$header['headers'] = $header_content;
		$header['content'] = $body_content;
		return $header;
	}

	/**
	 * Print debug message
	 * @param $msg
	 */
	private function _debug($msg){
		echo $msg.PHP_EOL;
	}
}
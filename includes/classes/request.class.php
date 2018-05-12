<?php

/**
 * Request Class
 *
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */

class Request
{

	static $instance;
	public $input;

	/**
	 * Singleton constructor
	 */
	public function __construct() {
		self::$instance = &$this;
		$this->input = self::get_params();
	}

	/**
	 * Returns the request parameters
	 * @params $sanitize (optional) sanitize input data, default is true
	 * @return input parameters
	 */
	public static function get_params($sanitize = true) {

		// Parse GET params
		$source = $_SERVER['QUERY_STRING'];

		parse_str($source, $params);

		// Parse POST, PUT, DELETE params
		if (self::method() != 'GET') {
			$source_input = file_get_contents("php://input");
			parse_str($source_input, $params_input);
			$params = array_merge($params, $params_input);
		}

		//die(print_r($params));

		if ($sanitize == true) $params = self::sanitize_params($params);
		return $params;
	}

	/**
	 * Returns the request method
	 */
	public static function method() {
		return $_SERVER['REQUEST_METHOD'];
	}

	/**
	 * Sanitize the parameters
	 * @package    Elite Platform
	 * @from       Security Module
	 * @author     Marco Cesarato <cesarato.developer@gmail.com>
	 * @version    0.6.1
	 * @return     sanitized data
	 * @param      $params data to sanitize
	 */
	private static function sanitize_params($params) {
		foreach ($params as $key => $value) {
			$value = trim_all($value);
			$value = self::sanitize_rxss($value);
			$value = self::sanitize_striptags($value);
			$value = self::sanitize_htmlentities($value);
			$value = self::sanitize_stripslashes($value);
			$params[$key] = $value;
		}
		return $params;
	}

	/**
	 * Sanitize from XSS injection
	 * @package    Elite Platform
	 * @from       Security Module
	 * @author     Marco Cesarato <cesarato.developer@gmail.com>
	 * @version    0.6.1
	 * @return     sanitized data
	 * @param      $params data to sanitize
	 */
	public static function sanitize_rxss($data) {
		if (is_array($data)) {
			foreach ($data as $k => $v) {
				$data[$k] = self::sanitize_rxss($v);
			}
		} else {
			$data = self::sanitize_xss($data);
		}
		return $data;
	}

	/**
	 * Sanitize from XSS injection
	 * @package    Elite Platform
	 * @from       Security Module
	 * @author     Marco Cesarato <cesarato.developer@gmail.com>
	 * @version    0.6.1
	 * @return     sanitized data
	 * @param      $params data to sanitize
	 */
	private static function sanitize_xss($data) {
		$data = str_replace(array("&amp;", "&lt;", "&gt;"), array("&amp;amp;", "&amp;lt;", "&amp;gt;"), $data);
		$data = preg_replace("/(&#*\w+)[- ]+;/u", "$1;", $data);
		$data = preg_replace("/(&#x*[0-9A-F]+);*/iu", "$1;", $data);
		$data = html_entity_decode($data, ENT_COMPAT, "UTF-8");
		$data = preg_replace('#(<[^>]+?[- "\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data);
		$data = preg_replace('#([a-z]*)[- ]*=[- ]*([`\'"]*)[- ]*j[- ]*a[- ]*v[- ]*a[- ]*s[- ]*c[- ]*r[- ]*i[- ]*p[- ]*t[- ]*:#iu', '$1=$2nojavascript', $data);
		$data = preg_replace('#([a-z]*)[- ]*=([\'"]*)[- ]*v[- ]*b[- ]*s[- ]*c[- ]*r[- ]*i[- ]*p[- ]*t[- ]*:#iu', '$1=$2novbscript', $data);
		$data = preg_replace('#([a-z]*)[- ]*=([\'"]*)[- ]*-moz-binding[- ]*:#u', '$1=$2nomozbinding', $data);
		$data = preg_replace('#(<[^>]+?)style[- ]*=[- ]*[`\'"]*.*?expression[- ]*\([^>]*+>#i', '$1>', $data);
		$data = preg_replace('#(<[^>]+?)style[- ]*=[- ]*[`\'"]*.*?behaviour[- ]*\([^>]*+>#i', '$1>', $data);
		$data = preg_replace('#(<[^>]+?)style[- ]*=[- ]*[`\'"]*.*?s[- ]*c[- ]*r[- ]*i[- ]*p[- ]*t[- ]*:*[^>]*+>#iu', '$1>', $data);
		$data = preg_replace('#</*\w+:\w[^>]*+>#i', '', $data);
		do {
			$old_data = $data;
			$data = preg_replace("#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i", "", $data);
		} while ($old_data !== $data);
		$data = str_replace(chr(0), '', $data);
		$data = preg_replace('%&\s*\{[^}]*(\}\s*;?|$)%', '', $data);
		$data = str_replace('&', '&amp;', $data);
		$data = preg_replace('/&amp;#([0-9]+;)/', '&#\1', $data);
		$data = preg_replace('/&amp;#[Xx]0*((?:[0-9A-Fa-f]{2})+;)/', '&#x\1', $data);
		$data = preg_replace('/&amp;([A-Za-z][A-Za-z0-9]*;)/', '&\1', $data);
		return $data;
	}

	/**
	 * Sanitize from HTML injection
	 * @package    Elite Platform
	 * @from       Security Module
	 * @author     Marco Cesarato <cesarato.developer@gmail.com>
	 * @version    0.6.1
	 * @return     sanitized data
	 * @param      $params data to sanitize
	 */
	public static function sanitize_striptags($data) {
		if (is_array($data)) {
			foreach ($data as $k => $v) {
				$data[$k] = self::sanitize_striptags($v);
			}
		} else {
			$data = strip_tags($data);
		}
		return $data;
	}

	/**
	 * Sanitize from HTML injection
	 * @package    Elite Platform
	 * @from       Security Module
	 * @author     Marco Cesarato <cesarato.developer@gmail.com>
	 * @version    0.6.1
	 * @return     sanitized data
	 * @param      $params data to sanitize
	 */
	public static function sanitize_htmlentities($data) {
		if (is_array($data)) {
			foreach ($data as $k => $v) {
				$data[$k] = self::sanitize_htmlentities($v);
			}
		} else {
			$data = htmlentities($data);
		}
		return $data;
	}

	/**
	 * Sanitize from SQL injection
	 * @package    Elite Platform
	 * @from       Security Module
	 * @author     Marco Cesarato <cesarato.developer@gmail.com>
	 * @version    0.6.1
	 * @return     sanitized data
	 * @param      $params data to sanitize
	 */
	public static function sanitize_stripslashes($data) {
		if (is_array($data)) {
			foreach ($data as $k => $v) {
				$data[$k] = self::sanitize_stripslashes($v);
			}
		} else {
			if (get_magic_quotes_gpc()) $data = stripslashes($data);
		}
		return $data;
	}

	/**
	 * Returns static reference to the class instance
	 */
	public static function &get_instance() {
		return self::$instance;
	}

	/**
	 * Halt the program with an "Internal server error" and the specified message.
	 * @param string|obj $error the error or a (PDO) exception object
	 * @param int $code (optional) the error code with which to respond
	 */
	/*public static function error($error, $code = '500') {
		if (is_object($error) && method_exists($error, 'get_message')) {
			$error = $error->get_message();
		}
		http_response_code($code);
		die($error);
	}*/

	public static function error($error, $code = '500') {
		if (is_object($error) && method_exists($error, 'getMessage') && method_exists($error, 'getCode')) {
			$message = DatabaseErrorParser::errorMessage($error);
			$api = API::get_instance();
			$results = array(
				"response" => (object)array('status' => 400, 'message' => $message),
			);
			$renderer = 'render_' . $api->query['format'];
			die($api->$renderer($results, $api->query));
		}
		http_response_code($code);
		die(self::sanitize_htmlentities($error));
	}
}

$request = new Request();
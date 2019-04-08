<?php

/**
 * Request Class
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 * @copyright  Copyright (c) 2018
 * @license    http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @link       https://github.com/marcocesarato/Database-Web-API
 */
class Request {

	static $instance;
	public $input;

	/**
	 * Singleton constructor
	 */
	public function __construct() {
		self::$instance = &$this;
		self::blockBots();
		self::blockTor();
		$this->input = self::get_params();
	}

	/**
	 * Prevent bad bots
	 */
	public static function blockBots() {
		// Block bots
		if(preg_match("/(spider|crawler|slurp|teoma|archive|track|snoopy|lwp|client|libwww)/i", $_SERVER['HTTP_USER_AGENT']) ||
		   preg_match("/(havij|libwww-perl|wget|python|nikto|curl|scan|java|winhttp|clshttp|loader)/i", $_SERVER['HTTP_USER_AGENT']) ||
		   preg_match("/(%0A|%0D|%27|%3C|%3E|%00)/i", $_SERVER['HTTP_USER_AGENT']) ||
		   preg_match("/(;|<|>|'|\"|\)|\(|%0A|%0D|%22|%27|%28|%3C|%3E|%00).*(libwww-perl|wget|python|nikto|curl|scan|java|winhttp|HTTrack|clshttp|archiver|loader|email|harvest|extract|grab|miner)/i", $_SERVER['HTTP_USER_AGENT'])) {
			self::error('Permission denied!', 403);
		}
		// Block Fake google bot
		self::blockFakeGoogleBots();
	}

	/**
	 * Halt the program with an "Internal server error" and the specified message.
	 * @param string|object $error the error or a (PDO) exception object
	 * @param int $code (optional) the error code with which to respond
	 */
	public static function error($error, $code = 500) {
		$api    = API::getInstance();
		$logger = Logger::getInstance();
		if(is_object($error) && method_exists($error, 'getMessage') && method_exists($error, 'getCode')) {
			$message = DatabaseErrorParser::errorMessage($error);
			$results = array(
				"response" => (object) array('status' => 400, 'message' => $message),
			);
			$logger->error($code . " - " . $error);
			$api->render($results);
		}
		http_response_code($code);
		$logger->error($code . " - " . $error);
		$results = array(
			"response" => (object) array('status' => $code, 'message' => self::sanitize_htmlentities($error)),
		);
		$api->render($results);
	}

	/**
	 * Sanitize from HTML injection
	 * @package    AIO Security Class
	 * @author     Marco Cesarato <cesarato.developer@gmail.com>
	 * @param      $data mixed data to sanitize
	 * @return     $data sanitized data
	 */
	public static function sanitize_htmlentities($data) {
		if(is_array($data)) {
			foreach($data as $k => $v) {
				$data[$k] = self::sanitize_htmlentities($v);
			}
		} else {
			$data = htmlentities($data);
		}

		return $data;
	}

	/**
	 * Prevent Fake Google Bots
	 */
	protected static function blockFakeGoogleBots() {
		$user_agent = (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '');
		if(preg_match('/googlebot/i', $user_agent, $matches)) {
			$ip      = self::getIPAddress();
			$name    = gethostbyaddr($ip);
			$host_ip = gethostbyname($name);
			if(preg_match('/googlebot/i', $name, $matches)) {
				if($host_ip != $ip) {
					self::error('Permission denied!', 403);
				}
			} else {
				self::error('Permission denied!', 403);
			}
		}
	}

	/**
	 * Get IP Address
	 * @return mixed
	 */
	public static function getIPAddress() {
		foreach(
			array(
				'HTTP_CLIENT_IP',
				'HTTP_CF_CONNECTING_IP',
				'HTTP_X_FORWARDED_FOR',
				'HTTP_X_FORWARDED',
				'HTTP_X_CLUSTER_CLIENT_IP',
				'HTTP_FORWARDED_FOR',
				'HTTP_FORWARDED',
				'HTTP_VIA',
				'REMOTE_ADDR'
			) as $key
		) {
			if(array_key_exists($key, $_SERVER) === true) {

				foreach(explode(',', $_SERVER[$key]) as $ip) {
					$ip = trim($ip);
					// Check for IPv4 IP cast as IPv6
					if(preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/', $ip, $matches)) {
						$ip = $matches[1];
					}
					if($ip == "::1") {
						$ip = "127.0.0.1";
					}
					if($ip == '127.0.0.1' || self::isPrivateIP($ip)) {
						$ip = $_SERVER['REMOTE_ADDR'];
						if($ip == "::1") {
							$ip = "127.0.0.1";
						}

						return $ip;
					}
					if(self::validateIPAddress($ip)) {
						return $ip;
					}
				}
			}
		}

		return "0.0.0.0";
	}

	/**
	 * Detect if is private IP
	 * @param $ip
	 * @return bool
	 */
	private static function isPrivateIP($ip) {

		// Dealing with ipv6, so we can simply rely on filter_var
		if(false === strpos($ip, '.')) {
			return !@filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
		}

		$long_ip = ip2long($ip);
		// Dealing with ipv4
		$private_ip4_addresses = array(
			'10.0.0.0|10.255.255.255',     // single class A network
			'172.16.0.0|172.31.255.255',   // 16 contiguous class B network
			'192.168.0.0|192.168.255.255', // 256 contiguous class C network
			'169.254.0.0|169.254.255.255', // Link-local address also referred to as Automatic Private IP Addressing
			'127.0.0.0|127.255.255.255'    // localhost
		);
		if(- 1 != $long_ip) {
			foreach($private_ip4_addresses as $pri_addr) {
				list ($start, $end) = explode('|', $pri_addr);
				if($long_ip >= ip2long($start) && $long_ip <= ip2long($end)) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Ensures an ip address is both a valid IP and does not fall within
	 * a private network range.
	 */
	public static function validateIPAddress($ip) {
		if(strtolower($ip) === 'unknown') {
			return false;
		}

		// generate ipv4 network address
		$ip = ip2long($ip);

		// if the ip is set and not equivalent to 255.255.255.255
		if($ip !== false && $ip !== - 1) {
			// make sure to get unsigned long representation of ip
			// due to discrepancies between 32 and 64 bit OSes and
			// signed numbers (ints default to signed in PHP)
			$ip = sprintf('%u', $ip);
			// do private network range checking
			if($ip >= 0 && $ip <= 50331647) {
				return false;
			}
			if($ip >= 167772160 && $ip <= 184549375) {
				return false;
			}
			if($ip >= 2130706432 && $ip <= 2147483647) {
				return false;
			}
			if($ip >= 2851995648 && $ip <= 2852061183) {
				return false;
			}
			if($ip >= 2886729728 && $ip <= 2887778303) {
				return false;
			}
			if($ip >= 3221225984 && $ip <= 3221226239) {
				return false;
			}
			if($ip >= 3232235520 && $ip <= 3232301055) {
				return false;
			}
			if($ip >= 4294967040) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if clients use Tor
	 */
	public static function blockTor() {
		$ips       = self::getAllIPAddress();
		$ip_server = gethostbyname($_SERVER['SERVER_NAME']);
		foreach($ips as $ip) {
			$query       = array(
				implode('.', array_reverse(explode('.', $ip))),
				$_SERVER["SERVER_PORT"],
				implode('.', array_reverse(explode('.', $ip_server))),
				'ip-port.exitlist.torproject.org'
			);
			$torExitNode = implode('.', $query);
			$dns         = dns_get_record($torExitNode, DNS_A);
			if(array_key_exists(0, $dns) && array_key_exists('ip', $dns[0])) {
				if($dns[0]['ip'] == '127.0.0.2') {
					self::error('Permission denied!', 403);
				}
			}

		}
	}

	/**
	 * Get all client IP Address
	 * @return array
	 */
	public static function getAllIPAddress() {
		$ips = array();
		foreach(
			array(
				'GD_PHP_HANDLER',
				'HTTP_AKAMAI_ORIGIN_HOP',
				'HTTP_CF_CONNECTING_IP',
				'HTTP_CLIENT_IP',
				'HTTP_FASTLY_CLIENT_IP',
				'HTTP_FORWARDED',
				'HTTP_FORWARDED_FOR',
				'HTTP_INCAP_CLIENT_IP',
				'HTTP_TRUE_CLIENT_IP',
				'HTTP_X_CLIENTIP',
				'HTTP_X_CLUSTER_CLIENT_IP',
				'HTTP_X_FORWARDED',
				'HTTP_X_FORWARDED_FOR',
				'HTTP_X_IP_TRAIL',
				'HTTP_X_REAL_IP',
				'HTTP_X_VARNISH',
				'HTTP_VIA',
				'REMOTE_ADDR'
			) as $key
		) {
			if(array_key_exists($key, $_SERVER) === true) {
				foreach(explode(',', $_SERVER[$key]) as $ip) {
					$ip = trim($ip);
					// Check for IPv4 IP cast as IPv6
					if(preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/', $ip, $matches)) {
						$ip = $matches[1];
					}
					if($ip == "::1") {
						$ips[] = "127.0.0.1";
					} else if(self::validateIPAddress($ip)) {
						$ips[] = $ip;
					}
				}
			}
		}
		if(empty($ips)) {
			$ips = array('0.0.0.0');
		}
		$ips = array_unique($ips);

		return $ips;
	}

	/**
	 * Returns the request parameters
	 * @params $sanitize (optional) sanitize input data, default is true
	 * @return $params parameters
	 */
	public static function get_params($sanitize = true) {

		// Parse GET params
		$source = $_SERVER['QUERY_STRING'];

		parse_str($source, $params);

		// Parse POST, PUT, DELETE params
		if(self::method() != 'GET' && self::method() != 'DELETE') {
			$source_input = file_get_contents("php://input");
			parse_str($source_input, $params_input);
			$params = array_merge($params, $params_input);
		}

		//die(print_r($params));

		if($sanitize == true) {
			$params = self::sanitize_params($params);
		}

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
	 * @package    AIO Security Class
	 * @author     Marco Cesarato <cesarato.developer@gmail.com>
	 * @param      $params mixed data to sanitize
	 * @return     $params sanitized data
	 */
	private static function sanitize_params($params) {
		foreach($params as $key => $value) {
			$value        = trim_all($value);
			$value        = self::sanitize_rxss($value);
			$value        = self::sanitize_striptags($value);
			$value        = self::sanitize_htmlentities($value);
			$value        = self::sanitize_stripslashes($value);
			$params[$key] = $value;
		}

		return $params;
	}

	/**
	 * Sanitize from XSS injection
	 * @package    AIO Security Class
	 * @author     Marco Cesarato <cesarato.developer@gmail.com>
	 * @param      $data mixed data to sanitize
	 * @return     $data sanitized data
	 */
	public static function sanitize_rxss($data) {
		if(is_array($data)) {
			foreach($data as $k => $v) {
				$data[$k] = self::sanitize_rxss($v);
			}
		} else {
			$data = self::sanitize_xss($data);
		}

		return $data;
	}

	/**
	 * Sanitize from XSS injection
	 * @package    AIO Security Class
	 * @author     Marco Cesarato <cesarato.developer@gmail.com>
	 * @param      $data mixed data to sanitize
	 * @return     $data sanitized data
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
			$data     = preg_replace("#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i", "", $data);
		} while($old_data !== $data);
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
	 * @package    AIO Security Class
	 * @author     Marco Cesarato <cesarato.developer@gmail.com>
	 * @param      $data mixed data to sanitize
	 * @return     $data sanitized data
	 */
	public static function sanitize_striptags($data) {
		if(is_array($data)) {
			foreach($data as $k => $v) {
				$data[$k] = self::sanitize_striptags($v);
			}
		} else {
			$data = strip_tags($data);
		}

		return $data;
	}

	/**
	 * Sanitize from SQL injection
	 * @package    AIO Security Class
	 * @author     Marco Cesarato <cesarato.developer@gmail.com>
	 * @param      $data mixed data to sanitize
	 * @return     $data sanitized data
	 */
	public static function sanitize_stripslashes($data) {
		if(is_array($data)) {
			foreach($data as $k => $v) {
				$data[$k] = self::sanitize_stripslashes($v);
			}
		} else {
			if(get_magic_quotes_gpc()) {
				$data = stripslashes($data);
			}
		}

		return $data;
	}

	/**
	 * Returns the request referer
	 */
	public static function referer() {
		return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "";
	}

	/**
	 * Returns static reference to the class instance
	 */
	public static function &getInstance() {
		return self::$instance;
	}
}

$request = new Request();
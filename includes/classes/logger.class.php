<?php
/**
 * Logger Class
 *
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 * @copyright  Copyright (c) 2018
 * @license    http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @link       https://github.com/marcocesarato/Database-Web-API
 */

class Logger {

	public static $instance;
	protected $log_file;
	protected $log_dir;
	protected $log_path;
	protected $file;
	protected $options = array (
		'dateFormat' => 'd-M-Y H:i:s'
	);

	/**
	 * Singleton class constructor
	 */
	public function __construct() {
		self::$instance = &$this;
	}

	/**
	 * Returns static reference to the class instance
	 */
	public static function &getInstance() {
		return self::$instance;
	}

	/**
	 * Setup
	 * @param string $log_file - path and filename of log
	 * @param array $params
	 */
	public function setLog($log_dir, $log_file = 'logs.log', $params = array()){
		$this->log_dir = $log_dir;
		$this->log_file = $log_file;
		$this->log_path = preg_replace('/\\\\/', '\\', $log_dir . "/" . $log_file);
		$this->params = array_merge($this->options, $params);

		//Create log file if it doesn't exist.
		if (!file_exists($this->log_path)) {
			if (!file_exists(dirname($this->log_path)))
				mkdir(dirname($this->log_path), 0775, true);
			@fopen($this->log_path, 'w'); //or exit("Can't create $log_file!");
		}
		//Check permissions of file.
		if (!is_writable($this->log_path)) {
			//throw exception if not writable
			//throw new Exception("ERROR: Unable to write to file!", 1);
		}
	}

	/**
	 * Info method (write info message)
	 * @param mixed $message
	 * @return void
	 */
	public function info($message) {
		$this->writeLog($message, 'INFO');
	}

	/**
	 * Debug method (write debug message)
	 * @param mixed $message
	 * @return void
	 */
	public function debug($message) {
		$this->writeLog($message, 'DEBUG');
	}

	/**
	 * Warning method (write warning message)
	 * @param mixed $message
	 * @return void
	 */
	public function warning($message) {
		$this->writeLog($message, 'WARNING');
	}

	/**
	 * Error method (write error message)
	 * @param mixed $message
	 * @return void
	 */
	public function error($message) {
		$this->writeLog($message, 'ERROR');
	}

	/**
	 * Write to log file
	 * @param mixed $message
	 * @param string $severity
	 * @return void
	 */
	public function writeLog($message, $severity) {

		// open log file
		if (!is_resource($this->file)) {
			$this->openLog();
		}

		// Encode to JSON if is not a string
		if(!is_string($message))
			$message = json_encode($message);

		// Remove new lines
		$message = trim(preg_replace('/\s+/', ' ', $message));

		// Request method
		$method = strtoupper($_SERVER['REQUEST_METHOD']);
		// Grab the url path ( for troubleshooting )
		$path = $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
		// Grab time - based on timezone in php.ini
		$time = date($this->params['dateFormat']);

		$ip = Request::getIPAddress();

		// Write time, url, & message to end of file
		@fwrite($this->file, "[$time] [$severity] [method $method] [url $path] [client $ip]: $message" . PHP_EOL);
	}

	/**
	 * Open log file
	 * @return void
	 */
	private function openLog() {
		$openFile = $this->log_dir . "/" . $this->log_file;
		if (!file_exists(dirname($openFile)))
			mkdir(dirname($openFile), 0775, true);
		// 'a' option = place pointer at end of file
		$this->file = @fopen($openFile, 'a'); // or exit("Can't open $openFile!");
	}

	/**
	 * Class destructor
	 */
	public function __destruct() {
		if ($this->file) {
			@fclose($this->file);
		}
	}
}
$LOGGER = new Logger();
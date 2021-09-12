<?php

namespace marcocesarato\DatabaseAPI;

use DateTime;

/**
 * Logger Class.
 *
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 * @license    http://opensource.org/licenses/gpl-3.0.html GNU Public License
 *
 * @see       https://github.com/marcocesarato/Database-Web-API
 */
class Logger
{
    protected static $instances = [];
    protected static $session_token;
    protected $log_file;
    protected $log_dir;
    protected $log_path;
    protected $file;
    protected $params = [];
    protected $options = [
        'dateFormat' => 'd-M-Y H:i:s.u',
        'onlyMessage' => false,
    ];
    protected $cache = '';
    protected $previously_enabled_memory_cache = false;
    protected $use_memory_cache = false;

    /**
     * Get an instance of the logger.
     *
     * @param $key
     *
     * @return self
     */
    public static function getInstance($key = 'API')
    {
        if (empty(self::$session_token)) {
            self::$session_token = uniqid('session_', false);
        }
        if (!array_key_exists($key, self::$instances)) {
            self::$instances[$key] = new self();
        }

        return self::$instances[$key];
    }

    /**
     * Logger constructor.
     * Prevent creating multiple instances due to "protected" constructor.
     */
    protected function __construct()
    {
    }

    /**
     * Prevent the instance from being cloned.
     */
    protected function __clone()
    {
    }

    /**
     * Prevent from being unserialized.
     */
    protected function __wakeup()
    {
    }

    /**
     * Setup.
     *
     * @param string $log_file - path and filename of log
     * @param array  $params
     */
    public function setLog($log_dir, $log_file = 'logs.log', $params = [])
    {
        $this->log_dir = $log_dir;
        $this->log_file = $log_file;
        $this->log_path = preg_replace('/\\\\/', '\\', $log_dir . '/' . $log_file);
        $this->params = shortcode_atts($this->options, $params);

        // Create log file if it doesn't exist.
        if (!file_exists($this->log_path)) {
            if (!file_exists(dirname($this->log_path))) {
                if (!mkdir($concurrentDirectory = dirname($this->log_path), 0775, true) && !is_dir($concurrentDirectory)) {
                    //throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
                }
            }
            @fopen($this->log_path, 'wb'); //or exit("Can't create $log_file!");
        }
        // Check permissions of file.
        if (!is_writable($this->log_path)) {
            //throw exception if not writable
            //throw new Exception("ERROR: Unable to write to file!", 1);
        }
    }

    /**
     * Info method (write info message).
     *
     * @param mixed $message
     *
     * @return void
     */
    public function info($message)
    {
        $this->writeLog($message, 'INFO');
    }

    /**
     * Write to log file.
     *
     * @param mixed  $message
     * @param string $severity
     *
     * @return void
     */
    public function writeLog($message, $severity)
    {
        // Encode to JSON if is not a string
        if (!is_string($message)) {
            $message = json_encode($message);
        }

        // Remove new lines
        $message = trim(preg_replace('/\s+/', ' ', $message));

        $token = Request::getToken();

        // Request method
        $method = Request::method();
        // Grab the url path ( for troubleshooting )
        $path = $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];

        // Grab time - based on timezone in php.ini
        $t = microtime(true);
        $micro = sprintf('%06d', ($t - floor($t)) * 1000000);
        $d = new DateTime(date('Y-m-d H:i:s.' . $micro, $t));
        $time = $d->format($this->params['dateFormat']);

        $ip = Request::getIPAddress();

        $path = str_replace($token . '/', '', $path);

        $session_token = self::$session_token;

        // Write time, url, & message to end of file
        if ($this->params['onlyMessage']) {
            $log = "[$time] [$severity]: $message";
        } else {
            $log = "[$time] [$severity] [method $method] [url $path] [token $token] [client $ip] [session $session_token]: $message";
        }

        $log .= PHP_EOL;

        if ($severity === 'none') {
            $log = '';
        }

        if (!$this->use_memory_cache || isset($this->cache[100000])) { // Write every ~ 100kb if cache was enabled
            // open log file
            if (!is_resource($this->file)) {
                $this->openLog();
            }

            @fwrite($this->file, $this->cache . $log);
            $this->cache = '';
        } else {
            $this->cache .= $log;
        }
    }

    /**
     * Open log file.
     *
     * @return void
     */
    private function openLog()
    {
        $openFile = $this->log_dir . '/' . $this->log_file;
        if (!file_exists(dirname($openFile))) {
            if (!mkdir($concurrentDirectory = dirname($openFile), 0775, true) && !is_dir($concurrentDirectory)) {
                //throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }
        // 'a' option = place pointer at end of file
        $this->file = @fopen($openFile, 'ab'); // or exit("Can't open $openFile!");
    }

    /**
     * Debug method (write debug message).
     *
     * @param mixed $message
     *
     * @return void
     */
    public function debug($message)
    {
        $this->writeLog($message, 'DEBUG');
    }

    /**
     * Warning method (write warning message).
     *
     * @param mixed $message
     *
     * @return void
     */
    public function warning($message)
    {
        $this->writeLog($message, 'WARNING');
    }

    /**
     * Error method (write error message).
     *
     * @param mixed $message
     *
     * @return void
     */
    public function error($message)
    {
        $this->writeLog($message, 'ERROR');
    }

    /**
     * Write cache on shutdown (if memory cache enabled and cache is not empty).
     */
    public function writeCache()
    {
        if ($this->use_memory_cache && !empty($this->cache)) {
            $this->use_memory_cache = false;
            $this->writeLog('', 'none');
            $this->use_memory_cache = true;
        }
    }

    /**
     * Enable/Disable memory cache (for faster execution).
     *
     * @param bool $status
     */
    public function useMemoryCache($status = true)
    {
        if ($status === false) {
            $this->writeCache();
        }

        $this->use_memory_cache = $status;
        if ($status === true && !$this->previously_enabled_memory_cache) {
            register_shutdown_function([$this, 'writeCache']);
            $this->previously_enabled_memory_cache = true;
        }
    }

    /**
     * Class destructor.
     */
    public function __destruct()
    {
        if ($this->file) {
            @fclose($this->file);
        }
    }
}

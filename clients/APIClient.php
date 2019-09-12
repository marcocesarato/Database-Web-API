<?php

namespace marcocesarato\DatabaseAPI\Client;

/**
 * Database Web API Client.
 *
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 * @copyright  Copyright (c) 2019
 * @license    http://opensource.org/licenses/gpl-3.0.html GNU Public License
 *
 * @see       https://github.com/marcocesarato/Database-Web-API
 */
class APIClient
{
    // Public
    private static $DEBUG = false;
    private static $URL = '';
    private static $DATASET = '';
    private static $ACCESS_TOKEN = '';
    private static $TIMEOUT = 15;
    private static $EXECUTION_TIME = 60;
    private static $MEMORY_LIMIT = '1G';

    // Protected
    protected static $instance = null;

    // Private
    private static $_DATA = array();

    /**
     * Singleton constructor.
     */
    protected function __construct()
    {
        // Depends from data weight
        ini_set('memory_limit', self::$MEMORY_LIMIT);
        self::setExecutionTime(self::$EXECUTION_TIME);
    }

    /**
     * Returns static reference to the class instance.
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * Is Connected.
     *
     * @return bool
     */
    public function isConnected()
    {
        if (empty(self::$ACCESS_TOKEN) || empty(self::$URL)) {
            return false;
        }

        return true;
    }

    /**
     * Set Url.
     *
     * @param $url
     */
    public static function setUrl($url)
    {
        self::$URL = $url;
    }

    /**
     * Get Url.
     *
     * @param string $page
     *
     * @return string
     */
    private static function getUrl($page = '')
    {
        if (empty($page)) {
            $page = '.json';
        } else {
            $page = '/' . ltrim($page, '/');
        }
        $URL = str_replace('\\', '/', self::$URL);

        return rtrim($URL, '/') . '/api/' . self::$DATASET . $page;
    }

    /**
     * Set Access token.
     *
     * @param $token
     */
    public static function setAccessToken($token)
    {
        self::$ACCESS_TOKEN = $token;
    }

    /**
     * Set Dataset.
     *
     * @param $dataset
     */
    public static function setDataset($dataset)
    {
        self::$DATASET = $dataset;
    }

    /**
     * Set Timeout.
     *
     * @param $timeout
     */
    public static function setTimeout($timeout = 15)
    {
        self::$TIMEOUT = $timeout;
    }

    /**
     * Set max execution time.
     *
     * @param $time
     */
    public static function setExecutionTime($time = 60)
    {
        self::$EXECUTION_TIME = $time;
        ini_set('max_execution_time', self::$EXECUTION_TIME);
        set_time_limit(self::$EXECUTION_TIME);
    }

    /**
     * Build url query params
     * as http_build_query build a query url the difference is
     * that this function is array recursive and compatible with PHP4.
     *
     * @param        $query
     * @param string $parent
     *
     * @return string
     *
     * @author Marco Cesarato <cesarato.developer@gmail.com>
     */
    private static function buildQuery($query, $parent = null)
    {
        $query_array = array();
        foreach ($query as $key => $value) {
            $_key = empty($parent) ? urlencode($key) : $parent . '[' . urlencode($key) . ']';
            if (is_array($value)) {
                $query_array[] = self::buildQuery($value, $_key);
            } else {
                $query_array[] = $_key . '=' . urlencode($value);
            }
        }

        return implode('&', $query_array);
    }

    /**
     * HTTP Request.
     *
     * @param $url
     *
     * @return mixed
     */
    private static function doRequest($url, $body = null, $method = 'GET')
    {
        $options = array(
            CURLOPT_RETURNTRANSFER => true,                 // return web page
            CURLOPT_HEADER => true,                 // return headers in addition to content
            CURLOPT_FOLLOWLOCATION => true,                 // follow redirects
            CURLOPT_ENCODING => '',                   // handle all encodings
            CURLOPT_AUTOREFERER => true,                 // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => self::$TIMEOUT,      // timeout on connect
            CURLOPT_TIMEOUT => self::$TIMEOUT,      // timeout on response
            CURLOPT_MAXREDIRS => 10,                   // stop after 10 redirects
            CURLINFO_HEADER_OUT => true,
            CURLOPT_SSL_VERIFYPEER => false,                // Validate SSL Cert
            CURLOPT_SSL_VERIFYHOST => false,                // Validate SSL Cert
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => array(
                'Accept-Language: ' . @$_SERVER['HTTP_ACCEPT_LANGUAGE'],
                'Cache-Control: no-cache',
                'Access-Token: ' . self::$ACCESS_TOKEN,
            ),
            CURLOPT_USERAGENT => @$_SERVER['HTTP_USER_AGENT'],
            CURLOPT_POSTFIELDS => (empty($body) ? null : $body),
        );

        if ($body !== false && !empty($body) && $method == 'GET') {
            $method = 'POST';
        }

        $method = strtoupper($method);
        if (in_array($method, array('POST', 'PUT', 'PATCH', 'DELETE'))) {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
        }

        $options = array_filter($options);

        $ch = curl_init($url);

        $rough_content = self::execRequest($ch, $options);
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
     * Prevent 301/302/307 Code.
     *
     * @param resource $ch
     * @param array    $options
     *
     * @return bool|string
     */
    private static function execRequest($ch, $options = array())
    {
        $options[CURLOPT_FOLLOWLOCATION] = false;
        curl_setopt_array($ch, $options);

        $rough_content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code == 301 || $http_code == 302 || $http_code == 307) {
            preg_match('/(Location:|URI:)(.*?)\n/', $rough_content, $matches);
            if (isset($matches[2])) {
                $redirect_url = trim($matches[2]);
                if ($redirect_url !== '') {
                    $options[CURLOPT_URL] = $redirect_url;

                    return self::execRequest($ch, $options);
                }
            }
        }

        return $rough_content;
    }

    /**
     * Print debug message.
     *
     * @param $msg
     */
    private function _debug($msg)
    {
        if (self::$DEBUG) {
            echo '<pre>' . $msg . '</pre>';
        }
    }

    /**
     * Get data.
     *
     * @param       $table
     * @param array $where
     *
     * @return bool|mixed
     */
    public function get($table, $params = array())
    {
        if (!$this->isConnected()) {
            return false;
        }

        $param_key = serialize($params);

        if (!empty(self::$_DATA[$table][$param_key])) {
            return self::$_DATA[$table][$param_key];
        }

        $params_query = !empty($params) ? self::buildQuery($params) : '';
        $url = self::getUrl($table . '.json?' . $params_query);
        $request = self::doRequest($url);
        $this->_debug('APIClient GET: Sent GET REQUEST to ' . $url);

        self::$_DATA[$table][$param_key] = $request['content'];

        if ($request['errmsg']) {
            $this->_debug('APIClient GET: Error ' . $request['errno'] . ' => ' . $request['errmsg']);
        }

        self::$_DATA[$table][$param_key] = @json_decode(self::$_DATA[$table][$param_key]);

        if (empty(self::$_DATA[$table][$param_key])) {
            return false;
        }

        $this->_debug('APIClient GET: Count ' . count(self::$_DATA[$table][$param_key], true));

        return self::$_DATA[$table][$param_key];
    }

    /**
     * Insert data.
     *
     * @param array $params
     *
     * @return bool|mixed
     */
    public function insert($params = array())
    {
        if (!$this->isConnected()) {
            return false;
        }

        $params_query = !empty($params) ? self::buildQuery($params) : '';
        $url = self::getUrl();
        $request = self::doRequest($url, $params_query);
        $this->_debug('APIClient INSERT: Sent POST REQUEST to ' . $url);
        //$this->_debug("APIClient INSERT: Params \r\n".var_export($params, true));

        $this->_debug('APIClient INSERT: Params ' . var_export($params, true));

        if ($request['errmsg']) {
            $this->_debug('APIClient INSERT: Error ' . $request['errno'] . ' => ' . $request['errmsg']);
        }

        $this->_debug('APIClient INSERT: Header ' . $request['headers']);

        $response = $request['content'];
        $response = @json_decode($response);

        if (empty($response)) {
            return false;
        }

        $this->_debug("APIClient INSERT: Response \r\n" . var_export($response, true));

        return $response;
    }

    /**
     * Update data.
     *
     * @param array $params
     *
     * @return bool|mixed
     */
    public function update($params = array())
    {
        if (!$this->isConnected()) {
            return false;
        }

        $params_query = !empty($params) ? self::buildQuery($params) : '';
        $url = self::getUrl();
        $request = self::doRequest($url, $params_query, 'PATCH');
        $this->_debug('APIClient UPDATE: Sent PUT REQUEST to ' . $url);

        if ($request['errmsg']) {
            $this->_debug('APIClient UPDATE: Error ' . $request['errno'] . ' => ' . $request['errmsg']);
        }

        $response = $request['content'];
        $response = @json_decode($response);

        if (empty($response)) {
            return false;
        }

        $this->_debug("APIClient UPDATE: Response \r\n" . var_export($request, true));

        return $response;
    }

    /**
     * Replace data.
     *
     * @param array $params
     *
     * @return bool|mixed
     */
    public function replace($params = array())
    {
        if (!$this->isConnected()) {
            return false;
        }

        $params_query = !empty($params) ? self::buildQuery($params) : '';
        $url = self::getUrl();
        $request = self::doRequest($url, $params_query, 'PUT');
        $this->_debug('APIClient REPLACE: Sent PUT REQUEST to ' . $url);

        if ($request['errmsg']) {
            $this->_debug('APIClient REPLACE: Error ' . $request['errno'] . ' => ' . $request['errmsg']);
        }

        $response = $request['content'];
        $response = @json_decode($response);

        if (empty($response)) {
            return false;
        }

        $this->_debug("APIClient REPLACE: Response \r\n" . var_export($request, true));

        return $response;
    }

    /**
     * Delete data.
     *
     * @param       $table
     * @param array $params
     *
     * @return bool|mixed
     */
    public function delete($table, $params = array())
    {
        if (!$this->isConnected()) {
            return false;
        }

        $params_query = !empty($params) ? self::buildQuery($params) : '';
        $url = self::getUrl($table . '.json?' . $params_query);
        $request = self::doRequest($url, false, 'DELETE');
        $this->_debug('APIClient DELETE: Sent DELETE REQUEST to ' . $url);

        if ($request['errmsg']) {
            $this->_debug('APIClient GET: Error ' . $request['errno'] . ' => ' . $request['errmsg']);
        }

        $response = $request['content'];
        $response = @json_decode($response);

        $this->_debug("APIClient DELETE: CURL Request \r\n" . var_export($request, true));

        if (empty($response)) {
            return false;
        }

        $this->_debug("APIClient DELETE: Response \r\n" . var_export($response, true));

        return $response;
    }

    /**
     * Search object in array.
     *
     * @param $array
     * @param $key
     * @param $value
     *
     * @return mixed
     */
    public function searchElement($key, $value, $array)
    {
        if (is_null($value)) {
            return null;
        }
        foreach ($array as $elem) {
            if (is_object($elem)) {
                if (!empty($elem->{$key}) && $value == $elem->{$key}) {
                    return $elem;
                }
            } else {
                if (!empty($elem[$key]) && $value == $elem[$key]) {
                    return $elem;
                }
            }
        }
        $this->_debug('APIClient searchElement: Element not found! [' . $key . ' = ' . $value . ']');

        return null;
    }

    /**
     * Filter object in array.
     *
     * @param $key
     * @param $value
     * @param $array
     * @param $limit
     *
     * @return mixed
     */
    public function filterBy($key, $value, $array, $limit = null)
    {
        if (is_null($value)) {
            return null;
        }
        $result = array();
        foreach ($array as $elem) {
            if (!empty($limit) && count($result) == $limit) {
                break;
            }
            if (is_object($elem)) {
                if (!empty($elem->$key) && $value == $elem->$key) {
                    $result[] = $elem;
                }
            } elseif (is_array($elem)) {
                if (!empty($elem[$key]) && $value == $elem[$key]) {
                    $result[] = $elem;
                }
            }
        }
        $this->_debug('APIClient filter: Trovati ' . count($result) . ' elementi! [' . $key . ' = ' . $value . ']');

        return $result;
    }

    /**
     * Filter object in array.
     *
     * @param $values
     * @param $array
     * @param $limit
     *
     * @return mixed
     */
    public function filter($values, $array, $limit = null)
    {
        if (is_null($values)) {
            return null;
        }
        $result = array();
        foreach ($array as $elem) {
            if (!empty($limit) && count($result) == $limit) {
                break;
            }
            $found = true;
            foreach ($values as $key => $value) {
                if (is_object($elem)) {
                    if (!empty($elem->$key) && $value != $elem->$key) {
                        $found = false;
                    }
                } elseif (is_array($elem)) {
                    if (!empty($elem[$key]) && $value != $elem[$key]) {
                        $found = false;
                    }
                }
            }
            if ($found) {
                $result[] = $elem;
            }
        }
        $this->_debug('APIClient filter: Trovati ' . count($result) . ' elementi! [' . $key . ' = ' . $value . ']');

        return $result;
    }

    private function __clone()
    {
    }

    private function __wakeup()
    {
    }
}

<?php

namespace marcocesarato\DatabaseAPI;

/**
 * Request Class.
 *
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 * @copyright  Copyright (c) 2019
 * @license    http://opensource.org/licenses/gpl-3.0.html GNU Public License
 *
 * @see       https://github.com/marcocesarato/Database-Web-API
 */
class Request
{
    public static $instance;
    public $input;

    private static $urlparsed = false;

    /**
     * Request constructor.
     */
    public function __construct()
    {
        self::$instance = &$this;
        self::blockBots();
        self::blockTor();
        $this->input = self::getParams();
    }

    /**
     * Returns static reference to the class instance.
     */
    public static function &getInstance()
    {
        return self::$instance;
    }

    /**
     * Returns the request parameters.
     *
     * @params $sanitize (optional) sanitize input data, default is true
     *
     * @return $params parameters
     */
    public static function getParams($sanitize = true)
    {
        self::parseUrlRewrite();

        // Parse GET params
        $source = $_SERVER['QUERY_STRING'];

        parse_str($source, $params);

        // Parse POST, PUT, PATCH params
        if (!in_array(self::method(), ['GET', 'DELETE'])) {
            $source_input = file_get_contents('php://input');
            $decoded_input = json_decode($source_input, true);
            if (json_last_error() == JSON_ERROR_NONE && is_array($decoded_input)) {
                $params_input = $decoded_input;
            } else {
                parse_str($source_input, $params_input);
            }
            $params = array_merge($params, $params_input);
        }

        // Read header Access-Token
        $params['token'] = self::getToken();

        // Auth
        if (!empty($params['auth'])) {
            if (empty($params['user_id'])) {
                $params['user_id'] = (!empty($_SERVER['HTTP_AUTH_ACCOUNT']) ? $_SERVER['HTTP_AUTH_ACCOUNT'] : uniqid(rand(), true));
            }
            if (empty($params['password'])) {
                $params['password'] = (!empty($_SERVER['HTTP_AUTH_PASSWORD']) ? $_SERVER['HTTP_AUTH_PASSWORD'] : uniqid(rand(), true));
            }
            unset($params['token']);
        }

        // Check token
        if (!empty($params['check_auth']) && empty($params['check_token'])) {
            $params['check_token'] = (!empty($_SERVER['HTTP_ACCESS_TOKEN']) ? $_SERVER['HTTP_ACCESS_TOKEN'] : uniqid(rand(), true));
            unset($params['token']);
        }

        if (empty($params['token'])) {
            unset($params['token']);
        }

        if ($sanitize == true) {
            $params = self::sanitizeParams($params);
        }

        return $params;
    }

    /**
     * Parse url rewrite.
     */
    public static function parseUrlRewrite()
    {
        if (!self::$urlparsed) {
            $formats = ['json', 'xml', 'html'];
            $rewrite_regex = [
                // Check Auth
                'auth/check' => 'check_auth=1&format=%s',
                // Auth
                'auth' => 'auth=1&format=%s',
                // Dataset + P1 + P2 + P3 + P4 (Custom requests)
                '([^/]+)/([^/]+)/([^/]+)/([^/]+)/([^/]+)' => 'custom=%s&db=%s&table=%s&where[%s]=%s&format=%s',
                // Dataset + Table + Column + Value
                '([^/]+)/([^/]+)/([^/]+)/([^/]+)' => 'db=%s&table=%s&where[%s]=%s&format=%s',
                // Dataset + Limit + Table
                '([^/]+)/([0-9]|[1-8][0-9]|9[0-9]|100)/([^/]+)' => 'db=%s&limit=%s&table=%s&format=%s',
                // Dataset + Table docs
                '([^/]+)/docs/([^/]+)' => 'db=%s&table=%s&format=%s&docs=true',
                // Dataset + Table + ID
                '([^/]+)/([^/]+)/([^/]+)' => 'db=%s&table=%s&id=%s&format=%s',
                // Dataset + Check counter
                '([^/]+)/check_counter' => 'db=%s&check_counter=true&format=%s',
                // Dataset + Table
                '([^/]+)/([^/]+)' => 'db=%s&table=%s&format=%s',
                // Dataset (for POST/PUT/PATCH requests)
                '([^/]+)' => 'db=%s&format=%s',
            ];

            $formats = implode('|', $formats);
            $formats = "\.($formats)$";

            // Parse rewrites
            foreach ($rewrite_regex as $regex => $qs) {
                $request_uri = self::getRequestURI();
                if (preg_match('#' . $regex . $formats . '#', $request_uri, $matches)) {
                    array_shift($matches);
                    $matches = array_filter($matches, function ($v) {
                        return urlencode($v);
                    });

                    $query_string = vsprintf($qs, $matches);
                    if ($_SERVER['QUERY_STRING'] != $query_string) {
                        $_SERVER['QUERY_STRING'] = $query_string . (!empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '');
                    }
                    parse_str($_SERVER['QUERY_STRING'], $_GET);
                    break;
                }
            }
            self::$urlparsed = true;
        }
    }

    /**
     * Get Request URI.
     *
     * @return string
     */
    public static function getRequestURI()
    {
        $base = '';
        $doc_root = realpath(preg_replace('/' . preg_quote($_SERVER['SCRIPT_NAME'], '/') . '$/', '', $_SERVER['SCRIPT_FILENAME']));
        if (realpath(__API_ROOT__) != realpath($_SERVER['DOCUMENT_ROOT'])) {
            $base = str_replace(realpath($_SERVER['DOCUMENT_ROOT']), '', __API_ROOT__) . '/';
        } elseif (realpath(__API_ROOT__) != $doc_root) {
            $base = str_replace($doc_root, '', __API_ROOT__) . '/';
        }
        $base = str_replace('\\', '/', $base);

        $request_uri = str_replace($base, '', $_SERVER['REQUEST_URI']);
        $request_uri = explode('?', $request_uri, 2);
        $request_uri = $request_uri[0];

        return $request_uri;
    }

    /**
     * Returns the request method.
     */
    public static function method()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Returns the access token.
     */
    public static function getToken()
    {
        $token = @$_GET['token'];
        if (isset($_SERVER['HTTP_ACCESS_TOKEN'])) {
            $token = $_SERVER['HTTP_ACCESS_TOKEN'];
        }

        return $token;
    }

    /**
     * Prevent bad bots.
     */
    public static function blockBots()
    {
        // Block bots
        if (preg_match('/(spider|crawler|slurp|teoma|archive|track|snoopy|lwp|client|libwww)/i', $_SERVER['HTTP_USER_AGENT']) ||
           preg_match('/(havij|libwww-perl|wget|python|nikto|curl|scan|java|winhttp|clshttp|loader)/i', $_SERVER['HTTP_USER_AGENT']) ||
           preg_match('/(%0A|%0D|%27|%3C|%3E|%00)/i', $_SERVER['HTTP_USER_AGENT']) ||
           preg_match("/(;|<|>|'|\"|\)|\(|%0A|%0D|%22|%27|%28|%3C|%3E|%00).*(libwww-perl|wget|python|nikto|curl|scan|java|winhttp|HTTrack|clshttp|archiver|loader|email|harvest|extract|grab|miner)/i", $_SERVER['HTTP_USER_AGENT'])) {
            Response::error('Permission denied!', 403);
        }
        // Block Fake google bot
        self::blockFakeGoogleBots();
    }

    /**
     * Sanitize from HTML injection.
     *
     * @param      $data mixed data to sanitize
     *
     * @return     $data sanitized data
     *
     * @author     Marco Cesarato <cesarato.developer@gmail.com>
     */
    public static function sanitizeHtmlentities($data)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = self::sanitizeHtmlentities($v);
            }
        } else {
            $data = htmlentities($data);
        }

        return $data;
    }

    /**
     * Prevent Fake Google Bots.
     */
    protected static function blockFakeGoogleBots()
    {
        $user_agent = (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '');
        if (preg_match('/googlebot/i', $user_agent, $matches)) {
            $ip = self::getIPAddress();
            $name = gethostbyaddr($ip);
            $host_ip = gethostbyname($name);
            if (preg_match('/googlebot/i', $name, $matches)) {
                if ($host_ip != $ip) {
                    Response::error('Permission denied!', 403);
                }
            } else {
                Response::error('Permission denied!', 403);
            }
        }
    }

    /**
     * Get IP Address.
     *
     * @return mixed
     */
    public static function getIPAddress()
    {
        foreach (
            [
                'HTTP_CLIENT_IP',
                'HTTP_CF_CONNECTING_IP',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_FORWARDED',
                'HTTP_X_CLUSTER_CLIENT_IP',
                'HTTP_FORWARDED_FOR',
                'HTTP_FORWARDED',
                'HTTP_VIA',
                'REMOTE_ADDR',
            ] as $key
        ) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    // Check for IPv4 IP cast as IPv6
                    if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/', $ip, $matches)) {
                        $ip = $matches[1];
                    }
                    if ($ip == '::1') {
                        $ip = '127.0.0.1';
                    }
                    if ($ip == '127.0.0.1' || self::isPrivateIP($ip)) {
                        $ip = $_SERVER['REMOTE_ADDR'];
                        if ($ip == '::1') {
                            $ip = '127.0.0.1';
                        }

                        return $ip;
                    }
                    if (self::validateIPAddress($ip)) {
                        return $ip;
                    }
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Detect if is private IP.
     *
     * @param $ip
     *
     * @return bool
     */
    private static function isPrivateIP($ip)
    {
        // Dealing with ipv6, so we can simply rely on filter_var
        if (false === strpos($ip, '.')) {
            return !@filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        $long_ip = ip2long($ip);
        // Dealing with ipv4
        $private_ip4_addresses = [
            '10.0.0.0|10.255.255.255',     // single class A network
            '172.16.0.0|172.31.255.255',   // 16 contiguous class B network
            '192.168.0.0|192.168.255.255', // 256 contiguous class C network
            '169.254.0.0|169.254.255.255', // Link-local address also referred to as Automatic Private IP Addressing
            '127.0.0.0|127.255.255.255',    // localhost
        ];
        if (-1 != $long_ip) {
            foreach ($private_ip4_addresses as $pri_addr) {
                list($start, $end) = explode('|', $pri_addr);
                if ($long_ip >= ip2long($start) && $long_ip <= ip2long($end)) {
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
    public static function validateIPAddress($ip)
    {
        if (strtolower($ip) === 'unknown') {
            return false;
        }

        // generate ipv4 network address
        $ip = ip2long($ip);

        // if the ip is set and not equivalent to 255.255.255.255
        if ($ip !== false && $ip !== -1) {
            // make sure to get unsigned long representation of ip
            // due to discrepancies between 32 and 64 bit OSes and
            // signed numbers (ints default to signed in PHP)
            $ip = sprintf('%u', $ip);
            // do private network range checking
            if ($ip >= 0 && $ip <= 50331647) {
                return false;
            }
            if ($ip >= 167772160 && $ip <= 184549375) {
                return false;
            }
            if ($ip >= 2130706432 && $ip <= 2147483647) {
                return false;
            }
            if ($ip >= 2851995648 && $ip <= 2852061183) {
                return false;
            }
            if ($ip >= 2886729728 && $ip <= 2887778303) {
                return false;
            }
            if ($ip >= 3221225984 && $ip <= 3221226239) {
                return false;
            }
            if ($ip >= 3232235520 && $ip <= 3232301055) {
                return false;
            }
            if ($ip >= 4294967040) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if clients use Tor.
     */
    public static function blockTor()
    {
        $ips = self::getAllIPAddress();
        $ip_server = gethostbyname($_SERVER['SERVER_NAME']);
        foreach ($ips as $ip) {
            $query = [
                implode('.', array_reverse(explode('.', $ip))),
                $_SERVER['SERVER_PORT'],
                implode('.', array_reverse(explode('.', $ip_server))),
                'ip-port.exitlist.torproject.org',
            ];
            $torExitNode = implode('.', $query);
            $dns = dns_get_record($torExitNode, DNS_A);
            if (array_key_exists(0, $dns) && array_key_exists('ip', $dns[0])) {
                if ($dns[0]['ip'] == '127.0.0.2') {
                    Response::error('Permission denied!', 403);
                }
            }
        }
    }

    /**
     * Get all client IP Address.
     *
     * @return array
     */
    public static function getAllIPAddress()
    {
        $ips = [];
        foreach (
            [
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
                'REMOTE_ADDR',
            ] as $key
        ) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    // Check for IPv4 IP cast as IPv6
                    if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/', $ip, $matches)) {
                        $ip = $matches[1];
                    }
                    if ($ip == '::1') {
                        $ips[] = '127.0.0.1';
                    } elseif (self::validateIPAddress($ip)) {
                        $ips[] = $ip;
                    }
                }
            }
        }
        if (empty($ips)) {
            $ips = ['0.0.0.0'];
        }
        $ips = array_unique($ips);

        return $ips;
    }

    /**
     * Sanitize the parameters.
     *
     * @param      $params mixed data to sanitize
     *
     * @return     $params sanitized data
     *
     * @author     Marco Cesarato <cesarato.developer@gmail.com>
     */
    private static function sanitizeParams($params)
    {
        foreach ($params as $key => $value) {
            $value = trim_all($value);
            $value = self::sanitizeRXSS($value);
            $value = self::sanitizeStriptags($value);
            $value = self::sanitizeHtmlentities($value);
            $value = self::sanitizeStripslashes($value);
            $params[$key] = $value;
        }

        return $params;
    }

    /**
     * Sanitize from XSS injection.
     *
     * @param      $data mixed data to sanitize
     *
     * @return     $data sanitized data
     *
     * @author     Marco Cesarato <cesarato.developer@gmail.com>
     */
    public static function sanitizeRXSS($data)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = self::sanitizeRXSS($v);
            }
        } else {
            $data = self::sanitizeXSS($data);
        }

        return $data;
    }

    /**
     * Sanitize from XSS injection.
     *
     * @param      $data mixed data to sanitize
     *
     * @return     $data sanitized data
     *
     * @author     Marco Cesarato <cesarato.developer@gmail.com>
     */
    private static function sanitizeXSS($data)
    {
        $data = str_replace(['&amp;', '&lt;', '&gt;'], ['&amp;amp;', '&amp;lt;', '&amp;gt;'], $data);
        $data = preg_replace("/(&#*\w+)[- ]+;/u", '$1;', $data);
        $data = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $data);
        $data = html_entity_decode($data, ENT_COMPAT, 'UTF-8');
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
            $data = preg_replace('#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i', '', $data);
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
     * Sanitize from HTML injection.
     *
     * @param      $data mixed data to sanitize
     *
     * @return     $data sanitized data
     *
     * @author     Marco Cesarato <cesarato.developer@gmail.com>
     */
    public static function sanitizeStriptags($data)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = self::sanitizeStriptags($v);
            }
        } else {
            $data = strip_tags($data);
        }

        return $data;
    }

    /**
     * Sanitize from SQL injection.
     *
     * @param      $data mixed data to sanitize
     *
     * @return     $data sanitized data
     *
     * @author     Marco Cesarato <cesarato.developer@gmail.com>
     */
    public static function sanitizeStripslashes($data)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = self::sanitizeStripslashes($v);
            }
        } else {
            if (get_magic_quotes_gpc()) {
                $data = stripslashes($data);
            }
        }

        return $data;
    }

    /**
     * Returns the request referer.
     */
    public static function referer()
    {
        return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    }

    /**
     * Detect if is console.
     *
     * @return bool
     */
    public static function isConsole()
    {
        if (defined('STDIN')) {
            return true;
        }
        if (php_sapi_name() === 'cli') {
            return true;
        }
        if (array_key_exists('SHELL', $_ENV)) {
            return true;
        }
        if (empty($_SERVER['REMOTE_ADDR']) and !isset($_SERVER['HTTP_USER_AGENT']) and count($_SERVER['argv']) > 0) {
            return true;
        }
        if (!array_key_exists('REQUEST_METHOD', $_SERVER)) {
            return true;
        }

        return false;
    }
}

$request = new Request();

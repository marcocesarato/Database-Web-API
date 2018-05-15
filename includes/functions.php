<?php
/**
 * Functions
 *
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */

function register_db_api($name, $args) {
	$API = API::getInstance();
	$API->register($name, $args);
}

if (!function_exists('shortcode_atts')) {

    /**
     * Combine user attributes with known attributes and fill in defaults when needed.
     *
     * The pairs should be considered to be all of the attributes which are
     * supported by the caller and given as a list. The returned attributes will
     * only contain the attributes in the $pairs list.
     *
     * If the $atts list has unsupported attributes, then they will be ignored and
     * removed from the final returned list.
     *
     * @from Wordpress
     * @since 2.5
     *
     * @param array $pairs Entire list of supported attributes and their defaults.
     * @param array $atts User defined attributes in shortcode tag.
     * @return array Combined and filtered attribute list.
     */
    function shortcode_atts($pairs, $atts)
    {
        $atts = (array)$atts;
        $out = array();
        foreach ($pairs as $name => $default) {
            if (array_key_exists($name, $atts)) {
                $out[$name] = $atts[$name];
            } else {
                $out[$name] = $default;
            }
        }
        return $out;
    }

    function base_url($url)
    {
        $base_dir = '';
        $hostname = $_SERVER['HTTP_HOST'];
        if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' ||
                $_SERVER['HTTPS'] == 1) ||
            isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
            $protocol = 'https://';
        } else {
            $protocol = 'http://';
        }
        if (defined("__BASE_DIR__") && !empty(__BASE_DIR__))
            $base_dir = trim(__BASE_DIR__, '/') . '/';
        return $protocol . preg_replace('#/+#', '/', $hostname . "/" . $base_dir . $url);
    }

    function trim_all($input)
    {
        if (!is_array($input))
            return trim($input);
        return array_map('trim_all', $input);
    }

}

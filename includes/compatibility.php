<?php
/**
 * Compatibility.
 *
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 * @license    http://opensource.org/licenses/gpl-3.0.html GNU Public License
 *
 * @see       https://github.com/marcocesarato/Database-Web-API
 */
if (!function_exists('shortcode_atts')) {
    /**
     * Combine user attributes with known attributes and fill in defaults when needed.
     * The pairs should be considered to be all of the attributes which are
     * supported by the caller and given as a list. The returned attributes will
     * only contain the attributes in the $pairs list.
     * If the $atts list has unsupported attributes, then they will be ignored and
     * removed from the final returned list.
     *
     * @from  Wordpress
     *
     * @param array $pairs entire list of supported attributes and their defaults
     * @param array $atts  user defined attributes in shortcode tag
     *
     * @return array combined and filtered attribute list
     *
     * @since 2.5
     */
    function shortcode_atts($pairs, $atts)
    {
        $atts = (array)$atts;
        $out = [];
        foreach ($pairs as $name => $default) {
            if (array_key_exists($name, $atts)) {
                $out[$name] = $atts[$name];
            } else {
                $out[$name] = $default;
            }
        }

        return $out;
    }
}

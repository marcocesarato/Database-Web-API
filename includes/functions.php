<?php
/**
 * Functions
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 * @copyright  Copyright (c) 2018
 * @license    http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @link       https://github.com/marcocesarato/Database-Web-API
 */


/**
 * If an array contain others arrays
 * @param $a
 * @return bool
 */
function is_multi_array($a) {
	foreach($a as $v) {
		if(is_array($v)) {
			return true;
		}
	}

	return false;
}

/**
 * Convert array to object
 * @param $array
 * @return stdClass
 */
function array_to_object($array) {
	$obj = new stdClass;
	foreach($array as $k => $v) {
		if(strlen($k)) {
			if(is_array($v)) {
				$obj->{$k} = array_to_object($v); //RECURSION
			} else {
				$obj->{$k} = $v;
			}
		}
	}

	return $obj;
}

/**
 * Check if site run over https
 * @return boolean
 */
function is_https() {
	if(isset($_SERVER['HTTP_HOST'])) {
		if(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443)
		   || !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
			return true;
		}

		return false;
	}

	return false;
}

/**
 * Site base url
 * @param $url
 * @return string
 */
function base_url($url) {
	$hostname = $_SERVER['HTTP_HOST'];
	if(is_https()) {
		$protocol = 'https://';
	} else {
		$protocol = 'http://';
	}
	$base = '';
	if(realpath(__ROOT__) != realpath($_SERVER['DOCUMENT_ROOT'])) {
		$base = str_replace(realpath($_SERVER['DOCUMENT_ROOT']), '', realpath(__ROOT__));
	}

	return $protocol . preg_replace('#/+#', '/', $hostname . "/" . $base . "/" . $url);
}

/**
 * Build site base url
 * @param $url
 * @return string
 */
function build_base_url($url) {
	if(!empty($_GET['db'])){
		$url = '/' . $_GET['db'] . '/' . $url;
	}
	if(!empty($_GET['token'])){
		$url = '/' . $_GET['token'] . '/' . $url;
	}
	return base_url($url);
}

/**
 * Trim recursive
 * @param $input
 * @return array|string
 */
function trim_all($input) {
	if(!is_array($input)) {
		return trim($input);
	}

	return array_map('trim_all', $input);
}

/**
 * Send push notification to onesignal service
 * @param $msg
 * @param array $ids
 * @return mixed
 */
function notify($title, $msg, $tags = array(), $ids = array(), $opts = array()) {
	$content = array(
		"en" => $msg
	);

	$fields = array(
		'app_id'            => API_NOTIFICATION_ID,
		'included_segments' => array('All'),
		'small_icon'        => "ic_stat_onesignal_default",
		'headings'          => $title,
		'contents'          => $content
	);

	if(!empty($tags) && count($tags) > 0) {
		$result = array();
		$count  = count($tags);
		$i      = 0;
		foreach($tags as $key => $value) {
			$i ++;
			$result[] = array("field" => "tag", "key" => $key, "relation" => "=", "value" => $value);
			if($i !== $count) {
				$result[] = array("operator" => "OR");
			}
		}
		$fields['filters'] = $result;
	}

	if(!empty($ids)) {
		$fields['include_player_ids'] = array();
	}

	$fields = array_merge($fields, $opts);

	$fields = json_encode($fields);
	$ch     = curl_init();
	curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Content-Type: application/json; charset=utf-8',
		'Authorization: Basic ' . API_NOTIFICATION_REST
	));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);

	$response = curl_exec($ch);
	curl_close($ch);

	return $response;
}

/**
 * Recusively travserses through an array to propegate SimpleXML objects
 * @param array $array the array to parse
 * @param object $xml the Simple XML object (must be at least a single empty node)
 * @return object the Simple XML object (with array objects added)
 */
function object_to_xml($array, $xml) {

	//array of keys that will be treated as attributes, not children
	$attributes = array('id');

	//recursively loop through each item
	foreach($array as $key => $value) {

		//if this is a numbered array,
		//grab the parent node to determine the node name
		if(is_numeric($key)) {
			$key = 'result';
		}

		//if this is an attribute, treat as an attribute
		if(in_array($key, $attributes)) {
			$xml->addAttribute($key, $value);

			//if this value is an object or array, add a child node and treat recursively
		} else if(is_object($value) || is_array($value)) {
			$child = $xml->addChild($key);
			$child = $this->object_to_xml($value, $child);

			//simple key/value child pair
		} else {
			$xml->addChild($key, $value);
		}

	}

	return $xml;
}

/**
 * Clean up XML domdocument formatting and return as string
 * @param $xml
 * @return string
 */
function tidy_xml($xml) {
	$dom                     = new DOMDocument();
	$dom->preserveWhiteSpace = false;
	$dom->formatOutput       = true;
	$dom->loadXML($xml->asXML());

	return $dom->saveXML();
}

/**
 * Prevent malicious callbacks from being used in JSONP requests.
 * @param $callback
 * @return bool
 */
function jsonp_callback_filter($callback) {
	// As per <http://stackoverflow.com/a/10900911/1082542>.
	if(preg_match('/[^0-9a-zA-Z\$_]|^(abstract|boolean|break|byte|case|catch|char|class|const|continue|debugger|default|delete|do|double|else|enum|export|extends|false|final|finally|float|for|function|goto|if|implements|import|in|instanceof|int|interface|long|native|new|null|package|private|protected|public|return|short|static|super|switch|synchronized|this|throw|throws|transient|true|try|typeof|var|volatile|void|while|with|NaN|Infinity|undefined)$/', $callback)) {
		return false;
	}

	return $callback;
}

/**
 * Debug dump
 * @param bool $die
 */
function dump() {
	ob_clean();
	$args = func_get_args();
	if(!ini_get("xdebug.overload_var_dump")) {
		echo "<pre>";
		var_dump($args);
		echo "</pre>";
	} else {
		var_dump($args);
	}
	die();
}
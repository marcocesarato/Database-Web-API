<?php
/**
 * Index
 *
 * @package    Database API Platform
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */

include dirname(__FILE__).'/includes/loader.php';
$query = $API->parse_params();
$results = $API->query();
$renderer = 'render_' . $query['format'];
$API->$renderer($results, $query);

<?php
/**
 * Hooks - doc_testate
 *
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */

$hooks = Hooks::getInstance();

/**
 * On write doc testate
 * @param $data
 * @return mixed
 */
function filter_on_write_doc_testate($data, $table){
	$db = API::getConnection(); // PDO Object
	$date = substr((!empty($data['data_registrazione']) ? $data['data_registrazione'] : date('Y-m-d')), 0, 4);
	$query = "SELECT new_serial(:data, :table, 'progressivo') as serial";
	$sth = $db->prepare($query);
	$sth->bindValue(':data', $date);
	$sth->bindValue(':table', $table);
	$sth->execute();
	$result = $sth->fetch(PDO::FETCH_ASSOC);
	$data['progressivo'] = $result['serial'];
	return $data;
}
$hooks->add_filter('on_write_doc_testate', 'filter_on_write_doc_testate');
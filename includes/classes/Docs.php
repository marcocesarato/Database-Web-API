<?php

namespace marcocesarato\DatabaseAPI;

/**
 * Docs
 *
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 * @copyright  Copyright (c) 2019
 * @license    http://opensource.org/licenses/gpl-3.0.html GNU Public License
 *
 * @see       https://github.com/marcocesarato/Database-Web-API
 */
class Docs
{
	public static $instance;

	private $api;
	private $db;
	private $logger;
	private $hooks;

	/**
	 * Singleton constructor.
	 */
	public function __construct()
	{
		self::$instance = &$this;
		$this->logger = Logger::getInstance();
		$this->hooks = Hooks::getInstance();
		$this->api = API::getInstance();
		$this->db = &$this->api->connect();
	}

	/**
	 * Returns static reference to the class instance.
	 */
	public static function &getInstance()
	{
		return self::$instance;
	}

	/**
	 * Generate Open API documentation
	 * @return array
	 */
	public function generate()
	{
		$docs = array(
			'openapi' => '3.0.0',
			"servers" => array(
				array(
					"url" => base_domain()
				)
			),
			"info" => array(
				"description" => "Neterprise API documentation",
				"version" => "3.0.0",
				"title" => "Neterprise API",
			),
			'paths'   => array(),
			'components' => array(
			'securitySchemes' => array(
					'api_key' => array(
						"type" => "apiKey",
	                    "name" => "Access-Token",
						"in" => "header"
					),
				),
			),
		);

		$methods = array('POST', 'GET', 'PATCH', 'PUT', 'DELETE');
		$contentTypes = array("application/json");
		$tables = $this->api->getDatabase()->table_list;
		$schemas = array();
		if(empty($tables)) {
			$tables = $this->api->getTables();
		}
		foreach ($tables as $table) {
			if ($this->api->checkTable($table)) {
				$path = build_base_url('/' . $table . '.' . $this->api->query['format']);
				$parse = parse_url($path);
				$path = $parse["path"];
				$tag = ucwords(str_replace('_', ' ', $table));
				foreach ($methods as $method) {
					$methodLower = strtolower($method);
					$tableId = str_replace(' ', '', $tag);
					$operationId = $methodLower . $tableId;

					$results = $this->api->getTableMeta($table, $this->api->db);
					$proprieties = array();

					$required = [];
					foreach ($results as $column) {
						if ($this->api->checkColumn($column['column_name'], $table)) {
							$docs_table = !empty($this->api->db->table_docs[$table]) ? $this->api->db->table_docs[$table] : null;
							$docs_column = !empty($docs_table[$column['column_name']]) ? $docs_table[$column['column_name']] : null;

							if (!empty($docs_table) && empty($docs_column)) {
								continue;
							}

							$tmp = array(
								'type'        => $this->convertDataType($column['data_type']),
								'description' => '',
								'example'     => '',
							);

							if(strtolower($column['is_nullable']) === 'no') {
								$required[] = $column['column_name'];
							}

							if (!empty($column['character_maximum_length'])) {
								$tmp['maxLength'] = $column['character_maximum_length'];
							}
							if (!empty($docs_column) && is_array($docs_column)) {
								if (!empty($docs_column['description'])) {
									$tmp['description'] = ucfirst($docs_column['description']);
								}
								if (!empty($docs_column['example'])) {
									$cleaned = trim($docs_column['example'], "'");
									$tmp['example'] = !empty($cleaned) ? $cleaned : $docs_column['example'];
								}
							}
							$proprieties[$column['column_name']] = $tmp;
						}
					}

					$status = ($method === "POST") ? 201 : 200;

					$schemas[$tableId] = array(
						"properties" => $proprieties,
						"type" => "object",
						"required" => $required
					);

					$content = array();
					foreach ($contentTypes as $contentType) {
						$content[$contentType] = array(
							"schema" => array(
								"\$ref" => "#/components/schemas/$tableId"
							)
						);
					}

					$docs["paths"][$path][$methodLower] = array(
						"summary" => "$tag resource $method operation",
						"operationId" => $operationId,
						"tags"        => array($tag),
						"responses"    => array(
							$status => array(
								"description" => "OK"
							),
							"400" => array(
								"description" => "Bad Request"
							),
						),
					);

					if(in_array($method, array('POST', 'PUT', 'PATCH'))) {
						$docs["paths"][$path][$methodLower]["requestBody"] = array(
							"required" => true,
							"content"  => $content
						);
					}
				}
			}
		}

		$docs["components"]["schemas"] = $schemas;

		return $docs;
	}

	/**
	 * Database type to Open API
	 * @param $type
	 * @return string
	 */
	private function convertDataType($type)
	{
		$type = strtolower($type);
		switch ($type) {
			case "bigint":
			case "int8":
			case "serial8":
			case "bigserial":
				return "integer"; //long

			case "bit":
			case "int":
			case "smallint":
			case "int2":
			case "integer":
			case "int4":
			case "smallserial":
			case "serial2":
			case "serial":
			case "serial4":
				return "integer";

			case "real":
			case "float4":
			case "double precision":
			case "float8":
			case "decimal":
			case "double":
				return "number"; // float

			case "boolean":
			case "bool":
				return "boolean";

			case 'bpchar':
			case "varchar":
			case "char":
			case "character varying":
			case "character":
			case "bit varying":
			case "text":
				return "string";

			case "date":
				return "string"; // date

			case "time":
			case "timestamp":
				return "string"; // dateTime
		}
		return $type;
	}
}

$DOCS = new Docs();

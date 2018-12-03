<?php
/** File PDO_sqlite.class.php		*
 *(C) Andrea Giammarchi [2005/10/13]	*/

// Requires PDOStatement_sqlite.class.php , drived by PDO.class.php file
require_once('PDOStatement_sqlite.class.php');

/**
 * Class PDO_sqlite
 * 	This class is used from class PDO to manage a SQLITE version 2 database.
 *      Look at PDO.clas.php file comments to know more about SQLITE connection.
 * ---------------------------------------------
 * @Compatibility	>= PHP 4
 * @Dependencies	PDO.class.php
 * 			PDOStatement_sqlite.class.php
 * @Author		Andrea Giammarchi
 * @Site		http://www.devpro.it/
 * @Mail		andrea [ at ] 3site [ dot ] it
 * @Date		2005/10/13
 * @LastModified	2005/18/14 12:30
 * @Version		0.1 - tested
 */ 
class PDO_sqlite {
	
	/**
	 * 'Private' variables:
	 *	__connection:Resource		Database connection
         *	__dbinfo:String			Database filename
         *      __persistent:Boolean		Connection mode, is true on persistent, false on normal (deafult) connection
         *      __errorCode:String		Last error code
         *      __errorInfo:Array		Detailed errors
	 */
	var $__connection;
	var $__dbinfo;
	var $__persistent = false;
	var $__errorCode = '';
	var $__errorInfo = Array('');
	
	/**
	 * Public constructor:
	 *	Checks connection and database selection
         *       	new PDO_mysql( &$host:String, &$db:String, &$user:String, &$pass:String )
	 * @Param	String		host with or without port info
         * @Param	String		database name
         * @Param	String		database user
         * @Param	String		database password
	 */
	function PDO_sqlite(&$string_dsn) {
		if(!@$this->__connection = &sqlite_open($string_dsn))
			$this->__setErrors('DBCON', true);
		else
			$this->__dbinfo = &$string_dsn;
	}
	
	/** NOT NATIVE BUT MAYBE USEFULL FOR PHP < 5.1 PDO DRIVER
	 * Public method
         * Calls sqlite_close function.
	 *	this->close( Void ):Boolean
         * @Return	Boolean		True on success, false otherwise
	 */
	function close() {
		$result = is_resource($this->__connection);
		if($result) {
			sqlite_close($this->__connection);
		}
		return $result;
	}
	
	/**
	 * Public method:
	 *	Returns a code rappresentation of an error
         *       	this->errorCode( void ):String
         * @Return	String		String rappresentation of the error
	 */
	function errorCode() {
		return $this->__errorCode;
	}
	
	/**
	 * Public method:
	 *	Returns an array with error informations
         *       	this->errorInfo( void ):Array
         * @Return	Array		Array with 3 keys:
         * 				0 => error code
         *                              1 => error number
         *                              2 => error string
	 */
	function errorInfo() {
		return $this->__errorInfo;
	}
	
	/**
	 * Public method:
	 *	Excecutes a query and returns affected rows
         *       	this->exec( $query:String ):Mixed
         * @Param	String		query to execute
         * @Return	Mixed		Number of affected rows or false on bad query.
	 */
	function exec($query) {
		$result = 0;
		if(!is_null($this->__uquery($query)))
			$result = sqlite_changes($this->__connection);
		if(is_null($result))
			$result = false;
		return $result;
	}
	
	/**
	 * Public method:
	 *	Returns last inserted id
         *       	this->lastInsertId( void ):Number
         * @Return	Number		Last inserted id
	 */
	function lastInsertId() {
		return sqlite_last_insert_rowid($this->__connection);
	}
	
	/**
	 * Public method:
	 *	Returns a new PDOStatement
         *       	this->prepare( $query:String, $array:Array ):PDOStatement
         * @Param	String		query to prepare
         * @Param	Array		this variable is not used but respects PDO original accepted parameters
         * @Return	PDOStatement	new PDOStatement to manage
	 */
	function prepare($query, $array = Array()) {
		return new PDOStatement_sqlite($query, $this->__connection, $this->__dbinfo);
	}
	
	/**
	 * Public method:
	 *	Executes directly a query and returns an array with result or false on bad query
         *       	this->query( $query:String ):Mixed
         * @Param	String		query to execute
         * @Return	Mixed		false on error, array with all info on success
	 */
	function query($query) {
		$query = @sqlite_unbuffered_query($query, $this->__connection, $this->__dbinfo);
		if($query) {
			$result = Array();
			while($r = sqlite_fetch_array($query, SQLITE_ASSOC))
				array_push($result, $r);
		}
		else {
			$result = false;
			$this->__setErrors('SQLER');
		}
		return $result;
	}
	
	/**
	 * Public method:
	 *	Quotes correctly a string for this database
         *       	this->quote( $string:String ):String
         * @Param	String		string to quote
         * @Return	String		a correctly quoted string
	 */
	function quote($string) {
		return ("'".sqlite_escape_string($string)."'");
	}


	// NOT TOTALLY SUPPORTED PUBLIC METHODS
        /**
	 * Public method:
	 *	Quotes correctly a string for this database
         *       	this->getAttribute( $attribute:Integer ):Mixed
         * @Param	Integer		a constant [	PDO_ATTR_SERVER_INFO,
         * 						PDO_ATTR_SERVER_VERSION,
         *                                              PDO_ATTR_CLIENT_VERSION,
         *                                              PDO_ATTR_PERSISTENT	]
         * @Return	Mixed		correct information or null
	 */
	function getAttribute($attribute) {
		$result = null;
		switch($attribute) {
			case PDO_ATTR_SERVER_INFO:
				$result = sqlite_libencoding();
				break;
			case PDO_ATTR_SERVER_VERSION:
			case PDO_ATTR_CLIENT_VERSION:
				$result = sqlite_libversion();
				break;
			case PDO_ATTR_PERSISTENT:
				$result = $this->__persistent;
				break;
		}
		return $result;
	}
	
	/**
	 * Public method:
	 *	Sets database attributes, in this version only connection mode.
         *       	this->setAttribute( $attribute:Integer, $mixed:Mixed ):Boolean
         * @Param	Integer		PDO_* constant, in this case only PDO_ATTR_PERSISTENT
         * @Param	Mixed		value for PDO_* constant, in this case a Boolean value
         * 				true for permanent connection, false for default not permament connection
         * @Return	Boolean		true on change, false otherwise
	 */
	function setAttribute($attribute, $mixed) {
		$result = false;
		if($attribute === PDO_ATTR_PERSISTENT && $mixed != $this->__persistent) {
			$result = true;
			$this->__persistent = (boolean) $mixed;
			sqlite_close($this->__connection);
			if($this->__persistent === true)
				$this->__connection = &sqlite_popen($this->__dbinfo);
			else
				$this->__connection = &sqlite_open($this->__dbinfo);
		}
		return $result;
	}
	
	
	// UNSUPPORTED PUBLIC METHODS
	function beginTransaction() {
		return false;
	}
	
	function commit() {
		return false;
	}
	
	function rollBack() {
		return false;
	}
	
	
	// PRIVATE METHODS
	function __setErrors($er, $connection = false) {
		if(!is_resource($this->__connection)) {
			$errno = 1;
			$errst = 'Unable to open or find database.';
		}
		else {
			$errno = sqlite_last_error($this->__connection);
			$errst = sqlite_error_string($errno);
		}
		$this->__errorCode = &$er;
		$this->__errorInfo = Array($this->__errorCode, $errno, $errst);
	}
	
	function __uquery(&$query) {
		if(!@$query = sqlite_query($query, $this->__connection)) {
			$this->__setErrors('SQLER');
			$query = null;
		}
		return $query;
	}
}
?>
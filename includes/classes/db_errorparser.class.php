<?php

/**
 * Database error parser Class
 *
 * @package    Database Web API
 * @author     Marco Cesarato <cesarato.developer@gmail.com>
 */

class DatabaseErrorParser {
	public function __construct() {
	}

	public static function errorMessage($error, $db_type = 'pgsql') {

		$code = $error->getCode();
		$message = $error->getMessage();

		$error = "($code) Ops qualcosa e' andato storto";

		switch ($db_type) {
			case 'pgsql':
				switch ($code) {

					// INVALID DATA LENGTH
					/*
						ERRORE: il valore è troppo lungo per il tipo character varying(3)
						Stato SQL: 22001
					*/
					case 22001:
						$error = 'Il campo inserito e troppo lungo';
						break;

					// NOT NULL
					/*
						ERRORE: valori null nella colonna "id" violano il vincolo non-null
						Stato SQL: 23502
						Dettaglio: La riga in errore contiene (null, admin, ad/Cxa4.8ueHM...
					 */
					case 23502:
						$error = preg_replace_callback('/^valori null nella colonna "(.*)" violano il vincolo non-null$/', 'self::notNullError', $message);
						break;

					// FOREIGN KEY
					/*
						ERRORE: la INSERT o l'UPDATE sulla tabella "users" viola il vincolo di chiave esterna "users_address_state_fkey"
						Stato SQL: 23503
						Dettaglio: La chiave (address_state)=(BAN) non è presente nella tabella "paesi".
					*/
					case 23503:
						$error = preg_replace_callback('/^la INSERT o l\'UPDATE sulla tabella "(.*)" viola il vincolo di chiave esterna "(.*)"$/', 'self::foreignKeyError', $message);
						break;

					// UNIQUE
					/*
						ERRORE: un valore chiave duplicato viola il vincolo univoco "users_id_key"
						Stato SQL: 23505
						Dettaglio: La chiave (id)=(1) esiste già .
					 */
					case 23505:
						$error = preg_replace_callback('/^un valore chiave duplicato viola il vincolo univoco "(.*)"$/', 'self::duplicateError', $message);
						break;
				}
				break;
		}
		return $error;
	}

	private static function duplicateError($m) {
		return sprintf("Il campo %s esiste gia'. Si prega di modificare il campo per poter proseguire", $m[1]);
	}

	private static function notNullError($m) {
		return sprintf("Il campo %s non puo' essere vuoto. Si prega di compilare il campo per poter proseguire", $m[1]);
	}

	private static function foreignKeyError($m) {
		$m[2] = str_replace($m[1] . '_', '', $m[2]);
		$m[2] = str_replace('_fkey', '', $m[2]);
		return sprintf("Il campo %s non puo' essere vuoto. Si prega di compilare il campo per poter proseguire", $m[2]);
	}
}
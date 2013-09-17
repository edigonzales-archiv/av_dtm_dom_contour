<?php

/**
* Klasse zur Abstraktion von Datenbankanbindungen
*
* @author Dr. Horst Duester
* @version 1.1 - 03.03.2006
* @copyright Amt fuer Geoinformation
* @package class.db.php
* @link http://apollo/cgi-bin/cvsweb.cgi/MapServer/projekte/arp/bauges/php/main.php CVS-Repository
* @filesource
*/
class dbObj {

	/* Private Variables      ******************************************* */
	var $db_type;
	var $db_conn;
	var $db_cursor;
	var $db_user;
	var $db_name;
	var $db_host;
	var $db_port;
	var $tmp_path = "/opt/wwwroot/sogis/gifs";
	var $web_tmp_path = "http://localhost/sogis/gifs";

	/**
	* dbObj Konstruktor 
	* @param string Datenbank Typ pg||ora||mssql||dbf
	* @param string Connectionstring in Abhängigkeit der jeweiligen Datenbank (pg dbname=sogis host=localhost ...)
	* @param string Passwort
	*/
	function dbObj($typ = 'pg', $connection = 'dbname=sogis host=srsofaioi4531 user=hdus port=5432', $password = '') {
		$this->connect($typ, $connection, $password);
	}

	function connect($typ, $connection, $password) {
		$this->db_type = $typ;

		// Mit PostgreSQL verbinden				 
		if ($this->db_type == "pg") {
			/* parsen des DB-Connections Strings und setzen der Verbindung.
			* Als Ergebnis liegt das assiziative Array $conn_arr vor:
			* $db_conn_arr[DBNAME]
			* $db_conn_arr[HOST]
			* $db_conn_arr[USER]
			* $db_conn_arr[PORT]
			*/

			$conn_arr_tmp = explode(" ", $connection);

			for ($i = 0; $i < count($conn_arr_tmp); $i++) {
				$tmp_arr = explode("=", ltrim(rtrim($conn_arr_tmp[$i])));
				$db_conn_arr[strtolower($tmp_arr[0])] = $tmp_arr[1];
			}

			$this->db_name = $db_conn_arr["dbname"];
			$this->db_host = $db_conn_arr["host"];
			$this->db_user = $db_conn_arr["user"];
			$this->db_port = $db_conn_arr["port"];
//			die("dbname=" . $this->db_name . " host=" . $this->db_host . " user=" . $this->db_user . " port=" . $this->db_port . " password=" . $password);      
			$this->db_cursor = pg_connect("dbname=" . $this->db_name . " host=" . $this->db_host . " user=" . $this->db_user . " port=" . $this->db_port . " password=" . $password);
			return;
		}

		// Mit ORACLE via ora verbinden		
		else
			if ($this->db_type == "ora") {
				if (strlen($connection) == 0) {
					$connection = "sogis@sogis";
					$password = "sogis";
				} else {
					$ora_arr = explode("@", $connection);
				}
				$conn = ora_logon($connection, $password);
				if (!$conn) {
					$this->post_ora_error($conn);
					$this->db_conn = "NULL";
				} else {
					$this->db_conn = $conn;
					$this->db_cursor = ora_open($conn);
				}
			}

		// Mit ORACLE via OCI verbinden		
		else
			if ($this->db_type == "oci") {
				if (strlen($connection) == 0) {
					$connection = "sogis@sogis";
					$password = "sogis";
				} else {
					$ora_arr = explode("@", $connection);
				}
				$conn = ocilogon($ora_arr[0], $password, $ora_arr[1]);
				if (!$conn) {
					$this->post_oci_error($conn);
					$this->db_conn = "NULL";
				} else {
					$this->db_conn = $conn;
				}
			}

		// Mit MS-SQL Datenbankserver verbinden		
		else
			if ($this->db_type == "mssql") {

				// Default Verbindung zur Datenbank MJPNL		
				if (strlen($connection) == 0) {
					$connection = "natur@MJPNL";
					$password = "myoko";
				} else {
					$conn_arr = explode("@", $connection);
				}
				$conn = mssql_connect($conn_arr[1], $conn_arr[0], $password);
				if (!$conn) {
					echo "MS-SQL Verbindung ist fehlgeschlagen";
					return;
				} else {
					$this->db_cursor = $conn;
				}
			}

		// Mit dBase Datei verbinden		
		else
			if ($this->db_type == "dbf") {
				if (strlen($connection) == 0) {
					echo "Kein DBF-Dateiname angegeben";
					return;
				} else {
					$conn_arr = explode(",", $connection);
				}
				$conn = dbase_open($conn_arr[0], $conn_arr[1]);
				if (!$conn) {
					echo "Öffnen der DBF-Datei ist fehlgeschlagen";
					return;
				} else {
					$this->db_cursor = $conn;
				}
			} else {
				echo "Eine Datenbank vom Typ " . $this->db_type . " wird nicht unterstuetzt";
				return;
			}

		return;
	}

	/**
	* schliessen einer DB Verbindung
	*/
	function disconnect() {
		if ($this->db_type == "pg") {
			// No action due to PG doesn't need disconnection
		}
		if ($this->db_type == "ora") {
			ora_logoff($this->db_conn);
		}
		if ($this->db_type == "oci") {
			OCILogOff($this->db_conn);
		}
		if ($this->db_type == "mssql") {
			mssql_close($this->db_conn);
		}
		if ($this->db_type == "dbf") {
			dbase_close($this->db_conn);
		}
		return;
	}

	/**
	* Lesen des Ergebnisses in ein 2-Dim Array [FELDNAME][RecNo]
	* @param string Abfrage
	* @return array 2-Dim Array [FELDNAME][RecNo]
	*/
	function read($abfrage = '') {

		//  PostgreSQL			 
		if ($this->db_type == "pg") {
			if (isset ($datensatz)) {
				unset ($datensatz);
			}
			$result = pg_query($this->db_cursor, $abfrage);
			for ($i = 0; $i < pg_numrows($result); $i++) {
				$arr = pg_fetch_array($result, $i);
				for ($n = 0; $n < pg_numfields($result); $n++) {
					$datensatz[strtoupper(pg_fieldName($result, $n))][$i] = $arr[$n];
				}
			}
			return $datensatz;
		}

		//  Oracle ORA					
		else
			if ($this->db_type == "ora") {
				$stmt = ora_parse($this->db_cursor, $abfrage);
				$stmt = ora_exec($this->db_cursor);
				$n = 0;
				if (isset ($datensatz)) {
					unset ($datensatz);
				}
				while (ora_fetch($this->db_cursor) == 1) {
					for ($i = 0; $i < ora_numcols($this->db_cursor); $i++) {
						$datensatz[ora_columnName($this->db_cursor, $i)][$n] = ora_getColumn($this->db_cursor, $i);
					}
					$n++;
				}
				return $datensatz;
			}

		//  Oracle OCI					
		else
			if ($this->db_type == "oci") {
				$stmt = OCIParse($this->db_conn, $abfrage);
				OCIExecute($stmt);
				empty($results);
				$rows = OCIFetchstatement($stmt, $results);
				return $results;
			}

		// MS_SQL		
		else
			if ($this->db_type == "mssql") {
				if (isset ($datensatz)) {
					unset ($datensatz);
				}
				$result = mssql_query($abfrage, $this->db_cursor);
				for ($i = 0; $i < mssql_num_rows($result); $i++) {
					$arr = mssql_fetch_array($result, $i);
					for ($n = 0; $n < mssql_num_fields($result); $n++) {
						$datensatz[strtoupper(mssql_field_name($result, $n))][$i] = $arr[$n];
					}
				}
				return $datensatz;
			}

		// DBF-Datei
		else
			if ($this->db_type == "dbf") {
				if (isset ($datensatz)) {
					unset ($datensatz);
				}
				$nrecs = dbase_numrecords($this->db_cursor);
				for ($i = 1; $i < $nrecs +1; $i++) {
					$rec = dbase_get_record_with_names($this->db_cursor, $i);
					while (list ($key, $value) = each($rec)) {
						$datensatz[$key][$i -1] = $value;
					}
				}
				return $datensatz;
			}
	}

	/**
	* Ausfuehren einer Query ohne Rueckgabeergebnis
	* @param string Abfrage
	* @return void
	*/
	function run($abfrage) {

		// Fuer PostgreSQL			 
		// Im Fall eines Fehlers wird die Fehlermeldung ausgegeben			 
		if ($this->db_type == "pg") {
			pg_query($this->db_cursor, $abfrage);
		}

		// Fuer Oracle ORA					
		else
			if ($this->db_type == "ora") {
				$stmt = ora_parse($this->db_cursor, $abfrage);
				$stmt = ora_exec($this->db_cursor);
			}

		// Fuer Oracle OCI					
		else
			if ($this->db_type == "oci") {
				$stmt = OCIParse($this->db_cursor, $abfrage);
				$stmt = OCIExec($this->db_cursor);
			}

		// Fuer MS_SQL		
		else
			if ($this->db_type == "mssql") {
				$result = mssql_query($abfrage, $this->db_cursor);
			}
	}

	/**
	* Gibt die Spalten eines Abfrageergebnisses zurueck
	* @param array Datenbank Typ pg||ora||mssql||dbf
	* @return array 1-Dim Array der Spaltennamen
	*/
	function cols($result) {
		if (isset ($col_arr)) {
			unset ($col_arr);
		}
		if (!empty ($result))
			foreach ($result as $key => $unit)
				$col_arr[] = $key;
		return $col_arr;
	}

	/**
	* Gibt die Datentypen der Spalten eines Abfrageergebnisses zurueck
	* @param string Abfrage
	* @return array 1-Dim Array der Datentypen
	*/
	function coltype($abfrage) {
		// Fuer PostgreSQL			 
		// Im Fall eines Fehlers wird die Fehlermeldung ausgegeben			 
		if ($this->db_type == "pg") {

		}

		// Fuer Oracle ORA					
		else
			if ($this->db_type == "ora") {

			}

		// Fuer Oracle OCI					
		else
			if ($this->db_type == "oci") {
				$stmt = OCIParse($this->db_conn, $abfrage);
				OCIExecute($stmt);
				$ncols = OCINumCols($stmt);
				for ($i = 1; $i <= $ncols; $i++) {
					$column_type[] = OCIColumnType($stmt, $i);
				}
				return $column_type;
			}

		// Fuer MS_SQL		
		else
			if ($this->db_type == "mssql") {

			}
	}

	/**
	* Ausfuehren einer Query ohne Rueckgabeergebnis aber mit Ausgabe einer Fehlermeldung
	* @param string Abfrage
	* @return string PostgreSQL Fehlermeldung
	*/
	function run_error($abfrage) {

		// Fuer PostgreSQL			 
		// Im Fall eines Fehlers wird die Fehlermeldung ausgegeben			 
		if ($this->db_type == "pg") {
			$qry_array = explode(";", $abfrage);
			for ($i = 0; $i < count($qry_array); $i++) {
				@ pg_query($this->db_cursor, $qry_array[$i]);
				$result .= @ pg_last_error($this->db_cursor);
				if ($result) {
					@ pg_query($this->db_cursor, "COMMIT;");
					return $result;
				}
			}
		}
	}

	/**
	* Ausfuehren einer Query ohne Rueckgabeergebnis aber mit Ausgabe einer Notice
	* @param string Abfrage
	* @return string PostgreSQL Notice
	*/
	function run_notice($abfrage) {

		//  PostgreSQL			 
		if ($this->db_type == "pg") {
			$qry_array = explode(";", $abfrage);
			for ($i = 0; $i < count($qry_array); $i++) {
				@ pg_query($this->db_cursor, $qry_array[$i]);
				$result .= @ pg_last_notice($this->db_cursor);
			}
			return $result;
		}
	}

	/**
	* Ausfuehren einer Query ohne Rueckgabeergebnis aber mit Ausgabe einer Notice
	* @param string Abfrage
	* @return string PostgreSQL Notice
	*/
	function read_notice($abfrage) {

		//  PostgreSQL			 
		if ($this->db_type == "pg") {
				if (isset ($datensatz)) {
					unset ($datensatz);
				}
			$result = @ pg_query($this->db_cursor, $abfrage);
			$notice = @ pg_last_notice($this->db_cursor);
			if ($notice) {
				return $notice;
			}
			for ($i = 0; $i < pg_numrows($result); $i++) {
				$arr = pg_fetch_array($result, $i);
				for ($n = 0; $n < pg_numfields($result); $n++) {
					$datensatz[strtoupper(pg_fieldName($result, $n))][$i] = $arr[$n];
				}
			}
			return $datensatz;
		}
	}

	/**
	* Liest ein Blob aus der DB und leitet dieses dierekt an den Browser weiter
	* @param oid BLOB OID
	* @param String Datentyp des BLOBS txt||html||htm||gif||jpg||png||zip||dbf||doc||xls||xlt||gz||tar||pdf
	*/
	function getBlob($lo_oid, $type) {

		//  PostgreSQL			 
		if ($this->db_type == "pg") {

			// remember, large objects must be obtained from within a transaction
			pg_query($this->db_cursor, "begin");
			$handle_lo = pg_lo_open($this->db_cursor, $lo_oid, "r") or die("<h1>Error.. can't get handle</h1>");

			//Begin writing headers
			switch ($type) {
				case "txt" :
					$content_type = "text/plain";
					break;
				case "html" :
					$content_type = "text/html";
					break;
				case "htm" :
					$content_type = "text/html";
					break;
				case "gif" :
					$content_type = "image/gif";
					break;
				case "jpg" :
					$content_type = "image/jpeg";
					break;
				case "png" :
					$content_type = "image/png";
					break;
				case "zip" :
					$content_type = "application/x-zip-compressed";
					break;
				case "dbf" :
					$content_type = "application/octet-stream";
					break;
				case "doc" :
					$content_type = "application/msword";
					break;
				case "xls" :
					$content_type = "application/vnd.ms-excel";
					break;
				case "xlt" :
					$content_type = "application/vnd.ms-excel";
					break;
				case "gz" :
					$content_type = "application/x-gzip";
					break;
				case "tar" :
					$content_type = "application/x-tar";
					break;
				case "pdf" :
					$content_type = "application/pdf";
					break;
				default :
					$content_type = "text/html";
			}

			header("Content-type: $content_type");
			header("Accept-Ranges: bytes");
			header("Cache-Control: private");

			pg_lo_read_all($handle_lo) or die("<h1>Error, can't read large object.</h1>");

			// committing the data transaction
			pg_query($this->db_cursor, "commit");
		}
	}

	/**
	* Liest ein Blob aus der DB und schreibt es in eine Datei 
	* @param oid BLOB OID
	* @param String Dateiname
	* @return String Name der erzeugten temporären Datei
	*/
	function getBlobFile($lo_oid, $filename) {

		$fname_arr = explode(".", basename($filename));
		$fname = $this->tmp_path . "/" . $fname_arr[0] . mktime() . "." . $fname_arr[1];
		$outfname = $this->web_tmp_path . "/" . $fname_arr[0] . mktime() . "." . $fname_arr[1];

		//  PostgreSQL			 
		if ($this->db_type == "pg") {

			// remember, large objects must be obtained from within a transaction
			pg_query($this->db_cursor, "begin");
			pg_lo_export($this->db_cursor, $lo_oid, $fname) or die("<h1>Error, can't read large object.</h1>");

			// committing the data transaction
			pg_query($this->db_cursor, "commit");
		}
		return $outfname;
	}
	
	/**
	 * Liefert den aktuellen DB Cursor zurueck
	 * Diese Funktion ist aus Gruenden der Abwärtskompatibilität zum pg_env
	 * Mechanismus erstellt.
	 * @param void
	 * @return DBCursor Handle ID der aktuellen DB Verbindung
	 */
	function getDBCursor() {
		if ($this->db_type == "pg" || $this->db_type == "ora") {
		    return $this->db_cursor;
		} else {
			return $this->db_conn;
		}
	}

	/**
	* Prueft, ob ein Objekt vom Typ objtyp (table||view) in der Datenbank existiert 
	* @param String OBJTyp (table||view)
	* @param String Objektname
	* @return Boolean
	*/
	function exists($objtyp, $name) {

		//  PostgreSQL			 
		if ($this->db_type == "pg") {

			$tmp_arr = explode(".", $name);

			if (count($tmp_arr) == 1) {
				$schema = "public";
			} else
				if (count($tmp_arr) == 2) {
					$schema = $tmp_arr[0];
					$name = $tmp_arr[1];
				}

			if ($objtyp == "table")
				$objtyp = "BASE TABLE";
			else
				if ($objtyp == "view")
					$objtyp = "VIEW";

				else {
					die("Es können nur Objekttypen table||view gesucht werden");
					return;
				}

			$abfrage = "select count(table_name) 
			            from information_schema.tables
			  				  where table_schema='$schema'
			  				  and table_name='$name'
									and table_type='$objtyp'";

			$result = $this->read($abfrage);

			if ($result["COUNT"][0] == 0)
				return false;
			else
				return true;
		} else {
			die("Die Methode ist nur fuer PostgreSQL implementiert");
			return;
		}
	}

	/**
	* Kopiert den Inhalt einer Tabelle in ein Array 
	* @param String Name der Tabelle, die Kopiert werden soll 
	* @return Array Array mit dem Inhalt der Tabelle
	*/
	function copyToArray($table_name) {

		if ($this->db_type == "pg") {
			$tab_arr = explode(".", $table_name);
			if (count($tab_arr) == 2) {
				$schema = $tab_arr[0];
				$table = $tab_arr[1];
			} else {
				$schema = 'public';
				$table = $tab_arr[0];
			}
			$this->run("SET search_path TO " . $schema);
			return pg_copy_to($this->db_cursor, $table);
		} else {
			die("Diese Methode ist nur fuer PostgreSQL implementiert");
		}
	}

	/**
	* Kopiert den Inhalt eines mit copyToFile erzeugten Datei in eine benannte Tabelle  
	* @param String Name der Tabelle, die Kopiert werden soll
	* @param String Die mit copyToFile erzeugte Datei 
	* @return void
	*/
	function copyToTable($table_name, $rows) {

		if ($this->db_type == "pg") {
			$tab_arr = explode(".", $table_name);
			if (count($tab_arr) == 2) {
				$schema = $tab_arr[0];
				$table = $tab_arr[1];
			} else {
				$schema = 'public';
				$table = $tab_arr[0];
			}
			$this->run("SET search_path TO " . $schema);
			pg_copy_from($this->db_cursor, $table, $rows);
		} else {
			die("Diese Methode ist nur fuer PostgreSQL implementiert");
		}
	}

	/**
	* Zeigt die Verbindungsparameter der DB Verbindung an  
	* @return String Connectioninfo
	*/
	function connectionInfo() {
		if ($this->db_type == "pg") {
			$info = "HOST: " . pg_host($this->db_cursor) . "  ";
			$info .= "DBNAME: " . pg_dbname($this->db_cursor) . "  ";
			return $info;
		} else {
			die("Diese Methode ist nur fuer PostgreSQL implementiert");
		}
	}

}
?>

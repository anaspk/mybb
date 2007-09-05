<?php
/**
 * MyBB 1.2
 * Copyright � 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/license.php
 *
 * $Id$
 */

class DB_PgSQL
{
	/**
	 * The title of this layer.
	 *
	 * @var string
	 */
	var $title = "PostgreSQL";
	
	/**
	 * The short title of this layer.
	 *
	 * @var string
	 */
	var $short_title = "PostgreSQL";

	/**
	 * A count of the number of queries.
	 *
	 * @var int
	 */
	var $query_count = 0;

	/**
	 * A list of the performed queries.
	 *
	 * @var array
	 */
	var $querylist = array();

	/**
	 * 1 if error reporting enabled, 0 if disabled.
	 *
	 * @var boolean
	 */
	var $error_reporting = 1;

	/**
	 * The database connection resource.
	 *
	 * @var resource
	 */
	var $link;
	
	/**
	 * The slave database connection resource (if we have one)
	 *
	 * @var resource
	 */
	var $slave_link;
	
	/**
	 * Reference to the last database connection resource used.
	 *
	 * @var resource
	 */
	var $current_link;

	/**
	 * Explanation of a query.
	 *
	 * @var string
	 */
	var $explain;

	/**
	 * The current version of PgSQL.
	 *
	 * @var string
	 */
	var $version;

	/**
	 * The current table type in use (myisam/innodb)
	 *
	 * @var string
	 */
	var $table_type = "myisam";

	/**
	 * The table prefix used for simple select, update, insert and delete queries
	 *
	 * @var string
	 */
	var $table_prefix;
	
	/**
	 * The temperary connection string used to store connect details
	 *
	 * @var string
	 */
	var $connect_string;
	
	/**
	 * The last query run on the database
	 *
	 * @var string
	 */
	var $last_query;
	
	/**
	 * The current value of pconnect (0/1).
	 *
	 * @var string
	 */
	var $pconnect;
	
	/**
	 * The engine used to run the SQL database
	 *
	 * @var string
	 */
	var $engine = "pgsql";

	/**
	 * The database encoding currently in use (if supported)
	 *
	 * @var string
	 */
	var $db_encoding = "utf8";
	
	/**
	 * Connect to the database server.
	 *
	 * @param string The database hostname.
	 * @param string The database username.
	 * @param string The database user's password.
	 * @param boolean 1 if persistent connection, 0 if not.
	 * @return resource The database connection resource.
	 */
	function connect($hostname="localhost", $username="root", $password="", $pconnect=0)
	{
		if($password)
		{
			$this->connection_options['master']['password'] = $password;
		}

		$this->connection_options['master']['host'] = $hostname;
		$this->connection_options['master']['user'] = $username;
		$this->connection_options['master']['pconnect'] = $pconnect;

		// Set the DB encoding accordingly
		global $mybb;
		$this->db_encoding = $mybb->config['db_encoding'];

		return true;
	}

	function slave_connect($hostname="localhost", $username="root", $password="", $pconnect=0)
	{
		if($password)
		{
			$this->connection_options['slave']['password'] = $password;
		}

		$this->connection_options['slave']['host'] = $hostname;
		$this->connection_options['slave']['user'] = $username;
		$this->connection_options['slave']['pconnect'] = $pconnect;

		return true;
	}
	
	/**
	 * Selects the database to use.
	 *
	 * @param string The database name.
	 * @return boolean True when successfully connected, false if not.
	 */
	function select_db($database)
	{
		foreach($this->connection_options as $type => $db_connection)
		{
			if(!$db_connection['host'])
			{
				continue;
			}
			$this->connect_string .= "dbname={$database} user={$db_connection['user']}";
			
			if($db_connection['port'])
			{
				$connect_string .= "port={$db_connection['host']} ";
			}
			if(strpos($db_connection['host'], ':') !== false)
			{
				list($db_connection['host'], $db_connection['port']) = explode(':', $db_connection['host']);
			}
			
			if($db_connection['host'] != "localhost")
			{
				$this->connect_string .= "host={$db_connection['host']} ";
			}
			
			if($db_connection['password'])
			{
				$this->connect_string .= " password={$db_connection['password']}";
			}
			
			if($type == "slave")
			{
				$link = "slave_link";
			}
			else
			{
				$link = "link";
			}
			if($this->connection_options['pconnect'])
			{
				$this->$link = @pg_pconnect($this->connect_string) or $this->error();
			}
			else
			{
				$this->$link = @pg_connect($this->connect_string);
			}
		}
		if($this->link && $this->db_encoding)
		{
			$this->query("SET NAMES '{$this->db_encoding}'");
			if($this->slave_link)
			{
				$this->write_query("SET NAMES '{$this->db_encoding}'");
			}
		}
		return $this->link;
	}
	
	/**
	 * Query the database.
	 *
	 * @param string The query SQL.
	 * @param boolean 1 if hide errors, 0 if not.
	 * @param integer 1 if executes on slave database, 0 if not.
	 * @return resource The query data.
	 */
	function query($string, $hide_errors=0, $write_query=0)
	{
		global $pagestarttime, $querytime, $db, $mybb;
		
		$string = preg_replace("#LIMIT ([0-9]+),([ 0-9]+)#i", "LIMIT $2 OFFSET $1", $string);
		
		$this->last_query = $string;
		
		$qtimer = new timer();
		
		
		if(strtolower(substr(ltrim($string), 0, 5)) == 'alter')
		{			
			$string = preg_replace("#\sAFTER\s([a-z_]+?)(;*?)$#i", "", $string);
		}

		if($write_query && $this->slave_link)
		{
			$this->current_link = &$this->slave_link;
			pg_send_query($this->slave_link, $string);
			$query = pg_get_result($this->slave_link);					
		}
		else
		{
			$this->current_link = &$this->link;
			pg_send_query($this->link, $string);
			$query = pg_get_result($this->link);
		}
		
		if(pg_result_error($query) && !$hide_errors)
		{
			 $this->error($string);
			 exit;
		}
		
		$qtime = $qtimer->stop();
		$querytime += $qtimer->totaltime;
		$qtimer->remove();
		$this->query_count++;
		
		if($mybb->debug_mode)
		{
			$this->explain_query($string, $qtime);
		}
		return $query;
	}
	
	/**
	 * Execute a write query on the slave database
	 *
	 * @param string The query SQL.
	 * @param boolean 1 if hide errors, 0 if not.
	 * @return resource The query data.
	 */
	function write_query($query, $hide_errors=0)
	{
		return $this->query($query, $hide_errors, 1);
	}

	/**
	 * Explain a query on the database.
	 *
	 * @param string The query SQL.
	 * @param string The time it took to perform the query.
	 */
	function explain_query($string, $qtime)
	{
		if(preg_match("#^\s*select#i", $string))
		{
			$query = pg_query($this->current_link, "EXPLAIN $string");
			$this->explain .= "<table style=\"background-color: #666;\" width=\"95%\" cellpadding=\"4\" cellspacing=\"1\" align=\"center\">\n".
				"<tr>\n".
				"<td colspan=\"8\" style=\"background-color: #ccc;\"><strong>#".$this->query_count." - Select Query</strong></td>\n".
				"</tr>\n".
				"<tr>\n".
				"<td colspan=\"8\" style=\"background-color: #fefefe;\"><span style=\"font-family: Courier; font-size: 14px;\">".$string."</span></td>\n".
				"</tr>\n".
				"<tr style=\"background-color: #efefef;\">\n".
				"<td><strong>table</strong></td>\n".
				"<td><strong>type</strong></td>\n".
				"<td><strong>possible_keys</strong></td>\n".
				"<td><strong>key</strong></td>\n".
				"<td><strong>key_len</strong></td>\n".
				"<td><strong>ref</strong></td>\n".
				"<td><strong>rows</strong></td>\n".
				"<td><strong>Extra</strong></td>\n".
				"</tr>\n";

			while($table = pg_fetch_array($query))
			{
				$this->explain .=
					"<tr bgcolor=\"#ffffff\">\n".
					"<td>".$table['table']."</td>\n".
					"<td>".$table['type']."</td>\n".
					"<td>".$table['possible_keys']."</td>\n".
					"<td>".$table['key']."</td>\n".
					"<td>".$table['key_len']."</td>\n".
					"<td>".$table['ref']."</td>\n".
					"<td>".$table['rows']."</td>\n".
					"<td>".$table['Extra']."</td>\n".
					"</tr>\n";
			}
			$this->explain .=
				"<tr>\n".
				"<td colspan=\"8\" style=\"background-color: #fff;\">Query Time: ".$qtime."</td>\n".
				"</tr>\n".
				"</table>\n".
				"<br />\n";
		}
		else
		{
			$this->explain .= "<table style=\"background-color: #666;\" width=\"95%\" cellpadding=\"4\" cellspacing=\"1\" align=\"center\">\n".
				"<tr>\n".
				"<td style=\"background-color: #ccc;\"><strong>#".$this->query_count." - Write Query</strong></td>\n".
				"</tr>\n".
				"<tr style=\"background-color: #fefefe;\">\n".
				"<td><span style=\"font-family: Courier; font-size: 14px;\">".htmlspecialchars_uni($string)."</span></td>\n".
				"</tr>\n".
				"<tr>\n".
				"<td bgcolor=\"#ffffff\">Query Time: ".$qtime."</td>\n".
				"</tr>\n".
				"</table>\n".
				"<br />\n";
		}

		$this->querylist[$this->query_count]['query'] = $string;
		$this->querylist[$this->query_count]['time'] = $qtime;
	}


	/**
	 * Return a result array for a query.
	 *
	 * @param resource The query ID.
	 * @param constant The type of array to return.
	 * @return array The array of results.
	 */
	function fetch_array($query)
	{
		$array = pg_fetch_assoc($query);
		return $array;
	}

	/**
	 * Return a specific field from a query.
	 *
	 * @param resource The query ID.
	 * @param string The name of the field to return.
	 * @param int The number of the row to fetch it from.
	 */
	function fetch_field($query, $field, $row=false)
	{
		if($row === false)
		{
			$array = $this->fetch_array($query);
			return $array[$field];
		}
		else
		{
			return pg_fetch_result($query, $row, $field);
		}
	}

	/**
	 * Moves internal row pointer to the next row
	 *
	 * @param resource The query ID.
	 * @param int The pointer to move the row to.
	 */
	function data_seek($query, $row)
	{
		return pg_result_seek($query, $row);
	}

	/**
	 * Return the number of rows resulting from a query.
	 *
	 * @param resource The query ID.
	 * @return int The number of rows in the result.
	 */
	function num_rows($query)
	{
		return pg_num_rows($query);
	}

	/**
	 * Return the last id number of inserted data.
	 *
	 * @return int The id number.
	 */
	function insert_id()
	{
		$this->last_query = str_replace(array("\r", "\n", "\t"), '', $this->last_query);
		preg_match('#INSERT INTO ([a-zA-Z0-9_\-]+)#i', $this->last_query, $matches);
				
		$table = $matches[1];		
		
		$query = $this->query("SELECT column_name FROM information_schema.constraint_column_usage WHERE table_name = '{$table}' and constraint_name = '{$table}_pkey' LIMIT 1");
		$field = $this->fetch_field($query, 'column_name');
		
		$id = $this->query("SELECT currval('{$table}_{$field}_seq') AS last_value");
		return $this->fetch_field($id, 'last_value');
	}

	/**
	 * Close the connection with the DBMS.
	 *
	 */
	function close()
	{
		@pg_close($this->link);
		if($this->slave_link)
		{
			@pg_close($this->slave_link);
		}
	}

	/**
	 * Return an error number.
	 *
	 * @return int The error number of the current error.
	 */
	function error_number()
	{
		return 0;
	}

	/**
	 * Return an error string.
	 *
	 * @return string The explanation for the current error.
	 */
	function error_string()
	{
		if($this->current_link)
		{
			return pg_last_error($this->current_link);
		}
		else
		{
			return pg_last_error();
		}		
	}

	/**
	 * Output a database error.
	 *
	 * @param string The string to present as an error.
	 */
	function error($string="")
	{
		if($this->error_reporting)
		{
			global $error_handler;
			
			if(!is_object($error_handler))
			{
				require_once MYBB_ROOT."inc/class_error.php";
				$error_handler = new errorHandler();
			}
			
			$error = array(
				"error_no" => $this->error_number($this->current_linkk),
				"error" => $this->error_string($this->current_link),
				"query" => $string
			);
			$error_handler->error(MYBB_SQL, $error);
		}
	}


	/**
	 * Returns the number of affected rows in a query.
	 *
	 * @return int The number of affected rows.
	 */
	function affected_rows()
	{
		return pg_affected_rows($this->current_link);
	}

	/**
	 * Return the number of fields.
	 *
	 * @param resource The query ID.
	 * @return int The number of fields.
	 */
	function num_fields($query)
	{
		return pg_num_fields($query);
	}

	/**
	 * Lists all functions in the database.
	 *
	 * @param string The database name.
	 * @param string Prefix of the table (optional)
	 * @return array The table list.
	 */
	function list_tables($database, $prefix='')
	{
		if($prefix)
		{
			$query = $this->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_name LIKE '".$this->escape_string($prefix)."%'");
		}
		else
		{
			$query = $this->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public'");
		}		
		
		while($table = $this->fetch_array($query))
		{
			$tables[] = $table['table_name'];
		}

		return $tables;
	}

	/**
	 * Check if a table exists in a database.
	 *
	 * @param string The table name.
	 * @return boolean True when exists, false if not.
	 */
	function table_exists($table)
	{
		$err = $this->error_reporting;
		$this->error_reporting = 0;
		
		// Execute on slave server to ensure if we've just created a table that we get the correct result
		$query = $this->write_query("SELECT COUNT(table_name) as table_names FROM information_schema.tables WHERE table_schema = 'public' AND table_name='{$this->table_prefix}{$table}'");
		
		$exists = $this->fetch_field($query, 'table_names');
		$this->error_reporting = $err;
		
		if($exists > 0)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Check if a field exists in a database.
	 *
	 * @param string The field name.
	 * @param string The table name.
	 * @return boolean True when exists, false if not.
	 */
	function field_exists($field, $table)
	{
		$err = $this->error_reporting;
		$this->error_reporting = 0;
		
		$query = $this->write_query("SELECT COUNT(column_name) as column_names FROM information_schema.columns WHERE table_name='{$this->table_prefix}{$table}' AND column_name='{$field}'");
		
		$exists = $this->fetch_field($query, "column_names");
		$this->error_reporting = $err;
		
		if($exists > 0)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Add a shutdown query.
	 *
	 * @param resource The query data.
	 * @param string An optional name for the query.
	 */
	function shutdown_query($query, $name=0)
	{
		global $shutdown_queries;
		if($name)
		{
			$shutdown_queries[$name] = $query;
		}
		else
		{
			$shutdown_queries[] = $query;
		}
	}
	
	/**
	 * Performs a simple select query.
	 *
	 * @param string The table name to be queried.
	 * @param string Comma delimetered list of fields to be selected.
	 * @param string SQL formatted list of conditions to be matched.
	 * @param array List of options, order by, order direction, limit, limit start
	 */
	
	function simple_select($table, $fields="*", $conditions="", $options=array())
	{
		$query = "SELECT ".$fields." FROM ".$this->table_prefix.$table;
		if($conditions != "")
		{
			$query .= " WHERE ".$conditions;
		}
		
		if(isset($options['order_by']))
		{
			$query .= " ORDER BY ".$options['order_by'];
			if(isset($options['order_dir']))
			{
				$query .= " ".my_strtoupper($options['order_dir']);
			}
		}
		
		if(isset($options['limit_start']) && isset($options['limit']))
		{
			$query .= " LIMIT ".$options['limit_start'].", ".$options['limit'];
		}
		else if(isset($options['limit']))
		{
			$query .= " LIMIT ".$options['limit'];
		}
		
		return $this->query($query);
	}
	
	/**
	 * Build an insert query from an array.
	 *
	 * @param string The table name to perform the query on.
	 * @param array An array of fields and their values.
	 * @return resource The query data.
	 */
	function insert_query($table, $array)
	{
		$comma = $query1 = $query2 = "";
		if(!is_array($array))
		{
			return false;
		}
		$comma = "";
		$query1 = "";
		$query2 = "";
		foreach($array as $field => $value)
		{
			$query1 .= $comma.$field;
			$query2 .= $comma."'".$value."'";
			$comma = ", ";
		}
		return $this->write_query("
			INSERT 
			INTO ".TABLE_PREFIX.$table." (".$query1.") 
			VALUES (".$query2.")
		");
	}
	
	/**
	 * Build an insert query from an array.
	 *
	 * @param string The table name to perform the query on.
	 * @param array An array of fields and their values.
	 * @return string The query string.
	 */
	function build_insert_query($table, $array)
	{
		$comma = $query1 = $query2 = "";
		if(!is_array($array))
		{
			return false;
		}
		$comma = "";
		$query1 = "";
		$query2 = "";
		foreach($array as $field => $value)
		{
			$query1 .= $comma.$field;
			$query2 .= $comma."'".$value."'";
			$comma = ", ";
		}
		return "INSERT 
			INTO ".TABLE_PREFIX.$table." (".$query1.") 
			VALUES (".$query2.")";
	}

	/**
	 * Build an update query from an array.
	 *
	 * @param string The table name to perform the query on.
	 * @param array An array of fields and their values.
	 * @param string An optional where clause for the query.
	 * @param string An optional limit clause for the query.
	 * @return resource The query data.
	 */
	function update_query($table, $array, $where="", $limit="")
	{
		if(!is_array($array))
		{
			return false;
		}
		$comma = "";
		$query = "";
		foreach($array as $field => $value)
		{
			$query .= $comma.$field."='".$value."'";
			$comma = ", ";
		}
		if(!empty($where))
		{
			$query .= " WHERE $where";
		}
		if(!empty($limit))
		{
			$query .= " LIMIT $limit";
		}
		return $this->write_query("
			UPDATE {$this->table_prefix}$table 
			SET $query
		");
	}
	
	/**
	 * Build an update query from an array.
	 *
	 * @param string The table name to perform the query on.
	 * @param array An array of fields and their values.
	 * @param string An optional where clause for the query.
	 * @param string An optional limit clause for the query.
	 * @return string The query string.
	 */
	function build_update_query($table, $array, $where="", $limit="")
	{
		if(!is_array($array))
		{
			return false;
		}
		$comma = "";
		$query = "";
		foreach($array as $field => $value)
		{
			$query .= $comma.$field."='".$value."'";
			$comma = ", ";
		}
		if(!empty($where))
		{
			$query .= " WHERE $where";
		}
		if(!empty($limit))
		{
			$query .= " LIMIT $limit";
		}
		return $this->query("
			UPDATE {$this->table_prefix}$table 
			SET $query
		");
	}

	/**
	 * Build a delete query.
	 *
	 * @param string The table name to perform the query on.
	 * @param string An optional where clause for the query.
	 * @param string An optional limit clause for the query.
	 * @return resource The query data.
	 */
	function delete_query($table, $where="", $limit="")
	{
		$query = "";
		if(!empty($where))
		{
			$query .= " WHERE $where";
		}
		
		return $this->write_query("
			DELETE 
			FROM {$this->table_prefix}$table 
			$query
		");
	}

	/**
	 * Escape a string according to the pg escape format.
	 *
	 * @param string The string to be escaped.
	 * @return string The escaped string.
	 */
	function escape_string($string)
	{
		if(function_exists("pg_escape_string"))
		{
			$string = pg_escape_string($string);
		}
		else
		{
			$string = addslashes($string);
		}
		return $string;
	}
	
	/**
	 * Frees the resources of a MySQLi query.
	 *
	 * @param object The query to destroy.
	 * @return boolean Returns true on success, false on faliure
	 */
	function free_result($query)
	{
		return pg_free_result($query);
	}
	
	/**
	 * Escape a string used within a like command.
	 *
	 * @param string The string to be escaped.
	 * @return string The escaped string.
	 */
	function escape_string_like($string)
	{
		return str_replace(array('%', '_') , array('\\%' , '\\_') , $string);
	}

	/**
	 * Gets the current version of PgSQL.
	 *
	 * @return string Version of PgSQL.
	 */
	function get_version()
	{
		if($this->version)
		{
			return $this->version;
		}
		
		if(version_compare(PHP_VERSION, '5.0.0', '>='))
		{
			$version = pg_version($this->current_link);
 
  			$this->version = $version['client'];
		}
		else
		{
			$query = $this->query("select version()");
			$row = $this->fetch_array($query);

			$this->version = $row['version'];
		}
		
		return $this->version;
	}

	/**
	 * Optimizes a specific table.
	 *
	 * @param string The name of the table to be optimized.
	 */
	function optimize_table($table)
	{
		$this->write_query("OPTIMIZE TABLE ".$this->table_prefix.$table."");
	}
	
	/**
	 * Analyzes a specific table.
	 *
	 * @param string The name of the table to be analyzed.
	 */
	function analyze_table($table)
	{
		$this->write_query("ANALYZE TABLE ".$this->table_prefix.$table."");
	}

	/**
	 * Show the "create table" command for a specific table.
	 *
	 * @param string The name of the table.
	 * @return string The pg command to create the specified table.
	 */
	function show_create_table($table)
	{		
		$query = $this->write_query("
			SELECT a.attnum, a.attname as field, t.typname as type, a.attlen as length, a.atttypmod as lengthvar, a.attnotnull as notnull
			FROM pg_class c
			LEFT JOIN pg_attribute a ON (a.attrelid = c.oid)
			LEFT JOIN pg_type t ON (a.atttypid = t.oid)
			WHERE c.relname = '{$this->table_prefix}{$table}' AND a.attnum > 0 
			ORDER BY a.attnum
		");

		$table .= "CREATE TABLE {$this->table_prefix}{$table} (\n";
		$lines = array();
		
		while($row = $this->fetch_array($query))
		{
			// Get the data from the table
			$query2 = $this->write_query("
				SELECT pg_get_expr(d.adbin, d.adrelid) as rowdefault
				FROM pg_attrdef d
				LEFT JOIN pg_class c ON (c.oid = d.adrelid)
				WHERE c.relname = '{$this->table_prefix}{$table}' AND d.adnum = '{$row['attnum']}'
			");

			if(!$query2)
			{
				unset($row['rowdefault']);
			}
			else
			{
				$row['rowdefault'] = $this->fetch_field($query2, 'rowdefault');
			}

			if($row['type'] == 'bpchar')
			{
				// Stored in the engine as bpchar, but in the CREATE TABLE statement it's char
				$row['type'] = 'char';
			}

			$line = "  {$row['field']} {$row['type']}";

			if(strpos($row['type'], 'char') !== false)
			{
				if($row['lengthvar'] > 0)
				{
					$line .= '('.($row['lengthvar'] - 4).')';
				}
			}

			if(strpos($row['type'], 'numeric') !== false)
			{
				$line .= '('.sprintf("%s,%s", (($row['lengthvar'] >> 16) & 0xffff), (($row['lengthvar'] - 4) & 0xffff)).')';
			}

			if(!empty($row['rowdefault']))
			{
				$line .= " DEFAULT {$row['rowdefault']}";
			}

			if($row['notnull'] == 't')
			{
				$line .= ' NOT NULL';
			}
			
			$lines[] = $line;
		}

		// Get the listing of primary keys.
		$query = $this->write_query("
			SELECT ic.relname as index_name, bc.relname as tab_name, ta.attname as column_name, i.indisunique as unique_key, i.indisprimary as primary_key
			FROM pg_class bc
			LEFT JOIN pg_class ic ON (ic.oid = i.indexrelid)
			LEFT JOIN pg_index i ON (bc.oid = i.indrelid)
			LEFT JOIN pg_attribute ta ON (ta.attrelid = bc.oid AND ta.attrelid = i.indrelid AND ta.attnum = i.indkey[ia.attnum-1])
			LEFT JOIN pg_attribute ia ON (ia.attrelid = i.indexrelid)
			WHERE bc.relname = '{$this->table_prefix}{$table}'
			ORDER BY index_name, tab_name, column_name
		");

		$primary_key = array();

		// We do this in two steps. It makes placing the comma easier
		while($row = $this->fetch_array($query))
		{
			if($row['primary_key'] == 't')
			{
				$primary_key[] = $row['column_name'];
				$primary_key_name = $row['index_name'];
			}
		}

		if(!empty($primary_key))
		{
			$lines[] = "  CONSTRAINT $primary_key_name PRIMARY KEY (".implode(', ', $primary_key).")";
		}

		$table .= implode(", \n", $lines);
		$table .= "\n);\n";
		
		return $table;
	}

	/**
	 * Show the "show fields from" command for a specific table.
	 *
	 * @param string The name of the table.
	 * @return string Field info for that table
	 */
	function show_fields_from($table)
	{
		$query = $this->write_query("SELECT column_name FROM information_schema.constraint_column_usage WHERE table_name = '{$this->table_prefix}{$table}' and constraint_name = '{$this->table_prefix}{$table}_pkey' LIMIT 1");
		$primary_key = $this->fetch_field($query, 'column_name');
		
		$query = $this->write_query("
			SELECT column_name as Field, data_type as Extra
			FROM information_schema.columns 
			WHERE table_name = '{$this->table_prefix}{$table}'
		");		
		while($field = $this->fetch_array($query))
		{
			if($field['field'] == $primary_key)
			{
				$field['extra'] = 'auto_increment';
			}
			
			$field_info[] = array('Extra' => $field['extra'], 'Field' => $field['field']);
		}
		
		return $field_info;
	}

	/**
	 * Returns whether or not the table contains a fulltext index.
	 *
	 * @param string The name of the table.
	 * @param string Optionally specify the name of the index.
	 * @return boolean True or false if the table has a fulltext index or not.
	 */
	function is_fulltext($table, $index="")
	{
		return false;
	}

	/**
	 * Returns whether or not this database engine supports fulltext indexing.
	 *
	 * @param string The table to be checked.
	 * @return boolean True or false if supported or not.
	 */

	function supports_fulltext($table)
	{
		return false;
	}

	/**
	 * Returns whether or not this database engine supports boolean fulltext matching.
	 *
	 * @param string The table to be checked.
	 * @return boolean True or false if supported or not.
	 */
	function supports_fulltext_boolean($table)
	{
		return false;
	}

	/**
	 * Creates a fulltext index on the specified column in the specified table with optional index name.
	 *
	 * @param string The name of the table.
	 * @param string Name of the column to be indexed.
	 * @param string The index name, optional.
	 */
	function create_fulltext_index($table, $column, $name="")
	{
		return false;
	}

	/**
	 * Drop an index with the specified name from the specified table
	 *
	 * @param string The name of the table.
	 * @param string The name of the index.
	 */
	function drop_index($table, $name)
	{
		$this->write_query("
			ALTER TABLE {$this->table_prefix}$table 
			DROP INDEX $name
		");
	}
	
	/**
	 * Drop an table with the specified table
	 *
	 * @param string The name of the table.
	 * @param boolean hard drop - no checking
	 * @param boolean use table prefix
	 */
	function drop_table($table, $hard=false, $table_prefix=true)
	{
		if($table_prefix == false)
		{
			$table_prefix = "";
		}
		else
		{
			$table_prefix = $this->table_prefix;
		}
		
		if($hard == false)
		{
			if($this->table_exists($table))
			{
				$this->write_query('DROP TABLE '.$table_prefix.$table);
			}
		}
		else
		{
			$this->write_query('DROP TABLE '.$table_prefix.$table);
		}
	}
	
	/**
	 * Replace contents of table with values
	 *
	 * @param string The table
	 * @param array The replacements
	 */
	function replace_query($table, $replacements=array(), $default_field="")
	{
		$i = 0;		
		
		if($default_field == "")
		{
			$query = $this->write_query("SELECT column_name FROM information_schema.constraint_column_usage WHERE table_name = '{$this->table_prefix}{$table}' and constraint_name = '{$this->table_prefix}{$table}_pkey' LIMIT 1");
			$main_field = $this->fetch_field($query, 'column_name');
		}
		else
		{
			$main_field = $default_field;
		}
		
		$query = $this->write_query("SELECT {$main_field} FROM {$this->table_prefix}{$table}");
		
		while($column = $this->fetch_array($query))
		{
			if($column[$main_field] == $replacements[$main_field])
			{				
				++$i;
			}
		}
		
		if($i > 0)
		{
			return $this->update_query($table, $replacements, "{$main_field}='".$replacements[$main_field]."'");
		}
		else
		{
			return $this->insert_query($table, $replacements);
		}
	}
	
	/**
	 * Replace contents of table with values
	 *
	 * @param string The table
	 * @param array The replacements
	 */
	function build_replace_query($table, $replacements=array(), $default_field="")
	{
		$i = 0;		
		
		if($default_field == "")
		{
			$query = $this->write_query("SELECT column_name FROM information_schema.constraint_column_usage WHERE table_name = '{$this->table_prefix}{$table}' and constraint_name = '{$this->table_prefix}{$table}_pkey' LIMIT 1");
			$main_field = $this->fetch_field($query, 'column_name');
		}
		else
		{
			$main_field = $default_field;
		}
		
		$query = $this->write_query("SELECT {$main_field} FROM {$this->table_prefix}{$table}");
		
		while($column = $this->fetch_array($query))
		{
			if($column[$main_field] == $replacements[$main_field])
			{				
				++$i;
			}
		}
		
		if($i > 0)
		{
			return $this->build_update_query($table, $replacements, "{$main_field}='".$replacements[$main_field]."'");
		}
		else
		{
			return $this->build_insert_query($table, $replacements);
		}
	}
	
	function build_fields_string($table, $append="")
	{
		$fields = $this->show_fields_from($table);
		$comma = '';
		
		foreach($fields as $key => $field)
		{
			$fieldstring .= $comma.$append.$field['Field'];
			$comma = ',';
		}
		
		return $fieldstring;
	}
	
	/**
	 * Sets the table prefix used by the simple select, insert, update and delete functions
	 *
	 * @param string The new table prefix
	 */
	function set_table_prefix($prefix)
	{
		$this->table_prefix = $prefix;
	}
	
	/**
	 * Fetched the total size of all mysql tables or a specific table
	 *
	 * @param string The table (optional)
	 * @return integer the total size of all mysql tables or a specific table
	 */
	function fetch_size($table='')
	{
		if($table != '')
		{
			$query = $this->query("SELECT reltuples, relpages FROM pg_class WHERE relname = '".$this->table_prefix.$table."'");
		}
		else
		{
			$query = $this->query("SELECT reltuples, relpages FROM pg_class");
		}
		$total = 0;
		while($table = $this->fetch_array($query))
		{
			$total += $table['relpages']+$table['reltuples'];
		}
		return $total;
	}

	/**
	 * Fetch a list of database character sets this DBMS supports
	 *
	 * @return array Array of supported character sets with array key being the name, array value being display name. False if unsupported
	 */
	function fetch_db_charsets()
	{
		return false;
	}

	/**
	 * Fetch a database collation for a particular database character set
	 *
	 * @param string The database character set
	 * @return string The matching database collation, false if unsupported
	 */
	function fetch_charset_collation($charset)
	{
		return false;
	}

	/**
	 * Fetch a character set/collation string for use with CREATE TABLE statements. Uses current DB encoding
	 *
	 * @return string The built string, empty if unsupported
	 */
	function build_create_table_collation()
	{
		return '';
	}
}

if(!class_exists('databaseEngine'))
{
	class databaseEngine extends DB_PgSQL {
	}
}
?>
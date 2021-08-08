<?php

/**
 * This file is part of the CodeIgniter 4 framework.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Database\PDO;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use LogicException;
use PDO;
use stdClass;
use Throwable;

/**
 * Connection for MySQLi
 */
class Connection extends BaseConnection
{
	/**
	 * Database driver
	 *
	 * @var string
	 */
	public $DBDriver = 'PDO';

	/**
	 * DELETE hack flag
	 *
	 * Whether to use the MySQL "delete hack" which allows the number
	 * of affected rows to be shown. Uses a preg_replace when enabled,
	 * adding a bit more processing to all queries.
	 *
	 * @var boolean
	 */
	public $deleteHack = true;

	// --------------------------------------------------------------------

	/**
	 * Identifier escape character
	 *
	 * @var string
	 */
	public $escapeChar = '`';

	// --------------------------------------------------------------------

	/**
	 * MySQLi object
	 *
	 * Has to be preserved without being assigned to $conn_id.
	 *
	 * @var MySQLi
	 */
	public $mysqli;

	//--------------------------------------------------------------------

	/**
	 * Connect to the database.
	 *
	 * @param boolean $persistent
	 *
	 * @return mixed
	 * @throws DatabaseException
	 */
	public function connect(bool $persistent = false)
	{
		// Do we have a socket path?
		if ($this->hostname[0] === '/')
		{
			$hostname = null;
			$port     = null;
			$socket   = $this->hostname;
		}
		else
		{
			$hostname = ($persistent === true) ? 'p:' . $this->hostname : $this->hostname;
			$port     = empty($this->port) ? null : $this->port;
			$socket   = '';
		}	

		try
		{
			if($this->DSN){
				$this->mysqli = new PDO($this->DSN, $this->username, $this->password); 
			}else{
				$this->mysqli = new PDO("mysql:host=" . $hostname . ";port=". $port .";dbname=" . $this->database .";charset=". $this->charset, $this->username, $this->password); 
			}
			$this->mysqli->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->mysqli->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
			$this->mysqli->setAttribute(PDO::ATTR_TIMEOUT, 10);
			$this->mysqli->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND,"SET NAMES '". $this->charset ."'");
			return $this->mysqli;
		}
		catch (Throwable $e)
		{
			// Clean sensitive information from errors.
			$msg = $e->getMessage();

			$msg = str_replace($this->username, '****', $msg);
			$msg = str_replace($this->password, '****', $msg);

			throw new DatabaseException($msg, $e->getCode(), $e);
		}

		return false;
	}

	//--------------------------------------------------------------------

	/**
	 * Keep or establish the connection if no queries have been sent for
	 * a length of time exceeding the server's idle timeout.
	 *
	 * @return void
	 */
	public function reconnect()
	{
		$this->close();
		$this->initialize();
	}

	//--------------------------------------------------------------------

	/**
	 * Close the database connection.
	 *
	 * @return void
	 */
	protected function _close()
	{
		$this->connID = null;
	}

	//--------------------------------------------------------------------

	/**
	 * Select a specific database table to use.
	 *
	 * @param string $databaseName
	 *
	 * @return boolean
	 */
	public function setDatabase(string $databaseName): bool
	{
		if ($databaseName === '')
		{
			$databaseName = $this->database;
		}

		if (empty($this->connID))
		{
			$this->initialize();
		}

		if ($this->connID->query("use ". $databaseName))
		{
			$this->database = $databaseName;

			return true;
		}

		return false;
	}

	//--------------------------------------------------------------------

	/**
	 * Returns a string containing the version of the database being used.
	 *
	 * @return string
	 */
	public function getVersion(): string
	{
		if (isset($this->dataCache['version']))
		{
			return $this->dataCache['version'];
		}

		if (empty($this->mysqli))
		{
			$this->initialize();
		}

		return $this->dataCache['version'] = $this->mysqli->query('select version()')->fetchColumn();
	}

	//--------------------------------------------------------------------

	/**
	 * Executes the query against the database.
	 *
	 * @param string $sql
	 *
	 * @return mixed
	 */
	public function execute(string $sql)
	{
		try
		{
			return $this->connID->query($this->prepQuery($sql));
		}
		catch (PDOException $e)
		{
			log_message('error', $e->getMessage());

			if ($this->DBDebug)
			{
				throw $e;
			}
		}
		return false;
	}

	//--------------------------------------------------------------------

	/**
	 * Prep the query
	 *
	 * If needed, each database adapter can prep the query string
	 *
	 * @param string $sql an SQL query
	 *
	 * @return string
	 */
	protected function prepQuery(string $sql): string
	{
		// mysqli_affected_rows() returns 0 for "DELETE FROM TABLE" queries. This hack
		// modifies the query so that it a proper number of affected rows is returned.
		if ($this->deleteHack === true && preg_match('/^\s*DELETE\s+FROM\s+(\S+)\s*$/i', $sql))
		{
			return trim($sql) . ' WHERE 1=1';
		}

		return $sql;
	}

	//--------------------------------------------------------------------

	/**
	 * Returns the total number of rows affected by this query.
	 *
	 * @return integer
	 */
	public function affectedRows(): int
	{
		return $this->connID->rowCount() ?? 0;
	}

	//--------------------------------------------------------------------

	/**
	 * Platform-dependant string escape
	 *
	 * @param  string $str
	 * @return string
	 */
	protected function _escapeString(string $str): string
	{
		if (! $this->connID)
		{
			$this->initialize();
		}

		return $this->connID->quote($str);
	}

	//--------------------------------------------------------------------

	/**
	 * Escape Like String Direct
	 * There are a few instances where MySQLi queries cannot take the
	 * additional "ESCAPE x" parameter for specifying the escape character
	 * in "LIKE" strings, and this handles those directly with a backslash.
	 *
	 * @param  string|string[] $str Input string
	 * @return string|string[]
	 */
	public function escapeLikeStringDirect($str)
	{
		if (is_array($str))
		{
			foreach ($str as $key => $val)
			{
				$str[$key] = $this->escapeLikeStringDirect($val);
			}

			return $str;
		}

		$str = $this->_escapeString($str);

		// Escape LIKE condition wildcards
		return str_replace([
			$this->likeEscapeChar,
			'%',
			'_',
		], [
			'\\' . $this->likeEscapeChar,
			'\\' . '%',
			'\\' . '_',
		], $str
		);
	}

	//--------------------------------------------------------------------

	/**
	 * Generates the SQL for listing tables in a platform-dependent manner.
	 * Uses escapeLikeStringDirect().
	 *
	 * @param boolean $prefixLimit
	 *
	 * @return string
	 */
	protected function _listTables(bool $prefixLimit = false): string
	{
		$sql = 'SHOW TABLES FROM ' . $this->escapeIdentifiers($this->database);

		if ($prefixLimit !== false && $this->DBPrefix !== '')
		{
			return $sql . " LIKE '" . $this->escapeLikeStringDirect($this->DBPrefix) . "%'";
		}

		return $sql;
	}

	//--------------------------------------------------------------------

	/**
	 * Generates a platform-specific query string so that the column names can be fetched.
	 *
	 * @param string $table
	 *
	 * @return string
	 */
	protected function _listColumns(string $table = ''): string
	{
		return 'SHOW COLUMNS FROM ' . $this->protectIdentifiers($table, true, null, false);
	}

	//--------------------------------------------------------------------

	/**
	 * Returns an array of objects with field data
	 *
	 * @param  string $table
	 * @return stdClass[]
	 * @throws DatabaseException
	 */
	public function _fieldData(string $table): array
	{
		$table = $this->protectIdentifiers($table, true, null, false);

		if (($query = $this->query('SHOW COLUMNS FROM ' . $table)) === false)
		{
			throw new DatabaseException(lang('Database.failGetFieldData'));
		}
		$query = $query->getResultObject();

		$retVal = [];
		for ($i = 0, $c = count($query); $i < $c; $i++)
		{
			$retVal[$i]       = new stdClass();
			$retVal[$i]->name = $query[$i]->Field;

			sscanf($query[$i]->Type, '%[a-z](%d)', $retVal[$i]->type, $retVal[$i]->max_length);

			$retVal[$i]->nullable    = $query[$i]->Null === 'YES';
			$retVal[$i]->default     = $query[$i]->Default;
			$retVal[$i]->primary_key = (int) ($query[$i]->Key === 'PRI');
		}

		return $retVal;
	}

	//--------------------------------------------------------------------

	/**
	 * Returns an array of objects with index data
	 *
	 * @param  string $table
	 * @return stdClass[]
	 * @throws DatabaseException
	 * @throws LogicException
	 */
	public function _indexData(string $table): array
	{
		$table = $this->protectIdentifiers($table, true, null, false);

		if (($query = $this->query('SHOW INDEX FROM ' . $table)) === false)
		{
			throw new DatabaseException(lang('Database.failGetIndexData'));
		}

		if (! $indexes = $query->getResultArray())
		{
			return [];
		}

		$keys = [];

		foreach ($indexes as $index)
		{
			if (empty($keys[$index['Key_name']]))
			{
				$keys[$index['Key_name']]       = new stdClass();
				$keys[$index['Key_name']]->name = $index['Key_name'];

				if ($index['Key_name'] === 'PRIMARY')
				{
					$type = 'PRIMARY';
				}
				elseif ($index['Index_type'] === 'FULLTEXT')
				{
					$type = 'FULLTEXT';
				}
				elseif ($index['Non_unique'])
				{
					$type = $index['Index_type'] === 'SPATIAL' ? 'SPATIAL' : 'INDEX';
				}
				else
				{
					$type = 'UNIQUE';
				}

				$keys[$index['Key_name']]->type = $type;
			}

			$keys[$index['Key_name']]->fields[] = $index['Column_name'];
		}

		return $keys;
	}

	//--------------------------------------------------------------------

	/**
	 * Returns an array of objects with Foreign key data
	 *
	 * @param  string $table
	 * @return stdClass[]
	 * @throws DatabaseException
	 */
	public function _foreignKeyData(string $table): array
	{
		$sql = '
                    SELECT
                        tc.CONSTRAINT_NAME,
                        tc.TABLE_NAME,
                        kcu.COLUMN_NAME,
                        rc.REFERENCED_TABLE_NAME,
                        kcu.REFERENCED_COLUMN_NAME
                    FROM information_schema.TABLE_CONSTRAINTS AS tc
                    INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS AS rc
                        ON tc.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                    INNER JOIN information_schema.KEY_COLUMN_USAGE AS kcu
                        ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                    WHERE
                        tc.CONSTRAINT_TYPE = ' . $this->escape('FOREIGN KEY') . ' AND
                        tc.TABLE_SCHEMA = ' . $this->escape($this->database) . ' AND
                        tc.TABLE_NAME = ' . $this->escape($table);

		if (($query = $this->query($sql)) === false)
		{
			throw new DatabaseException(lang('Database.failGetForeignKeyData'));
		}
		$query = $query->getResultObject();

		$retVal = [];
		foreach ($query as $row)
		{
			$obj                      = new stdClass();
			$obj->constraint_name     = $row->CONSTRAINT_NAME;
			$obj->table_name          = $row->TABLE_NAME;
			$obj->column_name         = $row->COLUMN_NAME;
			$obj->foreign_table_name  = $row->REFERENCED_TABLE_NAME;
			$obj->foreign_column_name = $row->REFERENCED_COLUMN_NAME;

			$retVal[] = $obj;
		}

		return $retVal;
	}

	//--------------------------------------------------------------------

	/**
	 * Returns platform-specific SQL to disable foreign key checks.
	 *
	 * @return string
	 */
	protected function _disableForeignKeyChecks()
	{
		return 'SET FOREIGN_KEY_CHECKS=0';
	}

	//--------------------------------------------------------------------

	/**
	 * Returns platform-specific SQL to enable foreign key checks.
	 *
	 * @return string
	 */
	protected function _enableForeignKeyChecks()
	{
		return 'SET FOREIGN_KEY_CHECKS=1';
	}

	//--------------------------------------------------------------------

	/**
	 * Returns the last error code and message.
	 * Must return this format: ['code' => string|int, 'message' => string]
	 * intval(code) === 0 means "no error".
	 *
	 * @return array<string,string|int>
	 */
	public function error(): array
	{
		if (! empty($this->mysqli->errorCode()))
		{
			return [
				'code'    => $this->mysqli->errorCode(),
				'message' => $this->mysqli->errorInfo(),
			];
		}

		return [
			'code'    => $this->connID->errno,
			'message' => $this->connID->error,
		];
	}

	//--------------------------------------------------------------------

	/**
	 * Insert ID
	 *
	 * @return integer
	 */
	public function insertID(): int
	{
		return $this->connID->lastInsertId();
	}

	//--------------------------------------------------------------------

	/**
	 * Begin Transaction
	 *
	 * @return boolean
	 */
	protected function _transBegin(): bool
	{
		$this->connID->autocommit(false);

		return $this->connID->beginTransaction();
	}

	//--------------------------------------------------------------------

	/**
	 * Commit Transaction
	 *
	 * @return boolean
	 */
	protected function _transCommit(): bool
	{
		if ($this->connID->commit())
		{
			$this->connID->autocommit(true);

			return true;
		}

		return false;
	}

	//--------------------------------------------------------------------

	/**
	 * Rollback Transaction
	 *
	 * @return boolean
	 */
	protected function _transRollback(): bool
	{
		if ($this->connID->rollback())
		{
			$this->connID->autocommit(true);

			return true;
		}

		return false;
	}

	//--------------------------------------------------------------------
}

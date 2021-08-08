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

use BadMethodCallException;
use App\Database\PDO\BasePreparedQuery;

/**
 * Prepared query for MySQLi
 */
class PreparedQuery extends BasePreparedQuery
{
	/**
	 * Prepares the query against the database, and saves the connection
	 * info necessary to execute the query later.
	 *
	 * NOTE: This version is based on SQL code. Child classes should
	 * override this method.
	 *
	 * @param string $sql
	 * @param array  $options Passed to the connection's prepare statement.
	 *                        Unused in the MySQLi driver.
	 *
	 * @return mixed
	 */
	public function _prepare(string $sql, array $options = [])
	{
		// Mysqli driver doesn't like statements
		// with terminating semicolons.
		$sql = rtrim($sql, ';');
		try{
			$this->statement = $this->db->mysqli->prepare($sql);
		}catch(PDOException $e){
			throw $e;
		}
		return $this;
	}

	/**
	 * Takes a new set of data and runs it against the currently
	 * prepared query. Upon success, will return a Results object.
	 *
	 * @param array $data
	 *
	 * @return boolean
	 */
	public function _execute(array $data): bool
	{
		if (! isset($this->statement))
		{
			throw new BadMethodCallException('You must call prepare before trying to execute a prepared statement.');
		}
		return $this->statement->execute($data[0]);
	}

	/**
	 * Returns the result object for the prepared query.
	 *
	 * @return mixed
	 */
	public function _getResult()
	{
		return $this->statement;
	}
}

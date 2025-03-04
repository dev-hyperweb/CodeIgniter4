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

use CodeIgniter\Database\BaseResult;
use CodeIgniter\Entity\Entity;
use stdClass;
use PDO;

/**
 * Result for MySQLi
 */
class Result extends BaseResult
{
	/**
	 * Gets the number of fields in the result set.
	 *
	 * @return integer
	 */
	public function getFieldCount(): int
	{
		return $this->resultID->columnCount();
	}

	//--------------------------------------------------------------------

	/**
	 * Generates an array of column names in the result set.
	 *
	 * @return array
	 */
	public function getFieldNames(): array
	{
		$fieldNames = [];
		while ($field = $this->resultID->fetchAll(PDO::FETCH_COLUMN))
		{
			$fieldNames[] = $field['name'];
		}

		return $fieldNames;
	}

	//--------------------------------------------------------------------

	/**
	 * Generates an array of objects representing field meta-data.
	 *
	 * @return array
	 */
	public function getFieldData(): array
	{
		static $dataTypes = [
			MYSQLI_TYPE_DECIMAL     => 'decimal',
			MYSQLI_TYPE_NEWDECIMAL  => 'newdecimal',
			MYSQLI_TYPE_FLOAT       => 'float',
			MYSQLI_TYPE_DOUBLE      => 'double',

			MYSQLI_TYPE_BIT         => 'bit',
			MYSQLI_TYPE_SHORT       => 'short',
			MYSQLI_TYPE_LONG        => 'long',
			MYSQLI_TYPE_LONGLONG    => 'longlong',
			MYSQLI_TYPE_INT24       => 'int24',

			MYSQLI_TYPE_YEAR        => 'year',

			MYSQLI_TYPE_TIMESTAMP   => 'timestamp',
			MYSQLI_TYPE_DATE        => 'date',
			MYSQLI_TYPE_TIME        => 'time',
			MYSQLI_TYPE_DATETIME    => 'datetime',
			MYSQLI_TYPE_NEWDATE     => 'newdate',

			MYSQLI_TYPE_SET         => 'set',

			MYSQLI_TYPE_VAR_STRING  => 'var_string',
			MYSQLI_TYPE_STRING      => 'string',

			MYSQLI_TYPE_GEOMETRY    => 'geometry',
			MYSQLI_TYPE_TINY_BLOB   => 'tiny_blob',
			MYSQLI_TYPE_MEDIUM_BLOB => 'medium_blob',
			MYSQLI_TYPE_LONG_BLOB   => 'long_blob',
			MYSQLI_TYPE_BLOB        => 'blob',
		];

		$retVal    = [];
		$fieldData = $this->resultID->fetchAll(PDO::FETCH_COLUMN);

		foreach ($fieldData as $i => $data)
		{
			$retVal[$i]              = new stdClass();
			$retVal[$i]->name        = $data->name;
			$retVal[$i]->type        = $data->type;
			$retVal[$i]->type_name   = in_array($data->type, [1, 247], true) ? 'char' : ($dataTypes[$data->type] ?? null);
			$retVal[$i]->max_length  = $data->max_length;
			$retVal[$i]->primary_key = (int) ($data->flags & 2);
			$retVal[$i]->length      = $data->length;
			$retVal[$i]->default     = $data->def;
		}

		return $retVal;
	}

	//--------------------------------------------------------------------

	/**
	 * Frees the current result.
	 *
	 * @return void
	 */
	public function freeResult()
	{
		if (is_object($this->resultID))
		{
			$this->resultID->closeCursor();
			$this->resultID = false;
		}
	}

	//--------------------------------------------------------------------

	/**
	 * Moves the internal pointer to the desired offset. This is called
	 * internally before fetching results to make sure the result set
	 * starts at zero.
	 *
	 * @param integer $n
	 *
	 * @return mixed
	 */
	public function dataSeek(int $n = 0)
	{
		
	}

	//--------------------------------------------------------------------

	/**
	 * Returns the result set as an array.
	 *
	 * Overridden by driver classes.
	 *
	 * @return mixed
	 */
	protected function fetchAssoc()
	{
		return $this->resultID->fetch(PDO::FETCH_ASSOC);
	}

	//--------------------------------------------------------------------

	/**
	 * Returns the result set as an object.
	 *
	 * Overridden by child classes.
	 *
	 * @param string $className
	 *
	 * @return object|boolean|Entity
	 */
	protected function fetchObject(string $className = 'stdClass')
	{
		if (is_subclass_of($className, Entity::class))
		{
			return empty($data = $this->fetchAssoc()) ? false : (new $className())->setAttributes($data);
		}
		return $this->resultID->fetch(PDO::FETCH_OBJ);
	}

	//--------------------------------------------------------------------

	/**
	 * Returns the number of rows in the resultID (i.e., mysqli_result object)
	 *
	 * @return integer number of rows in a query result
	 */
	public function getNumRows(): int
	{
		if (! is_int($this->numRows))
		{
			$this->numRows = $this->resultID->rowCount();
		}

		return $this->numRows;
	}

	//--------------------------------------------------------------------
}

<?php
/**
 * Part of the Joomla GSoC Webservices Project
 *
 * @copyright  Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Entity;

use Joomla\Database\QueryInterface;
use Joomla\Database\DatabaseDriver;

/**
 * Class Query
 * @since 1.0
 */
class Query
{
	/**
	 * The Joomla QueryInterface.
	 *
	 * @var QueryInterface
	 */
	protected $query;

	/**
	 * The connection name for the model.
	 *
	 * @var DatabaseDriver
	 */
	protected $db;

	/**
	 * The connection name for the model.
	 *
	 * @var Model
	 */
	protected $model;

	/**
	 * The methods that should be returned from Joomla Database query builder.
	 *
	 * @var array
	 */
	protected $passthru = array(
		'select', 'where', 'from'
	);

	/**
	 * Create a new Query instance.
	 *
	 * @param   QueryInterface $query Joomla Database QueryInterface instantiated in the model
	 * @param   DatabaseDriver $db    Joomla DatabaseDriver
	 * @param   Model          $model Model passed by reference
	 */
	public function __construct($query, $db, &$model)
	{
		$this->query = $query;
		$this->db    = $db;
		$this->model = $model;

	}

	/**
	 * Inserts a single instance of a model.
	 *
	 *
	 * @return boolean
	 */
	public function insert()
	{
		$fields = array();
		$values = array();

		// Iterate over the object variables to build the query fields and values.
		foreach ($this->model->getAttributes() as $k => $v)
		{
			// Prepare and sanitize the fields and values for the database query.
			$fields[] = $this->db->quoteName($k);
			$values[] = $this->db->quote($v);
		}

		// Create the base insert statement.
		$this->query->insert($this->db->quoteName($this->model->getTable()))
			->columns($fields)
			->values(implode(',', $values));

		// Set the query and execute the insert.
		$success = $this->db->setQuery($this->query)->execute();

		if ($this->model->isIncrementing())
		{
			$id = $this->db->insertid();
			$key = $this->model->getPrimaryKey();

			// Update the primary key if it exists.
			if ($key && $id && is_string($key))
			{
				$this->model->setPrimaryKey($id);
			}
		}

		return $success;
	}

	/**
	 * Updates a single instance of a model.
	 *
	 * @return boolean
	 */
	public function update()
	{
		$fields = array();

		$statement = 'UPDATE ' . $this->db->quoteName($this->model->getTable()) . ' SET %s WHERE %s';

		$where = $this->getWherePrimaryKey();

		// Iterate over the object variables to build the query fields and values.
		foreach ($this->model->getDirty() as $k => $v)
		{
			if ($v == null)
			{
				$v = 'NULL';
			}

			// Prepare and sanitize the fields and values for the database query.
			$fields[] = $this->db->quoteName($k) . '=' . $this->db->quote($v);
		}

		if (empty($fields))
		{
			return true;
		}

		// Set the query and execute the insert.
		$success = $this->db->setQuery(sprintf($statement, implode(',', $fields), $where))->execute();

		return $success;
	}

	/**
	 * Deletes a single instance of a model.
	 *
	 * @return boolean
	 */
	public function delete()
	{
		$this->query->delete($this->model->getTable())->where($this->getWherePrimaryKey($this->model));

		// Set the query and execute the insert.
		$success = $this->db->setQuery($this->query)->execute();

		return $success;
	}


	/**
	 * Constructs the where clause based on the primary key
	 *
	 * @return string
	 */
	protected function getWherePrimaryKey()
	{
		$key = $this->model->getPrimaryKey();

		return $this->db->quoteName($key) . '=' . $this->db->quote($this->model->$key);

	}

	/**
	 * Find a model by its primary key.
	 *
	 * @param   mixed  $id      primary key
	 * @param   array  $columns columns to be selected in query
	 * @return Model
	 */
	public function find($id, $columns = array('*'))
	{
		// TODO, what if the key does not exits, error handling
		$this->from($this->model->getTable())->select($columns)->whereKey($id);
		$item = $this->db->setQuery($this->query)->loadAssoc();

		// TODO something like first() from Collection would make this nicer
		return $this->hydrate(array($item))[0];
	}

	/**
	 * Add a where clause on the primary key to the query.
	 *
	 * @param   mixed $id primary key
	 * @return $this
	 */
	public function whereKey($id)
	{
		return $this->where($this->model->getPrimaryKey() . ' = ' . $id);
	}

	/**
	 * Create a collection of models from plain arrays.
	 *
	 * @param   array $items array of results from the database query
	 * @return array
	 */
	public function hydrate(array $items)
	{
		$instance = $this->model->newInstance();

		return array_map(
			function ($item) use ($instance) {
				return $instance->newFromBuilder($item);
			}, $items
		);
	}

	/**
	 * Dynamically handle calls into the query instance.
	 *
	 * @param   string  $method     method called dinamically
	 * @param   array   $parameters parameters to be passed to the dynamic called method
	 * @return mixed
	 */
	public function __call($method, $parameters)
	{
		if (in_array($method, $this->passthru))
		{
			$this->query->{$method}(...$parameters);
		}
		else
		{
			throw new BadMethodCallException(
				sprintf(
					'Method %s does not exist in QueryInterface.',  $method
				)
			);
		}

		return $this;
	}

}

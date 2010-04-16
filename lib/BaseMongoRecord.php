<?php

require_once('MongoRecord.php');
require_once('Inflector.php');

abstract class BaseMongoRecord
	implements MongoRecord
{
	protected $attributes;
	protected $errors;
	private $new;

	public static $database = null;
	public static $connection = null;

	public function __construct($attributes = array(), $new = true)
	{
		$this->new = $new;
		$this->attributes = $attributes;
		$this->errors = array();

		if ($new)
			$this->afterNew();
	}

	public function validate()
	{
		$this->beforeValidation();
		$retval = $this->isValid();
		$this->afterValidation();
		return $retval;
	}

	public function save()
	{
		if (!$this->validate())
			return false;

		$this->beforeSave();

		$collection = self::getCollection();
		$collection->save($this->attributes);

		$this->new = false;
		$this->afterSave();

		return true;
	}

	public function destroy()
	{
		$this->beforeDestroy();

		if (!$this->new)
		{
			$collection = self::getCollection();
			$collection->remove(array('_id' => $this->attributes['_id']));
		}
	}

	public static function find($query = array())
	{
		$collection = self::getCollection();
		$documents = $collection->find($query);

		$ret = array();
		while ($documents->hasNext())
		{
			$document = $documents->getNext();
			$ret[] = self::instantiate($document);
		}
		
		return $ret;
	}

	public static function findOne($query = array())
	{
		$collection = self::getCollection();
		$document = $collection->findOne($query);
		return self::instantiate($document);
	}

	private static function instantiate($document)
	{
		if ($document)
		{
			$className = get_called_class();
			return new $className($document, false);
		}
		else
		{
			return null;
		}
	}

	public function getID()
	{
		return $this->attributes['_id'];
	}
		
	public function __call($method, $arguments)
	{
		// Is this a get or a set
		$prefix = strtolower(substr($method, 0, 3));

		if ($prefix != 'get' && $prefix != 'set')
			return;

		// What is the get/set class attribute
		$inflector = Inflector::getInstance();
		$property = $inflector->underscore(substr($method, 3));

		if (empty($prefix) || empty($property))
		{
			// Did not match a get/set call
			throw New Exception("Calling a non get/set method that does not exist: $method");
		}

		// Get
		if ($prefix == "get" && isset($this->attributes[$property]))
		{
			return $this->attributes[$property];
		}
		else if ($prefix == "get")
		{
			return null;
		}

		// Set
		if ($prefix == "set" && isset($arguments[0]))
		{
			$this->attributes[$property] = $arguments[0];
			return $this;
		}
		else
		{
			throw new Exception("Calling a get/set method that does not exist: $property");
		}
	}


	// framework overrides/callbacks:
	public function beforeSave() {}
	public function afterSave() {}
	public function beforeValidation() {}
	public function afterValidation() {}
	public function beforeDestroy() {}
	public function afterNew() {}


	protected function isValid() 
	{
		$className = get_called_class();
		$methods = get_class_methods($className);
	
		foreach ($methods as $method)
		{
			if (substr($method, 0, 9))
			{
				$propertyCall = 'get' . substr($method, 9);
				if (!$className::$method($this->$propertyCall()))
				{
					return false;
				}
			}
		}

		return true; 
	}

	// core conventions
	protected static function getCollection()
	{
		$className = get_called_class();
		$inflector = Inflector::getInstance();
		$collection_name = $inflector->tableize($className);

		if (self::$database == null)
			throw new Exception("BaseMongoRecord::database must be initialized to a proper database string");

		if (self::$connection == null)
			throw new Exception("BaseMongoRecord::connection must be initialized to a valid Mongo object");
		
		if (!self::$connection->connected)
			self::$connection->connect();

		return self::$connection->selectCollection(self::$database, $collection_name);
	}
}


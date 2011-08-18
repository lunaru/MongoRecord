<?php

class MongoRecordIterator 
	implements Iterator, Countable
{
	protected $cursor;
	protected $className;
  
	public function __construct(MongoCursor $cursor, $className)
	{
		$this->cursor = $cursor;
		$this->className = $className;
	}
  
	public function current()
	{
		return $this->instantiate($this->cursor->current());
	}

	public function count()
	{
		return $this->cursor->count();
	}
  
	public function key()
	{
		return $this->cursor->key();
	}
  
	public function next()
	{
		$this->cursor->next();
	}
  
	public function rewind()
	{
		$this->cursor->rewind();
	}
  
	public function valid()
	{
		return $this->cursor->valid();
	}

	private function instantiate($document)
	{
		if ($document)
		{
			$className = $this->className;
			return new $className($document, false);
		}
		else
		{
			return null;
		}
	}
}


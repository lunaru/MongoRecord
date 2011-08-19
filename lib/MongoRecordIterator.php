<?php

class MongoRecordIterator 
	implements Iterator, Countable
{
	protected $current; // a PHP5.3 pointer hack to make current() work
	protected $cursor;
	protected $className;
  
	public function __construct($cursor, $className)
	{
		$this->cursor = $cursor;
		$this->className = $className;
		$this->cursor->rewind();
		$this->current = $this->current();
	}
  
	public function current()
	{
		$this->current = $this->instantiate($this->cursor->current());
		return $this->current;
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


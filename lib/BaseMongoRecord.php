<?php

require_once('MongoRecord.php');
require_once('MongoRecordIterator.php');
require_once('Inflector.php');

abstract class BaseMongoRecord
implements MongoRecord
{
  protected $attributes;
  protected $errors;
  private $new;

  public static $database = null;
  public static $connection = null;
  public static $findTimeout = 20000;

  /**
   * Collection name will be generated automaticaly if setted to null.
   * If overridden in child class, then new collection name uses. 
   * 
   * @var string
   */
  protected static $collectionName = null;
  protected $has_many=null;
  protected $belong_to=null;
  protected $has_one=null;
  public function __construct($attributes = array(), $new = true)
  {
    $this->new = $new;
    $this->attributes = $attributes;
    $this->errors = array();

    if ($new)
      $this->afterNew();

    if(isset($this->has_many)&&gettype($this->has_many)=="array")
      {
        call_user_func_array(array($this,"has_many"),$this->has_many);
      } 
    if(isset($this->has_one)&&gettype($this->has_one)=="array")
      {
        call_user_func_array(array($this,"has_one"),$this->has_one);
      } 
    if(isset($this->belong_to)&&gettype($this->belong_to)=="array")
      {
        call_user_func_array(array($this,"belong_to"),$this->belong_to);
      } 

  }

  public function validate()
  {
    $this->beforeValidation();
    $retval = $this->isValid();
    $this->afterValidation();
    return $retval;
  }

  public function save(array $options = array())
  {
    if (!$this->validate())
      return false;

    $this->beforeSave();

    $collection = self::getCollection();
    $collection->save($this->attributes, $options);

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
  private static function _find($query = array(), $options = array()){
            
    $collection = self::getCollection();
    if (isset($options['fields'])){
      $documents = $collection->find($query, $options['fields']);
    }
    else{
      $documents = $collection->find($query);
    }
                

    $className = get_called_class();

    if (isset($options['sort']))
      $documents->sort($options['sort']);

    if (isset($options['offset']))
      $documents->skip($options['offset']);

    if (isset($options['limit']))
      $documents->limit($options['limit']);

	
    $documents->timeout($className::$findTimeout);
    return $documents;
  }
  public static function findAll($query = array(), $options = array())
  {
    $documents = static::_find($query, $options);
    $ret = array();
    while ($documents->hasNext())
      {
        $document = $documents->getNext();
        $ret[] = self::instantiate($document);
      }

    return $ret;
  }

  public static function find($query = array(), $options = array())
  {
    $documents = static::_find($query, $options);
    var_dump($documents);
    $className = get_called_class();
    return new MongoRecordIterator($documents, $className);
  }

  public static function findOne($query = array(), $options = array())
  {
    $options['limit'] = 1;

    $results = self::find($query, $options);

    if ($results)
      return $results->current();
    else
      return null;
  }

  public static function count($query = array())
  {
    $collection = self::getCollection();
    $documents = $collection->count($query);

    return $documents;
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

  public function setID($id)
  {
    $this->attributes['_id'] = $id;
  }

  public function __call($method, $arguments)
  {
    //if the attribute has been set as function
      if(isset($this->$method)&&get_class($this->$method)=="Closure")
      {
         $func=$this->$method;    
         if(array_key_exists(0, $arguments))
           return $func($arguments[0]);
         else
           return $func();
      }

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
    if ($prefix == "get" && array_key_exists($property, $this->attributes))
      {
        return $this->attributes[$property];
      }
    else if ($prefix == "get")
      {
        return null;
      }

    // Set
    if ($prefix == "set" && array_key_exists(0, $arguments))
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
 
  public function beforeSave() {
    if(isset($this->beforeSave)&&get_class($this->beforeSave)=="Closure")    
      { $cb=$this->beforeSave;
        $cb(); 
      }
    if(isset($this->beforeSave_once)&&get_class($this->beforeSave_once)=="Closure")    
      { $cb=$this->beforeSave_once;
        $cb(); 
        $this->beforeSave_once=null;  
      }

  }

  public function afterSave() {
    if(isset($this->afterSave)&&get_class($this->afterSave)=="Closure")    
      { $cb=$this->afterSave;
        $cb(); 
      }
    if(isset($this->afterSave_once)&&get_class($this->afterSave_once)=="Closure")    
      { $cb=$this->afterSave_once;
        $cb(); 
        $this->afterSave_once=null;  
      }

  }
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
        if (substr($method, 0, 9) == 'validates')
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

    if (null !== static::$collectionName)
      {
        $collectionName = static::$collectionName;
      }
    else
      {
        $inflector = Inflector::getInstance();
        $collectionName = $inflector->tableize($className);
      }

    if ($className::$database == null)
      throw new Exception("BaseMongoRecord::database must be initialized to a proper database string");

    if ($className::$connection == null)
      throw new Exception("BaseMongoRecord::connection must be initialized to a valid Mongo object");

    if (!($className::$connection->connected))
      $className::$connection->connect();

    return $className::$connection->selectCollection($className::$database, $collectionName);
  }

  public static function setFindTimeout($timeout)
  {
    $className = get_called_class();
    $className::$findTimeout = $timeout;
  }

  public static function ensureIndex(array $keys, array $options = array())
  {
    return self::getCollection()->ensureIndex($keys, $options);
  }

  public static function deleteIndex($keys)
  {
    return self::getCollection()->deleteIndex($keys);
  }

  public function getAttributes()
  {
    return $this->attributes;
  }
  /* Below part are adding the functions to support relationship,like has_many,belong_to */
  var $dyn_methods=array();

  /**
   * has_many
   * @param mix
   * @return void
   * @comment generate getter/setter for array of target class
   */    

  protected function has_many()
  {
    $numcls = func_num_args();
    $cls_list = func_get_args();
    var_dump($cls_list);
    $inflector = Inflector::getInstance();
    $self=$this;
    foreach($cls_list as $clsname)
      {
        $plurclsname=$inflector->pluralize($clsname);
        //add,create,remove
        $funcname="add{$clsname}";
        $this->addfunc($funcname,
                       function($item) use($clsname,$self){
                         $id=$item->getID();
                         if($id==null)
                           {
                             //if id is null then recall the func after save
                             $item->afterSave_once=function()use ($item,$self,$clsname){
                               $addx="add{$clsname}";
                               $self->$addx($item);
                                                            
                             };
                             return $self;
                           } 
                         $getx="get{$clsname}ids";
                         $itemids=$self->$getx();
                         if($itemids==null)
                           $itemids=array();
                         array_push($itemids,$item->getID());
                         $setx="set{$clsname}ids"; 
                         $self->$setx($itemids);
                         return $self; 
                       }); 

        $funcname="create{$clsname}";
        $this->addfunc($funcname,
                       function() use($clsname,$self){
                           
                         $item=new $clsname();
                         $addx="add{$clsname}";  
                         $self->$addx($item);
                         return $item;   
                       }); 

        $funcname="remove{$clsname}";
        $this->addfunc($funcname,
                       function($item) use($clsname,$self){
                         $getx="get{$clsname}ids";
                         $itemids=$self->$getx();
                         $key=array_search($item->getID(),$itemids);  
                         if(gettype($key)=="boolean") //no such value
                           return $self;
                         unset($itemids[$key]);//remove the id
                         $setx="set{$clsname}ids"; 
                         $self->$setx($itemids);
                         return $self; 
                       }); 


        $funcname="get{$plurclsname}";
        $this->addfunc($funcname,
                       function() use($clsname,$self){
                         $getx="get{$clsname}ids";
                         $itemids=$self->$getx();     
                         $items=$clsname::find(array('_id'=>array('$in'=>$itemids)));
                         return $items; //return MongoRecordIterator
                       }); 

        $funcname="set{$plurclsname}";
        $this->addfunc($funcname,
                       function($items)use($clsname,$self){
                         $itemids=array();  
                         foreach($items as $item)
                           {
                             array_push($itemids,$item->getID());
                           }
                         if(count($itemids)==0)
                           return $self;
                         $setx="set{$clsname}ids";
                         $self->$setx($itemids);
                         return $self;
                       });                         
      } 
  }

  /**
   * has_one
   * @param mixed
   * @return void
   * @comment it is same with belong_to func just with different expression
   */    

 
  protected function has_one()
  {
    $cls_list = func_get_args();
    call_user_func_array(array($this,"belong_to"),$cls_list);
  }

  /**
   * belong_to
   * @param mixed
   * @return void
   * @comment generate getter/setter for the target object
   */    

  protected function belong_to()
  {
    $numcls = func_num_args();
    $cls_list = func_get_args();
    $self=$this;
    foreach($cls_list as $clsname)
      {
        $funcname="get{$clsname}";
        $this->addfunc($funcname,
                       function() use($funcname,$clsname,$self){
                         $getx="{$funcname}Id";
                         $result= $clsname::findOne(array('_id'=>$self->$getx()));
                         return $result;
                       });                       

        $funcname="set{$clsname}";  
        $this->addfunc($funcname,
                       function($ins) use($funcname,$self){
                         $setx="{$funcname}Id";
                         $self->$setx($ins->getID());
                         return $self;
                       });                         
     
      } 
  }

  /**
   * addfunc
   * @param String,Closure
   * @return void
   * @comments dynamicly add function for class
   */    

  
  protected function addfunc($name,$func)
  {
    $this->$name=$func;
    // $this->dyn_methods[$name]=$func;

  }

}





<?
require_once '../lib/BaseMongoRecord.php';
$dbusername="foo";
$dbpassword="foo";
BaseMongoRecord::$database = 'foodb';
BaseMongoRecord::$connection = new Mongo("mongodb://${dbusername}:${dbpassword}@localhost/".BaseMongoRecord::$database);

class Student extends BaseMongoRecord
{
  protected $belong_to=array('School');
  /**
      can add more targets with-> $belong_to=array('ClsA','ClsB',...)
      below methods are genereated automaticlly:
      $this->getSchool();//return school
      $this->setSchool($school);//return $this
    */


}

class School extends BaseMongoRecord
{
  protected $has_many=array('Student');
  /** 
       Can add more targets with-> $has_many=array('ClsA','ClsB',...)
       below methods are genereated automaticlly:
       $this->getStudents();//return MongoRecordIterator
       $this->setStudents($stuarray);//parm is array of students,return $this
       $this->createStudent();//new student and return it,return student
       $this->addStudent($stu);//parm is student instante,return $this
       $this->removeStudent($stu);//parm is student instant,return $this
    */
}
$school=new School();
$school->setName('fooschool');

$school->createStudent()->setName("foostu1")->save();
$school->createStudent()->setName("foostu2")->save();
$school->save();

$students=$school->getStudents();//=>MongoRecordIterator
var_dump($students->count());//=>2
foreach($students as $student)
  {
    var_dump($student);
  }

$student=new Student();
$student->setName('foostu3')->save();
$school->addStudent($student);
$students=$school->getStudents();//=>MongoRecordIterator
var_dump($students->count());//=>3
foreach($students as $stu)
  {
    var_dump($stu);
  }

$student->setSchool($school)->save();

var_dump($student->getSchool()->getName());//=>'fooschool'

?>
<?php
/*
* Copyright (c) 2011 Riccardo Cagnasso, Paolo Podesta
*
* Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the
* Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software,
* and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

* The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A
* PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
* CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

* NG ORM it's a tiny simple wanna be ORM implemented in PHP and ispired to python's SQLAlchemy.
*/

//We use Zend_Db_Select for building queries
require_once 'Zend/Db.php';
require_once "{$library_path}/NGOrm/Query.php";
require_once "{$library_path}/NGOrm/Session.php";

class ColumnNotFound extends Exception{}
class ObjectNotInSession extends Exception{}

class Relation{
    /*
     *A Relation reppresent a relation between to tables in a database.
     *The relation is declared inside an object.
     *Parameters: 
     *  $class: the class of the other object in relation
     *  $keys: the foreign keys involved in the relation
     */

    public $class;
    public $keys;

    public function __construct($class, $keys){
        $this->class=$class;
        $this->keys=$keys;
    }
}

//A relation where the object is in relation with one (or none) other object
class OneRelation extends Relation{}

//A relation where the object is in relation with many other objects
class ManyRelation extends Relation{}

//A relation where many object are in relation with many other objects. To implement.
//class Many2ManyRelation extends Relation{}

class ForeignKey{
    /*
     *Foreign keys should reppresent foreign keys between objects.
     *They would be needed to automatically generate SQL schema and they could make
     *writing of the Relations shorter and more expressive.
     *Currently foreign keys are not used.
     *Parameters
     *  target_table: the table to which the foreign key refers
     *  target columns: the list of columns to which the foreign key refers
     */
    public $target_table;
    public $target_columns;

    public function __construct($target_table, $target_columns){
        $this->target_table=$target_table;
        $this->target_columns=$target_columns;
    }
}

abstract class NGColumn{
    public $type;

    public function to_sql_value($value){
        return $value;
    }

    public function to_php_value($value){
        return $value;
    }
}

class NGString extends NGColumn{
    public $type='string';
}
class NGInt extends NGColumn{
    public $type='int';
}
class NGDate extends NGColumn{
    public $type='date';
    public $format;

    public function __construct($format='yyyy-MM-dd'){
        $this->format=$format;
    }

    public function to_sql_value($value){
        return $value->toString($this->format);
    }

    public function to_php_value($value){
        return new Zend_Date($value, $this->format);
    }
}



class NGObject{
    /*
     *BaseObject is the object that you extend to make a permanent object.
     */
    protected $db;

    /*
     *Here we define the structure of the table where the object is saved
     */
    public $tablename=null;
    public $pk=array();
    public $columns=array();
    public $relations=array();

    private $data=array();
    private $session=null;

    /*
     *pk_value contains the value of the pk colums.
     *This is needed to update the pk of an object
     */
    public $pk_value=array();

    public function __construct($arr=array(), $session=null, $fromdb=false){
        /*
         *The constructor set the "data" array and assign a session if it's provided,
         *then it updates the saved primary key
         */
        foreach($this->columns as $column_name=>$column_type){
            if(isset($arr[$column_name])){
                if($fromdb){
                    $this->data[$column_name]=$column_type->to_php_value($arr[$column_name]);
                }else{
                    $this->data[$column_name]=$arr[$column_name];
                }

            }    
        }

        $this->session=$session;
        $this->update_pk();
    }

    public function __set($var, $val){
        /*
         *We define __set such as when we set an undefined property
         *if this is a column, we the array in "data" and we set this
         *object as "dirty" for update.
         */
        if(!is_null($this->session)){
            $this->session->add_dirty($this);
        }
        if(isset($this->columns[$var])){
            $this->data[$var]=$val;
        }else{
            throw new ColumnNotFound();
        }
        //todo: implement "set" for relations
    }

    public function __get($var){
        /*
         * We define __get so when you try to read a column, you get that
         * from "data" and if you try to read a relation, data are get
         * from the database.
         */
        if(isset($this->columns[$var])){
            return $this->data[$var];
        }else if(isset($this->relations[$var])){
            if(is_null($this->session)){
                throw new ObjectNotInSession();
            }else{
                $relation=$this->relations[$var];
                $query=$this->session->query($relation->class);
                #$data=$this->to_db_array();
                foreach($relation->keys as $target_key=>$this_key){
                    $query->where($target_key, $this->data[$this_key]);
                }
                if(get_class($relation)==ManyRelation){
                    return $query->all();
                }else if(get_class($relation)==OneRelation){
                    return $query->one();
                }
                
            }
        }else{
            throw new ColumnNotFound();
        }
    }
    
    public function to_db_array(){
        /*
         *Returns an array with columns data
         */

        $data=array();
        foreach($this->columns as $column_name=>$column_type){
            if(isset($this->data[$column_name])){
                $data[$column_name]=$column_type->to_sql_value($this->data[$column_name]);
            }    
        }
        return $data;
    }
    
    public function from_db_array($arr, $session=null){
        /*
         *From an array with columns data it creates a new object
         */
        if(empty($arr)){
            return null;
        }else{
            $new_obj = clone $this;
            $new_obj->__construct($arr, $session, true);

            return $new_obj; 
        }
    }

    public function set_session($session){
        /*
         *Add a session to this object. And viceversa.
         */
        $this->session=$session;
    }

    public function update_pk(){
        /*
         *Update the stored pk of this object.
         *This is done when the sassion is flushed.
         */
        foreach($this->pk as $pk){
            $this->pk_value[$pk]=$this->data[$pk];
        }
    }

    public function delete(){
        if($this->session){
            $this->session->delete($this);
        }
    }
}
?>

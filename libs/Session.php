<?
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

* NG ORM it's a tiny simple wanna be ORM implemented in PHP and ispired to python's SQLAlchemy
*/

class CannotInsertException extends Exception{}

class NGSession{
    /*
     *A session contains the connection to the db, tracks the dirty objects
     *and let you build queries
     */
    public $db;
    public $objects;
    public $relations;
    private $dirty=array();

    public function __construct($configuration){
        if(is_null($GLOBALS['zend_db_session'])){
            $db = Zend_Db::factory('Pdo_Mysql', $configuration);
            $db->query('SET NAMES UTF8');
            $GLOBALS['zend_db_session']=$db;
        }
        //Get the global db session
        $this->db=$GLOBALS['zend_db_session'];
    }

    public function add_object($object){
        /*
         *Add an object type, a class to session.
         *Todo: rename it to add_class?
         *Todo: find a way to do the same thing automatically
         *with the reflection
         */
        $this->objects[get_class($object)]=$object;
    }

    public function query($object){
        /*
         *Creates a new query.
         */
        return new NGQuery($this->db, $this, $this->objects[$object]);
    }

    public function by_pk($object, $pks){
        /*
         *Return an object by pk
         */
        $query=new NGQuery($this->db, $this, $this->objects[$object]);
        foreach($pks as $column_pk=>$value_pk){
            $query->where($column_pk, $value_pk);
        }
        return $query->one();
    }

    public function add($object){
        /*
         *Add an object to the session and save it to the database
         */ 
        $object->set_session($this);
        $data=$object->to_db_array();

        try{
            $this->db->insert($object->tablename, $data);
        }catch(Exception $e){
            throw new CannotInsertException();
        }
    }

    public function delete($object){
        /*
         *Deletes an object from the session and from the database
         */
        $data=$object->to_db_array();
        $conditions=array();
        foreach($object->pk as $pk){
            $conditions[]=$this->db->quoteInto("{$pk}=?", $data[$pk]);
        }
        $this->db->delete($object->tablename, implode(" AND ", $conditions));
        $object->set_session(null);
    }

    public function add_dirty($object){
        /*
         *Mark an object as dirty.
         */
        $key='';
        $data=$object->to_db_array();
        foreach($object->pk as $pk){
            $key.=$data[$pk];
        }
        $this->dirty[get_class($object)][$key]=$object;
    }

    private function update_object($object){
        /*
         *Save changes to an object
         */
        $data=$object->to_db_array();
        $conditions=array();
        foreach($object->pk_value as $pk=>$value){
            $conditions[]="{$pk}='{$value}'";
        }
        $this->db->update(
            $object->tablename,
            $data,
            implode(" AND ", $conditions));
    }

    public function save(){
        /*
         *Flush the session updating all dirty objects
         */
        foreach($this->dirty as $object_types){
            foreach($object_types as $object){
                $this->update_object($object);
                $object->update_pk();
            }
        }
        $this->dirty=array();
    }
}
?>
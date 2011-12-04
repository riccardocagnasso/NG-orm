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

* NG ORM it's a tiny simple wanna be ORM implemented in PHP and ispired to python's SQLAlche
*/
class NGQuery{
    /*
     *This object reppresent a query, it's based on zend_db_select and
     *let you build queries and execute them, getting objects as results
     *
     *todo: add more stuff
     *todo: build the query in an intermediate data structure and transform
     *it in a zend_db_select query just before evaluating this. This is useful
     *to let the user modify the queries more easily. For example it will let
     *you do count() on arbitrary queries.
     */

    public $object;

    private $db;
    private $session;
    public $zend_query;

    private $select;
    private $from;
    private $where;
    private $limit;

    public function __construct($db, $session, $object){
        $this->object=$object;

        $this->db=$db;
        $this->session=$session;
        $this->zend_query=$this->get_zend_query();

        $this->select=new NGQuerySelect('*');
        $this->from=new NGQueryFrom($this->object->tablename);
    }

    public function all(){
        /*
         *Evaluates a query and returns the result
         */
        $res=array();
        foreach($this->db->fetchAll($this->zend_query) as $row){
            $res[]=$this->object->from_db_array($row, $this->session);
        }
        return $res;
    }

    public function one(){
        /*
         *Evaluates a query that return one object and returns it
         */
        return $this->object->from_db_array(
            $this->db->fetchRow($this->zend_query), $this->session);
    }

    public function count(){
        /*
         *Return the count of the objects
         *todo: make this useful
         */
        return $this->db->fetchOne(
            $this->db->select()
                ->from($this->object->tablename, array('COUNT(*)'))
        );
    }

    public function where($column, $value, $op='='){
        /*
         *Specify a where condition
         */
        $this->zend_query=$this->zend_query->where("{$column}{$op}?", $value);

        return $this;
    }

    private function get_zend_query($where=array(), $offset=null, $number=null){
        /*
         *Build the basic zend_query
         */
        
        $query=$this->db->select()
            ->from($this->object->tablename);
        
        if(!is_null($offset) && !is_null($number)){
            $query->limit($number, $offset);
        }
        
        foreach($where as $column=>$value){
            $this->where($column, $value);
        }
        return $query;
    }
}

class NGQuerySelect{
    public $select;
    function __construct($select){
        $this->select=$select;
    }
}

class NGQueryFrom{
    public $table;
    function __construct($table){
        $this->table=$table;
    }
}

class NGQueryWhere{}
class NGQueryLimit{}
?>
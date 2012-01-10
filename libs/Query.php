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
    private $where=array();
    private $limit;

    public function __construct($db, $session, $object){
        $this->object=$object;

        $this->db=$db;
        $this->session=$session;
        $this->zend_query=$this->get_zend_query();

        $this->select=new NGQuerySelect($this->session, '');
        $this->from=new NGQueryFrom($this->object->tablename);
    }

    public function all(){
        /*
         *Evaluates a query and returns the result
         */
        $query=$this->build_zend_query();
        $res=array();
        foreach($this->db->fetchAll($query) as $row){
            $res[]=$this->object->from_db_array($row, $this->session);
        }
        return $res;
    }

    public function one(){
        /*
         *Evaluates a query that return one object and returns it
         */
        $query=$this->build_zend_query();
        return $this->object->from_db_array(
            $this->db->fetchRow($query), $this->session);
    }

    public function count($count_clause='COUNT(*)'){
        /*
         *Return the count of the objects
         */
        $old_from=$this->from;
        $this->from=new NGQueryFrom($this->object->tablename, array($count_clause));
        $query=$this->build_zend_query();
        $this->from=$old_from;

        return $this->db->fetchOne($query);
    }

    public function where($column, $value, $op='='){
        /*
         *Specify a where condition
         */
        $this->where[]=new NGQueryWhere("{$column}{$op}?", $value);

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

    private function build_zend_query(){
        $zquery=$this->select->process_element(null);
        $zquery=$this->from->process_element($zquery);

        foreach($this->where as $query_element){
            $zquery=$query_element->process_element($zquery);
        }

        if(!empty($this->limit)){
            $zquery=$this->limit->process_element($zquery);
        }
        return $zquery;
    }
}

class NGQueryElement{
    public function process_element($zquery){}
}

class NGQuerySelect extends NGQueryElement{
    public $select;
    public $session;
    function __construct($session, $select){
        $this->session=$session;
        $this->select=$select;
    }

    public function process_element($zquery){
        return $this->session->db->select();
    }
}

class NGQueryFrom extends NGQueryElement{
    public $table;
    public $columns;
    function __construct($table, $columns=null){
        $this->table=$table;
        $this->columns=$columns;
    }

    public function process_element($zquery){
        if(!empty($this->columns)){
            return $zquery->from($this->table, $this->columns);
        }else{
            return $zquery->from($this->table);
        }
    }
}

class NGQueryWhere extends NGQueryElement{
    public $condition;
    public $parameter;
    function __construct($condition, $parameter){
        $this->condition=$condition;
        $this->parameter=$parameter;
    }

    public function process_element($zquery){
        return $zquery->where($this->condition, $this->parameter);
    }
}

class NGQueryLimit extends NGQueryElement{
    public $a;
    public $b;
    function __construct($a, $b=null){
        $this->a=$a;
        $this->b=$b;
    }

    public function process_element($zquery){
        return $zquery->limit($a, $b);
    }
}

class NGQueryDistinct extends NGQueryElement{
    public function process_element($zquery){
        return $zquery->distinct();
    }
}

class NGQueryJoin extends NGQueryElement{
    public $table;
    public function process_element($zquery){
        return $zquery->join($table);
    }
}
?>
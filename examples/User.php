<?php
require_once('DBConfig.php');
require_once('NGOrm.php');
require_once('Zend/Date.php');

/*
 *We declare a new session and we save it in $GLOBALS 
 */
$session=new NGSession(DBConfig::get_config());
$GLOBALS['session']=$session;

class File extends NGObject{
    public $tablename='file';
    public $pk=array('filename', 'username');

    public $columns=array('filename',
        'username',
        'hash',
        'mime',
        'size',
        'description',
        'title');

    public function __construct($arr=array(), $session=null){
        /*
         *In case of a relation whe need to redefine the costructor.
         */
        $this->relations=array('user'=> new OneRelation(User, array('username'=>'username')));

        parent::__construct($arr, $session);
    }
}

$session->add_object(new File());

class User extends NGObject{
    /*
     * A User obect reppresent a user in the system
     */
    
    public $tablename='user';
    public $pk=array('username');

    public $columns=array('username', 'password', 'description');

    public function __construct($arr=array(), $session=null){
        $this->relations=array('files'=>new ManyRelation(File, array('username'=>'username')));

        parent::__construct($arr, $session);
    }

    public static function login($username, $password){
        $user=$GLOBALS['session']->query(User)
            ->where('username', $username)
            ->where('password', sha1($password))
            ->one();
        
        if(!is_null($user)){
            $_SESSION['user']=$user;
        }

        return $user;
    }

    public static function logout(){
        unset($_SESSION['user']);
    }
}

$session->add_object(new User());

?>
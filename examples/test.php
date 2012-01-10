<?php
require_once('User.php');

echo '<pre>';
$query=$GLOBALS['session']->query(User);
$users=$query->all();
$count=$query->count();

echo "Users are {$count}\n";
echo "\n";
foreach($users as $user){
    echo "User:\n";
    var_dump($user->username, $user->password);
    echo "\n";

    //here we read a relation
    foreach($user->files as $file){
        echo "File:\n";
        var_dump($file->filename);
        var_dump($file->username);
        var_dump($file->hash);
        var_dump($file->mime);
        var_dump($file->size);
    } 
}


echo '</pre>';
?>
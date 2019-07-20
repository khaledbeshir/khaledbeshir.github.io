<?php

require_once './vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;


class Articles {
    protected $database;
    protected $dbname = 'Categories';


    public function __construct(){
        $acc = ServiceAccount::fromJsonFile(__DIR__ . '/secret/deployarticle-firebase-adminsdk-308br-6429f7bcd2.json');
        $firebase = (new Factory)->withServiceAccount($acc)->create();

        $this->database = $firebase->getDatabase();
    }



    public function get(String $userID = NULL){
        if (empty($userID) || !isset($userID)) { return FALSE; }

        if ($this->database->getReference($this->dbname)->getSnapshot()->hasChild($userID)){

            return $this->database->getReference($this->dbname)->getChild($userID)->getSnapshot()->getValue();


          } else {
            return FALSE;
        }
    }





    public function delete($userID , $cat) {
        if (empty($userID) || !isset($userID)) { return FALSE; }

        if ($this->database->getReference($this->dbname)->getChild($cat)->getSnapshot()->hasChild($userID)){
            $this->database->getReference($this->dbname)->getChild($cat)->getChild($userID)->remove();
            return TRUE;
        } else {
            return FALSE;
        }
    }
      }

     $Articles = new Articles();


if (isset($_GET['catt']) && isset($_GET['del'])){

    $Articles->delete($_GET['del'] , $_GET['catt']);
    getArticles($_GET['catt'] , $Articles);
}

if(isset($_GET['cat'])){

    $category = $_GET['cat'];
    getArticles($category , $Articles);


}

function getArticles ($category , $Articles ){


    $ArticleArray = array();


    $ArticleArray = $Articles->get($category);

    foreach($ArticleArray as $key => $value) {

        echo "<iv id='article'><div id='title'>".$value['title'] ."   </div>  <br>  
                <div id='author'>author name</div>   <br>
            writen on " . $value['date'] . "   at " . $value['time'] . "<br><br><br>    
              <div id='content'>        " .$value['content']."</div><div class='delete'><a type='hidden' href='Articles.php?id=".$key."&catt=".$category."'> DELETE</a></div> ";
        echo "<br> <br> <br> <hr><br> <br> <br></div> ";
    }

}


if(isset($_GET['id']) && isset($_GET['catt'])){
    header("Location:Articles.php?catt=".$_GET['catt']."&del=".$_GET['id']);


    exit();

    //var_dump(cat());

    // $Articles->delete($_GET['id'] , null );
    //header("Location:Articles.php");
    // exit();
}



     ?>

<style>

    #title {
        color: blue;
        font-weight: bold;
        text-align: center;
        font-size: 23px;
    }

    #author {
        text-align: center;
    }

    .delete {
        display: block;
        width: 115px;
        height: 25px;
        background: burlywood;
        padding: 10px;
        text-align: center;
        border-radius: 5px;
        color: white;
        font-weight: bold;
        margin: 20px;
        child-align: right;
    }

    a {
        text-decoration: none;
    }

</style>

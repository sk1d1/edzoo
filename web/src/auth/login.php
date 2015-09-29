<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Edzoo\EdzooException;

// the controllers factory
$login = $app['controllers_factory'];


#####################################################################
##  POST /login/                                                   ##
####  Route to log the user in                                     ##
#####################################################################

$login->post('/', function (Request $req) use($app) {

  // parse the request to get the variables if there is an exception return immediately
  $user = json_decode($req->getContent(), true);
  if (!isset($user)) {
    throw new EdzooException("Incorrect JSON format", "INVALID_JSON", 400);
  }

  // check for the required fields
  if(!isset($user['username']) || !isset($user['password'])) {
    throw new EdzooException("Parameter missing", "INVAID_REQUEST", 400);
  }

  // prepare the query
  $st = $app['pdo']->prepare("SELECT * FROM appuser WHERE username = :username AND password = :password");
  // execute the query
  $st->execute(array(":username" => $user['username'], ":password" => $user['password']));

  // if the user already exist then return with token
  if ($row = $st->fetch(PDO::FETCH_ASSOC)) {

    // prepare for generating the token
    $issuedAt = time();
    $data = [
      'iat'  => $issuedAt,
      'data' => [
        'user'   => $user['username']
      ]
    ];
    $secret = $_SERVER['HTTP_USER_AGENT'];

    // generate the token
    $token = JWT::encode(
      $data,
      $secret,
      'HS256'
    );

    // send the data
    $res['status'] = "LOGIN";
    $res['data'] = $token;

    return $app->json(array (
      'status' => "LOGIN",
      'data' => array(
        "token"=>$token,
        "username"=>$user['username']
        )
    ), 200);

  } else {

    return $app->json(array(
      'status' => "INVALID_USER",
      'data' => "The user does not exist"
    ), 401,array(
      'WWW-Authenticate'=>"Basic realm = 'site_login'"
    ));

  }
});


#####################################################################
##  POST /login/fb/                                                ##
####  Route to log the user in  using facebook                     ##
#####################################################################

$login->post('/fb/', function (Request $req) use($app) {

  // parse the request to get the variables if there is an exception return immediately
  $data = json_decode($req->getContent(), true);

  if (!isset($data)) {
    throw new EdzooException("Incorrect JSON format", "INVALID_JSON", 400);
  }

  $fb_token=$data['fb_token'];

  
  $fb = new Facebook\Facebook([
    'app_id' => '514056365409540',
    'app_secret' => '70c9eb58bdc5a4198143330103543cdb',
    'default_graph_version' => 'v2.4',
   ]);

  try {
  // Returns a `Facebook\FacebookResponse` object
    $response = $fb->get('/me?fields=id,first_name,last_name,email,birthday', $fb_token);
} catch(Facebook\Exceptions\FacebookResponseException $e) {
  echo 'Graph returned an error: ' . $e->getMessage();
  exit;
} catch(Facebook\Exceptions\FacebookSDKException $e) {
  echo 'Facebook SDK returned an error: ' . $e->getMessage();
  exit;
}

//get all the info in $user

  $user = json_decode($response->getGraphUser(),true);


// check for the required fields
  if (!isset($user['id']) || !isset($user['email'])) {
    throw new EdzooException("Parameter missing", "INVAID_REQUEST", 400);
  }

  // prepare the query and check whether the username exists
  $st = $app['pdo']->prepare("SELECT * FROM appuser WHERE email= :email");
  // execute the query
  $st->execute(array(":email" => $user['email']));
  $row = $st->fetch(PDO::FETCH_ASSOC);
  // if the username doesnot exist then return with token and update the info 
  if (!$row) {

    // finally the database with transction
    // start the transaction
    try {
      $app['pdo']->beginTransaction();
      // prepare the query
      $st = $app['pdo']->prepare("INSERT INTO appuser(username, email, password) values (:username, :email, :password)");

      // execute the query
      $result = $st->execute(array(':username' => $user['email'], ':email' => $user['email'], ':password' => $user['id']));
       // commit the transaction
      $app['pdo']->commit();
  } catch (PDOException $e) {
    $app['pdo']->rollback();
    throw new PDOException($e->getMessage(), 500);
  }

// Info update 

   $query="UPDATE user_info SET ";

   if(isset($user['first_name']))
   {
       $query .= 'first_name = :first_name, ';
       $parameters['first_name']=$user['first_name'];
   }


   if(isset($user['last_name']))
   {
       $query .= 'last_name = :last_name, ';
       $parameters['last_name'] = $user['last_name'];
   }


  if(isset($user['birthday']))
   {

      $query .= 'dob = :dob, ';
      $parameters[':dob'] = $user['birthday'];
   }

   if(isset($user['id']))
   {
      $query .= 'profile_image = :id, ';
      $parameters[':id'] = $user['id'];
   }  
  
   $query = substr($query,0,-2);
   $query .= " where username = :username";
   $parameters[':username'] = $user['email'];


    try {
        // begin the transaction
        $app['pdo']->beginTransaction();
        // prepare the query
        $st = $app['pdo']->prepare($query);
        // execute query
        $update_count = $st->execute($parameters);
        // commit this
        $app['pdo']->commit();

      } catch (PDOException $e) {
        $app['pdo']->rollback();
        throw new PDOException($e->getMessage(), 500);
      }


    // prepare for generating the token
    $issuedAt = time();
    $data = [
      'iat'  => $issuedAt,
      'data' => [
        'user'   => $user['email']
      ]
    ];
    $secret = $_SERVER['HTTP_USER_AGENT'];

    // generate the token
    $token = JWT::encode(
      $data,
      $secret,
      'HS256'
    );

    
    return $app->json(array (
      'status' => "USER_CREATED",
      'data' => array(
        "token"=>$token,
        "username"=>$user['email'],
        "id"=>$user['id']
        )
    ), 201);

  } else if(($row['email']==$row['username']))
      {
        // prepare for generating the token
        $issuedAt = time();
        $data = [
          'iat'  => $issuedAt,
          'data' => [
          'user'   => $user['email']
          ]
        ];
        $secret = $_SERVER['HTTP_USER_AGENT'];

        // generate the token
        $token = JWT::encode(
          $data,
          $secret,
          'HS256'
        );
      
        return $app->json(array (
         'status' => "LOGIN",
         'data' => array(
           "token"=>$token,
           "username"=>$user['email'],
           "id"=>$user['id']
            )
        ), 200);
      }else{
        throw new EdzooException("The email already exist", "ALREADY_EXIST", 400);
      }

});


// return the controllers factory
return $login;


?>
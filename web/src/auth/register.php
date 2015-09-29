<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Edzoo\EdzooException;

// the controller factory to hold all the controllers
$register = $app['controllers_factory'];



#####################################################################
##  POST /register/                                                ##
####  Route to create a new user                                   ##
#####################################################################

$register->post('/', function(Request $req) use($app) {

  // parse the request to get the variables if there is an exception return immediately
  $user = json_decode($req->getContent(), true);
  if (!isset($user)) {
    throw new EdzooException("Incorrect JSON format", "INVALID_JSON", 400);
  }

  // check for the required fields
  if(!isset($user['username']) || !isset($user['password']) || !isset($user['email'])) {
    throw new EdzooException("Parameter missing", "INVAID_REQUEST", 400);
  }

  // prepare the query
  $st = $app['pdo']->prepare("SELECT * FROM appuser WHERE username = :username::text OR email = :email::text");
  // execute the queries
  $st->execute(array(":username" => $user['username'], ":email" => $user['email']));

  // if the user already exist then throw
  if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    if ($row['username'] === $user['username'] && $row['email'] === $user['email']) {
      throw new EdzooException("The username and email already exists", "ALREADY_EXIST", 400);
    } else if ($row['username'] === $user['username']) {
      throw new EdzooException("The username already exists", "ALREADY_EXIST", 400);
    } else if ($row['email'] === $user['email']) {
      throw new EdzooException("The email already exist", "ALREADY_EXIST", 400);
    }
  }

  // alright now the user is new, add to the database
  // prepare for token generation
  $issuedAt = time();
  $data = [
    'iat'  => $issuedAt,
    'data' => [
      'user'   => $user['username']
    ]
  ];
  //Get the user-agent header from server bag and take it as a secret key
  $secret = $_SERVER['HTTP_USER_AGENT'];

  // generate the token
  $token = JWT::encode(
    $data,
    $secret,
    'HS256'
  );

  // finally the database with transction
  // start the transaction
  try {
    $app['pdo']->beginTransaction();
    // prepare the query
    $st = $app['pdo']->prepare("INSERT INTO appuser(username, email, password) values(:username, :email, :password)");
    // execute the query
    $result = $st->execute(array(':username' => $user['username'], ':email' => $user['email'], ':password' => $user['password']));
    // commit the transaction
    $app['pdo']->commit();
  } catch (PDOException $e) {
    $app['pdo']->rollback();
    throw new PDOException($e->getMessage(), 500);
  }

  // send response to the user
  $res = array (
    'status' => 'USER_CREATED',
    'data' => $token
  );
  return $app->json($res, 201);

});


// return the controllers factory
return $register;

?>
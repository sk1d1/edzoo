<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Edzoo\EdzooException;


// the controller factory to hold all the controllers
$users = $app['controllers_factory'];



#####################################################################
##  GET /user/feed/                                                ##
####  Route to return the courses feed for the user                ##
#####################################################################
// Generate according to the preferences of user
$users->get('/feed/{offset}', function(Request $req, $offset) use($app) {

  // get the language and type from the query string
  //$lang = $app['request']->get('lang');
  //$type = $app['request']->get('type');

  // get the username
  list($token) = sscanf($req->headers->get('Authorization'), 'token %s');
  $jwt = JWT::decode($token, $_SERVER['HTTP_USER_AGENT'], array('HS256'));
  $username = $jwt->data->user;

  // get the keywords from the database
  // prepare the query
  $st = $app['pdo']->prepare("SELECT keywords,levels FROM user_info where username = :username");
  // excute the query
  $st->execute(array(':username' => $username));

  // if we have the user in user_info we must have the keywords as well
  if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
     $keywords = $row['keywords'];
     $level = $row['levels'];
  } else {
    return $app->json(array (
      'status' => "INVALID_USER",
      'data' => "The user does not exist"
    ), 400, array(
      'WWW-Authenticate'=>"Basic realm = 'site_login'"
    ));
  }

 /* // prepare and execute the query
  // if we have the type
  if (isset($type)&&isset($lang)) {
    $st = $app['pdo']->prepare("SELECT * FROM courses where type = :type");
    $st->execute(array (
    ':type' => $type
    ));
  } else {
    $st = $app['pdo']->prepare("SELECT * FROM courses");
    $st->execute();
  }*/

  $parameters = array();
  $query = "SELECT * from courses where true";


  // check if there are filters
  // check for type filter
  $type = $app['request']->get('type');
  if (isset($type)) {
    $query .= ' and type = :type';
    $parameters[':type'] = $type;
  }

  // check for language filter
  $lang = $app['request']->get('lang');
  if (isset($lang)) {
    $query .= ' and lang = :lang';
    $parameters[':lang'] = $lang;
  }

  // check for keywords
  // Keywords list
  if (isset($keywords)&&$keywords!="") {
    $query .= " and keywords in ($keywords)";
  }


  // check for level
  if (isset($level) && $level!="") {
    $query .= " and level = :level";
    $parameters[':level'] = $level ;
  }

  $query .= " ORDER BY id DESC LIMIT 10 OFFSET $offset";
  // prepare the query
  $st = $app['pdo']->prepare($query);
  // execute the query
  $st->execute($parameters);

  // get the data and put it in the array
  $temp_array = array();
  while($row = $st->fetch(PDO::FETCH_ASSOC)) {
    array_push($temp_array, $row);
  }

  // return the reponse
  return $app->json(array (
    'status' => "SUCCESS",
    'data' => $temp_array
  ), 200, array(
    'Cache-Control' => 'max-age=259200'
  ));

});



#####################################################################
##  PUT /user/info/                                                ##
####  Route to update the user info                                ##
#####################################################################

$users->put('/info/', function(Request $req) use($app) {

  // parse the request into associative array
  $user = json_decode($req->getContent(), true);
  if (!isset($user)) {
    throw new EdzooException("Incorrect request, please check the fields", "INVALID_REQUEST", 400);
  }

  // get the username
  list($token) = sscanf($req->headers->get('Authorization'), 'token %s');
  $jwt = JWT::decode($token, $_SERVER['HTTP_USER_AGENT'], array('HS256'));
  $username = $jwt->data->user;

  $count = 0;
  $query="UPDATE user_info SET ";

  if(isset($user['first_name']))
  {
      $count++;
      $query .= 'first_name = :first_name, ';
      $parameters['first_name']=$user['first_name'];
  }


  if(isset($user['last_name']))
  {
      $count++;
      $query .= 'last_name = :last_name, ';
      $parameters['last_name'] = $user['last_name'];
  }

  if(isset($user['user_type']))
  {
      $count++;
      $query .= 'user_type = :user_type, ';
      $parameters[':user_type'] = $user['user_type'];
  }

  if(isset($user['institution']))
  {
      $count++;
      $query .= 'institution = :institution, ';
      $parameters[':institution'] = $user['institution'];
  }
   
  if(isset($user['levels']))
  {
      $count++;
      $query .= 'levels = :levels, ';
      $parameters[':levels'] = $user['levels'];
  }

  if(isset($user['dob']))
  {
      $count++;
      $query .= 'dob = :dob, ';
      $parameters[':dob'] = $user['dob'];
  }
  
  $query = substr($query,0,-2);
  $query .= " where username = :username";
  $parameters[':username'] = $username;
  //echo $parameters;
  //  update the database with transaction support
  if($count>0)
  {
      try {
        // begin the transaction
        $app['pdo']->beginTransaction();
        // prepare the query
        $st = $app['pdo']->prepare($query);
        // execute query
        $update_count = $st->execute($parameters);
        // commit this
        $app['pdo']->commit();

        // check if something was updated or not
        if($update_count > 0) {
          return $app->json(array (
            'status' => "INFO_UPDATED",
            'data' => "User info is updated"
          ), 200);
        } else {
          return $app->json(array (
            'status' => "INVALID_USER",
            'data' => "The user does not exist"
          ), 400, array(
            'WWW-Authenticate'=>"Basic realm = 'site_login'"
          ));
        }

      } catch (PDOException $e) {
        $app['pdo']->rollback();
        throw new PDOException($e->getMessage(), 500);
      }
  }else{
    return $app->json(array (
            'status' => "NOTHING_UPDATED",
            'data' => "Nothing to update"
          ), 200);
  }

})->before($before);



#####################################################################
##  GET /user/info                                                 ##
####  Route to return the user info                                ##
#####################################################################

$users->get('/info/', function(Request $req) use($app) {

  // get the username
  list($token) = sscanf($req->headers->get('Authorization'), 'token %s');
  $jwt = JWT::decode($token, $_SERVER['HTTP_USER_AGENT'], array('HS256'));
  $username = $jwt->data->user;

  // prepare the query
  $st = $app['pdo']->prepare("SELECT first_name, last_name, institution, username, user_type, dob, levels, profile_image FROM user_info where username = :username");
  $rt = $app['pdo']->prepare("SELECT email FROM appuser where username = :username");
  // execute
  $st->execute(array(':username' => $username));
  $rt->execute(array(':username' => $username));

  // if we have the user_info and  email
  if (($row = $st->fetch(PDO::FETCH_ASSOC)) && ($row2=$rt->fetch(PDO::FETCH_ASSOC))) {
    $row['email'] = $row2['email'];
    return $app->json(array (
      'status' => "SUCCESS",
      'data' => $row
    ), 200);
  } else {
    return $app->json(array (
      'status' => "INVALID_USER",
      'data' => "The user does not exist"
    ), 401, array(
      'WWW-Authenticate'=>"Basic realm = 'site_login'"
    ));
  }

});



#####################################################################
##  GET /user/courses                                              ##
####  It will return the courses that this user uploaded           ##
#####################################################################

$users->get('/courses/', function(Request $req) use($app) {

  // get the username
  list($token) = sscanf($req->headers->get('Authorization'), 'token %s');
  $jwt = JWT::decode($token, $_SERVER['HTTP_USER_AGENT'], array('HS256'));
  $username = $jwt->data->user;

  // get the courses from the database
  // prepare the query
  $st = $app['pdo']->prepare("SELECT * from courses where username = :username");
  // excecute the query
  $st->execute(array(':username' => $username));

  // fetch the data
  $temp_array = array();
  $i = 0;
  while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    array_push($temp_array, $row);
    $i++;
  }

  // return the response
  if ($i > 0) {
    return $app->json(array (
      'status' => "SUCCESS",
    ), 200);
  } else {
    return $app->json(array (
      'status' => "NO_COURSE",
      'data' => "No course uploaded"
    ), 200);
  }

});



#####################################################################
##  GET /user/keywords                                             ##
####  It will return the values of keywords preferred by use       ##
#####################################################################

$users->get('/keywords/', function(Request $req) use($app) {

  // get the username
  list($token) = sscanf($req->headers->get('Authorization'), 'token %s');
  $jwt = JWT::decode($token, $_SERVER['HTTP_USER_AGENT'], array('HS256'));
  $username = $jwt->data->user;

  // get the keywords from the database
  // prepare the query
  $st = $app['pdo']->prepare("SELECT keywords FROM user_info where username = :username");
  // excute the query
  $st->execute(array(':username' => $username));

  // if we have the user in user_info we must have the keywords as well
  if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    return $app->json(array (
      'status' => "SUCCESS",
      'data' => $row['keywords']
    ), 200);
  } else {
    return $app->json(array (
      'status' => "INVALID_USER",
      'data' => "The user does not exist"
    ), 400, array(
      'WWW-Authenticate'=>"Basic realm = 'site_login'"
    ));
  }

});



#####################################################################
##  PUT /user/keywords                                             ##
####  It will update the user preferences                          ##
#####################################################################

$users->put('/keywords/', function(Request $req) use($app) {

  // parse the request into associative array
  $keywords = json_decode($req->getContent(), true);
  if (!isset($keywords)) {
    throw new EdzooException("Incorrect JSON format", "INVALID_JSON", 400);
  }

  // get the username
  list($token) = sscanf($req->headers->get('Authorization'), 'token %s');
  $jwt = JWT::decode($token, $_SERVER['HTTP_USER_AGENT'], array('HS256'));
  $username = $jwt->data->user;

  //  update the database with transaction support
  try {
    // begin the transaction
    $app['pdo']->beginTransaction();
    // prepare the query
    $st = $app['pdo']->prepare("UPDATE user_info SET keywords = :keywords where username = :username");
    $params = array (
      ':keywords' => $keywords['keywords'],
      ':username' => $username
    );
    // execute query
    $update_count = $st->execute($params);
    // commit this
    $app['pdo']->commit();

    // check if something was updated or not
    if($update_count > 0) {
      return $app->json(array (
        'status' => "KEYWORDS_UPDATED",
        'data' => "User keyword preferences are updated"
      ), 200);
    } else {
      return $app->json(array (
        'status' => "INVALID_USER",
        'data' => "The user does not exist"
      ), 400, array(
        'WWW-Authenticate'=>"Basic realm = 'site_login'"
      ));
    }

  } catch (PDOException $e) {
    $app['pdo']->rollback();
    throw new PDOException($e->getMessage(), 500);
  }

});




#####################################################################
##  GET /user/levels                                               ##
####  It will return the level of education preferred by user      ##
#####################################################################

$users->get('/levels/', function(Request $req) use($app) {

  // get the username
  list($token) = sscanf($req->headers->get('Authorization'), 'token %s');
  $jwt = JWT::decode($token, $_SERVER['HTTP_USER_AGENT'], array('HS256'));
  $username = $jwt->data->user;

  // get the keywords from the database
  // prepare the query
  $st = $app['pdo']->prepare("SELECT levels FROM user_info where username = :username");
  // excute the query
  $st->execute(array(':username' => $username));

  // if we have the user in user_info we must have the keywords as well
  if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    return $app->json(array (
      'status' => "SUCCESS",
      'data' => $row['levels']
    ), 200);
  } else {
    return $app->json(array (
      'status' => "INVALID_USER",
      'data' => "The user does not exist"
    ), 400, array(
      'WWW-Authenticate'=>"Basic realm = 'site_login'"
    ));
  }

});




#####################################################################
##  PUT /user/levels                                               ##
####  It will update the user level preferences                    ##
#####################################################################

$users->put('/levels/', function(Request $req) use($app) {

  // parse the request into associative array
  $levels = json_decode($req->getContent(), true);
  if (!isset($levels)) {
    throw new EdzooException("Incorrect JSON format", "INVALID_JSON", 400);
  }

  // get the username
  list($token) = sscanf($req->headers->get('Authorization'), 'token %s');
  $jwt = JWT::decode($token, $_SERVER['HTTP_USER_AGENT'], array('HS256'));
  $username = $jwt->data->user;



  //  update the database with transaction support
  try {
    // begin the transaction
    $app['pdo']->beginTransaction();
    // prepare the query
    $st = $app['pdo']->prepare("UPDATE user_info SET levels = :levels where username = :username");
    $params = array (
      ':levels' => $levels,
      ':username' => $username
    );
    // execute query
    $update_count = $st->execute($params);
    // commit this
    $app['pdo']->commit();

    // check if something was updated or not
    if($update_count > 0) {
      return $app->json(array (
        'status' => "LEVELS_UPDATED",
        'data' => "User education level preferences are updated"
      ), 200);
    } else {
      return $app->json(array (
        'status' => "INVALID_USER",
        'data' => "The user does not exist"
      ), 400, array(
        'WWW-Authenticate'=>"Basic realm = 'site_login'"
      ));
    }

  } catch (PDOException $e) {
    $app['pdo']->rollback();
    throw new PDOException($e->getMessage(), 500);
  }

});


#####################################################################
##  PUT /reset                                                     ##
####  It will reset the user password                              ##
#####################################################################

$users->put('/reset/',function(Request $req) use($app){

  //parse the request to get variables
  $data = json_decode($req->getContent(),true);
  if (!isset($data)) {
    throw new EdzooException("Incorrect JSON format", "INVALID_JSON", 400);
  }


  // get the username
  list($token) = sscanf($req->headers->get('Authorization'), 'token %s');
  $jwt = JWT::decode($token, $_SERVER['HTTP_USER_AGENT'], array('HS256'));
  $username = $jwt->data->user;

   // check if all the required fields are passed
  if(!isset($data['current_password']) || !isset($data['new_password'])) {
    throw new EdzooException("Incorrect information provided", "INVALID_REQUEST", 400);
  }

  //get the current password as typed by the user
  $c_password = $data['current_password'];

  $rt = $app['pdo']->prepare("SELECT password from appuser where username = '$username'");
  try {
    $rt->execute();
  } catch (Exception $e) {
    echo $e->getMessage();
    return $app->json(array (
        'status' => "INTERNAL_ERROR",
        'data' => "Internal Server Error"
      ), 503);
  }

  //get the password in row
  if($row=$rt->fetch(PDO::FETCH_ASSOC))
  {
      //check whether the current password is right
      if($c_password==$row['password'])
      {

          //  update the database with transaction support
          try {
              // begin the transaction
              $app['pdo']->beginTransaction();
              // prepare the query

              $st = $app['pdo']->prepare("UPDATE appuser SET password = :new_password where username = :username");
              $params = array (
                ':new_password' => $data['new_password'],
                ':username' => $username
              );
              // execute query
              $st->execute($params);
              // commit this
              $app['pdo']->commit();

              return $app->json(array (
                 'status' => "PASSWORD_UPDATED",
                 'data' => "Password is updated"
              ), 201);

          } catch (PDOException $e) {
             $app['pdo']->rollback();
             throw new PDOException($e->getMessage(), 500);
          }

      }else{
            return $app->json(array (
                'status' => "INVALID_PASSWORD",
                'data' => "Retype Current Password"
                ), 400);
        }
    }else {
          return $app->json(array (
            'status' => "INVALID_USER",
            'data' => "The user does not exist"
            ), 400, array(
            'WWW-Authenticate'=>"Basic realm = 'site_login'"
          ));
      }

});

$users->post('/idea/',function(Request $req) use($app){

  //parse the request to get variables
  $data = json_decode($req->getContent(),true);

  if (!isset($data)) {
    throw new EdzooException("Incorrect JSON format", "INVALID_JSON", 400);
  }


  //  insert into the database with transaction support
  try {
    // begin the transaction
    $app['pdo']->beginTransaction();
    // prepare the query
    $st = $app['pdo']->prepare("INSERT INTO idea_page(posted_by,first_name,last_name,age,gender,education,address,state,district,email,mobile,title,description) values(:posted_by,:first_name,:last_name,:age,:gender,:education,:address,:state,:district,:email,:mobile,:title,:description)");
    $params = array (
      ':posted_by' => $data['submitted_by'],
      ':first_name' => $data['first_name'],
      ':last_name'  => $data['last_name'],
      ':age' => $data['age'],
      ':gender' => $data['gender'],
      ':education' => $data['education'],
      ':address' => $data['address'],
      ':state' => $data['state'],
      ':district' => $data['district'],
      ':email' => $data['email'],
      ':mobile' => $data['mobile'],
      ':title' => $data['title'],
      ':description' => $data['description']
    );
    // execute query
    $st->execute($params);
    // commit this
    $app['pdo']->commit();

      return $app->json(array (
        'status' => "IDEA_SUBMITTED",
        'data' => "Successfully submitted the Idea"
      ), 200);

  } catch (PDOException $e) {
    $app['pdo']->rollback();
    throw new PDOException($e->getMessage(), 500);
  }

})->before($before);


// return the controllers factory
return $users;

?>
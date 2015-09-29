<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Edzoo\EdzooException;

// the controllers factory
$course_controller = $app['controllers_factory'];



#####################################################################
##  GET /course/{id}                                               ##
####  Route to return the courses info by id                       ##
#####################################################################

$course_controller->get('/{cid}',function (Request $req, $cid) use($app) {

  // get the username
  list($token) = sscanf($req->headers->get('Authorization'), 'token %s');
  $jwt = JWT::decode($token, $_SERVER['HTTP_USER_AGENT'], array('HS256'));
  $username = $jwt->data->user;

  // get the course id
  $cid = (int)$cid;

  // prepare the query
  $st = $app['pdo']->prepare("SELECT * FROM courses where id = :cid");
  // execute the query
  $st->execute(array(':cid' => $cid));

  // if the course is found, get the materials that belongs to the course
  $data = array();
  if($row = $st->fetch(PDO::FETCH_ASSOC)) {

    // put the course info
    $data = $row;

    // prepare the query
    $st = $app['pdo']->prepare("SELECT id, image, path, downloads, name FROM material where course_id = :cid");
    // execute the query
    $st->execute(array(':cid' => $cid));

    // get the materials for the row

    $temp_arr = array();
    $material_count = 0;
    // loop through the materials and count them as well
    while($row = $st->fetch(PDO::FETCH_ASSOC)) {
      array_push($temp_arr, $row);
      $material_count++;
    }
    $data['count'] = $material_count;
    $data['material'] = $temp_arr;

    // find the vote_value for this user as well
    // prepare the query
    $st = $app['pdo']->prepare("SELECT vote_value FROM votes where username = :username AND course_id = :id");
    $params = array (
      ":username" => $username,
      ":id" => $cid
    );
    // execute query
    $st->execute($params);

    // now if there is an entry for the user
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      $data['vote_value'] = $row['vote_value'];
    }

    // return the data
    return $app->json(array (
      'status' => 'COURSE_CREATED',
      'data' => $data
    ), 200);

  } else {
    return $app->json(array (
      'status' => "NO_COURSE",
      'data' => "The course in not found"
    ), 404);
  }

})->before($before);




#####################################################################
##  POST /course/                                                  ##
####  Route to upload a course                                     ##
#####################################################################

$course_controller->post('/', function (Request $req) use ($app) {

  // parse the request
  $course = json_decode($req->getContent(), true);

  //print_r($course);
  if (!isset($course)) {
    throw new EdzooException("Incorrect JSON format", "INVALID_JSON", 400);
  }

  // get the username
  list($token) = sscanf($req->headers->get('Authorization'), 'token %s');
  $jwt = JWT::decode($token, $_SERVER['HTTP_USER_AGENT'], array('HS256'));
  $username = $jwt->data->user;

  // check for the required fields
  if(!isset($course['name']) || !isset($course['type']) || !isset($course['description'])
    || !isset($course['keywords']) || !isset($course['material']) || !isset($course['level']) || !isset($course['lang'])) {
    throw new EdzooException("Parameter missing", "INVAID_REQUEST", 400);
  }
  $material = $course['material'];

  // check the number of elements inan array
  $array_count = count($material);


  // check for required fields in materials
  for($i=0;$i<$array_count;$i++){

  if(!isset($material[$i]['name']) || !isset($material[$i]['image']) || !isset($material[$i]['path'])) {
    throw new EdzooException("Parameter missing", "INVAID_REQUEST", 400);
    }
  }

  if($course['type']!='videos'){

    $tn=$app['pdo']->prepare("SELECT image FROM keywords WHERE id=:keyword");
    $tn->execute(array(':keyword'=>$course['keywords']));

    $nrow = $tn->fetch(PDO::FETCH_ASSOC);

    $nimage = $nrow['image'];

    if($course['image']==""){
          $image = $nimage;
    }else{
        $image = $course['image'];
    }
  }else{
    $image = $material[0]['image'];
  }

  // now finally upload everything to the db
  try {

    // begin the transactions
    $app['pdo']->beginTransaction();
    // prepare the query
    $st = $app['pdo']->prepare("INSERT INTO courses(name, type, description, keywords, username, image, level, lang)
                              values(:name, :type, :description, :keywords, :username, :image, :level, :lang)");
    $params = array (
      ':name' => $course['name'],
      ':type' => $course['type'],
      ':description' => $course['description'],
      ':keywords' => $course['keywords'],
      ':level' => $course['level'],
      ':lang' => $course['lang'],
      ':username' => $username,
      ':image' => $image
    );
    // execute the query
    $st->execute($params);
    // now get course id just uploaded
    $id = $app['pdo']->lastInsertId('courses_id_seq');

    // now finally put in the material in database
    for($i=0;$i<$array_count;$i++){
      // prepare the query
      
          $st = $app['pdo']->prepare("INSERT INTO material (name, path, image, course_id, username)
                                values(:name, :path, :image, :course_id, :username)");
          $params = array (
            ':name' => $material[$i]['name'],
            ':path' => $material[$i]['path'],
            ':image' => $material[$i]['image'],
            ':course_id' => $id,
            //':type' => $material['type'],
            ':username' => $username
          );
          // execute the query
          $st->execute($params);
    }
    // allright everything done now commit
    $app['pdo']->commit();
    // setup new success response and return it with the course id
    return $app->json(array (
        'status' => 'SUCCESS',
        'data' => $id
     ), 201);

  }catch (PDOException $e) {
      $app['pdo']->rollback();
      throw new PDOException($e->getMessage(), 500);
  }
  
  })->before($before);




#####################################################################
##  DELETE /course/{id}                                            ##
####  Delete the course that this user uploaded by id              ##
#####################################################################

$course_controller->delete('/{id}', function(Request $req, $id) use($app) {

  // get the username
  list($token) = sscanf($req->headers->get('Authorization'), 'token %s');
  $jwt = JWT::decode($token, $_SERVER['HTTP_USER_AGENT'], array('HS256'));
  $username = $jwt->data->user;

  // delete from the database
  try {

    // begin the transaction
    $app['pdo']->beginTransaction();

    // first delete the course
    // prepare the query
    $st = $app['pdo']->prepare("DELETE FROM courses WHERE username = :username AND id = :id");
    $params = array (
      ':username' => $username,
      ':id' => $id
    );
    // execute the query
    $st->execute($params);
    // get the number of rows deleted
    $update_count = $st->rowCount();

    // alright commit now
    $app['pdo']->commit();

    // if course was deleted
    if($update_count > 0) {
      return $app->json(array (
        'status' => "COURSE_DELETED",
        'data' => "The course and all associated materials are deleted"
      ), 200);
    } else {
      return $app->json(array (
        'status' => "INVALID_COURSE",
        'data' => "No such course found or you can't delete this course"
      ), 200);
    }

  } catch (PDOException $e) {
    $app['pdo']->rollback();
    throw new PDOException($e->getMessage(), 500);
  }

})->before($before);




#####################################################################
##  PUt /course/{id}                                               ##
####  Update the course uploaded by the user by id                 ##
#####################################################################

$course_controller->put('/{id}', function(Request $req, $id) use($app) {

  // parse the request
  $course = json_decode($req->getContent(), true);
  if (!isset($course)) {
    throw new EdzooException("Incorrect JSON format", "INVALID_JSON", 400);
  }

  // get the id
  $id = (int)$id;

  // get the username
  list($token) = sscanf($req->headers->get('Authorization'), 'token %s');
  $jwt = JWT::decode($token, $_SERVER['HTTP_USER_AGENT'], array('HS256'));
  $username = $jwt->data->user;

  // check for the required fields
  if(!isset($course['name']) || !isset($course['type']) || !isset($course['description'])
    || !isset($course['keywords'])) {
    throw new EdzooException("Parameter missing", "INVAID_REQUEST", 400);
  }

  // update the database
  try {

    // begin the transaction
    $app['pdo']->beginTransaction();
    // prepare the query
    $st = $app['pdo']->prepare("UPDATE courses SET name = :name, type = :type, description = :description, keywords = :keywords
                                WHERE username = :username AND id = :id");
    $params = array (
      ':name' => $course['name'],
      ':type' => $course['type'],
      ':description' => $course['description'],
      ':keywords' => json_encode($course['keywords']),
      ':username' => $username,
      ':id' => $id
    );
    // execute the query
    $st->execute($params);
    // get the number of updates
    $update_count = $st->rowCount();
    // alright commit now
    $app['pdo']->commit();

    // if course was updated
    if($update_count > 0) {
      return $app->json(array (
        'status' => "COURSE_UPDATED",
        'data' => "The course is updated"
      ), 200);
    } else {
      return $app->json(array (
        'status' => "INVALID_COURSE",
        'data' => "No such course found or you can't delete this course"
      ), 200);
    }

  } catch (PDOException $e) {
    $app['pdo']->rollback();
    throw new PDOException($e->getMessage(), 500);
  }

})->before($before);



#####################################################################
##  PUT course/downvote/{id}                                       ##
####  Increases the number of downvotes and add a record in votes  ##
#####################################################################

$course_controller->put('/downvote/{id}',function(Request $req, $id) use($app) {

  // get the username
  list($token) = sscanf($req->headers->get('Authorization'), 'token %s');
  $jwt = JWT::decode($token, $_SERVER['HTTP_USER_AGENT'], array('HS256'));
  $username = $jwt->data->user;

  // get the id
  $id = (int)$id;

  // Check if the user has already down voted before
  // prepare the query
  $st = $app['pdo']->prepare("SELECT vote_value FROM votes where username = :username AND course_id = :id");
  $params = array (
    ":username" => $username,
    ":id" => $id
  );
  // execute query
  $st->execute($params);

  try {

    // if there is an entry for this course and user
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      $value=$row['vote_value'];

      // if the user upvoted and now wants to downvote (UPVOTE -> DOWNVOTE)
      if($value) {

        // start the transactions
        $app['pdo']->beginTransaction();

        // update the votes table changing upvote into downvote
        // prepare the query
        $st = $app['pdo']->prepare("UPDATE votes SET vote_value = :vote_value WHERE username = :username AND course_id = :id");
        $params = array (
          ':username' => $username,
          ':id' => $id,
          ':vote_value' => 0
        );
        // execute query
        $st->execute($params);

        // now update the course table increasing downvotes and decresing upvotes
        // prepare the query
        $st = $app['pdo']->prepare("UPDATE courses SET upvotes = upvotes-1, downvotes = downvotes+1 where id = :id");
        // execute
        $st->execute(array(":id" => $id));

        // everything done commit
        $app['pdo']->commit();

        // return with the new data telling what to do with upvotes and downvotes
        $data  = array (
          "upvotes" => -1,
          "downvotes" => +1
        );
        return $app->json(array (
          'status' => "DOWNVOTED",
          'data' => $data
        ), 200);

      } else {
        // in the case the user already downvoted delete the vote_value row and reduce number of downvotes

        // start the transactions
        $app['pdo']->beginTransaction();

        // update the votes table by removing the row
        // prepare the query
        $st = $app['pdo']->prepare("DELETE FROM votes WHERE username = :username AND course_id = :id");
        $params = array (
          ':username' => $username,
          ':id' => $id
        );
        // execute query
        $st->execute($params);

        // now update the course table decreasing downvotes
        // prepare the query
        $st = $app['pdo']->prepare("UPDATE courses SET downvotes = downvotes-1 where id = :id");
        // execute
        $st->execute(array(":id" => $id));

        // everything done commit
        $app['pdo']->commit();

        // return with the new data telling what to do with upvotes and downvotes
        $data  = array (
          "upvotes" => 0,
          "downvotes" => -1
        );
        return $app->json(array (
          'status' => "DOWNVOTE_REMOVED",
          'data' => $data
        ), 200);
      }

    } else {
      // first time the user is downvoting this course

      // begin the transaction
      $app['pdo']->beginTransaction();

      //Insert a new row of user and downvote in vote_value
      // prepare
      $st = $app['pdo']->prepare("INSERT INTO votes (vote_value, username, course_id) VALUES (:vote_value, :username, :id)");
      $params = array (
          ":username" => $username,
          ":id" => $id,
          ":vote_value" => 0
      );
      // execute query
      $st->execute($params);

      // now update the courses table increasing downvotes
      // prepare
      $st = $app['pdo']->prepare("UPDATE courses SET downvotes = downvotes+1 where id = :id");
      // execute
      $st->execute(array(":id" => $id));
      // get the number of updates
      $update_count = $st->rowCount();

      // everything done commit
      $app['pdo']->commit();

      // return with the new data telling what to do with upvotes and downvotes
      if($update_count > 0) {
        $data   = array (
          "upvotes" => 0,
          "downvotes" => +1
        );
        return $app->json(array (
          'status' => "DOWNVOTED",
          'data' => $data
        ), 200);
      } else {
        return $app->json(array (
          'status' => "INVALID_COURSE",
          'data' => "No such course found or you can't delete this course"
        ), 200);
      }

    }

  } catch (PDOException $e) {
    echo $e->getLine();
    $app['pdo']->rollback();
    throw new PDOException($e->getMessage(), 500);
  }

})->before($before);




#####################################################################
##  PUT course/upvote/{id}                                         ##
####  Increases the number of upvotes (and add a record in votes)  ##
#####################################################################

$course_controller->put('/upvote/{id}',function(Request $req, $id) use($app) {

  // get the username
  list($token) = sscanf($req->headers->get('Authorization'), 'token %s');
  $jwt = JWT::decode($token, $_SERVER['HTTP_USER_AGENT'], array('HS256'));
  $username = $jwt->data->user;

  // get the id
  $id = (int)$id;

  // Check if the user has already down voted before
  // prepare the query
  $st = $app['pdo']->prepare("SELECT vote_value FROM votes where username = :username AND course_id = :id");
  $params = array (
    ":username" => $username,
    ":id" => $id
  );
  // execute query
  $st->execute($params);

  try {

    // if there is an entry for this course and user
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      $value=$row['vote_value'];

      // if the user downvoted and now wants to upnvote (UPVOTE -> DOWNVOTE)
      if(!$value) {

        // start the transactions
        $app['pdo']->beginTransaction();

        // update the votes table changing upvote into downvote
        // prepare the query
        $st = $app['pdo']->prepare("UPDATE votes SET vote_value = :vote_value WHERE username = :username AND course_id = :id");
        $params = array (
          ':username' => $username,
          ':id' => $id,
          ':vote_value' => 1
        );
        // execute query
        $st->execute($params);

        // now update the course table increasing downvotes and decresing upvotes
        // prepare the query
        $st = $app['pdo']->prepare("UPDATE courses SET upvotes = upvotes+1, downvotes = downvotes-1 where id = :id");
        // execute
        $st->execute(array(":id" => $id));

        // everything done commit
        $app['pdo']->commit();

        // return with the new data telling what to do with upvotes and downvotes
        $data  = array (
          "upvotes" => +1,
          "downvotes" => -1
        );
        return $app->json(array (
          'status' => "UPVOTED",
          'data' => $data
        ), 200);

      } else {

        //in case user wants to revert the upvote

        // start the transactions
        $app['pdo']->beginTransaction();

        // update the votes table by removing the row
        // prepare the query
        $st = $app['pdo']->prepare("DELETE FROM votes WHERE username = :username AND course_id = :id");
        $params = array (
          ':username' => $username,
          ':id' => $id
        );
        // execute query
        $st->execute($params);

        // now update the course table  decresing upvotes
        // prepare the query
        $st = $app['pdo']->prepare("UPDATE courses SET upvotes = upvotes-1 where id = :id");
        // execute
        $st->execute(array(":id" => $id));

        // everything done commit
        $app['pdo']->commit();

        // return with the new data telling what to do with upvotes and downvotes
        $data  = array (
          "upvotes" => -1,
          "downvotes" => 0
        );
        return $app->json(array (
          'status' => "UPVOTE_REMOVED",
          'data' => $data
        ), 200);
      }

    } else {
      // first time the user is upvoting this course

      // begin the transaction
      $app['pdo']->beginTransaction();

      //Insert a new row of user and upvote in vote_value
      // prepare
      $st = $app['pdo']->prepare("INSERT INTO votes (vote_value, username, course_id) VALUES (:vote_value, :username, :id)");
      $params = array (
          ":username" => $username,
          ":id" => $id,
          ":vote_value" => 1
      );
      // execute query
      $st->execute($params);

      // now update the courses table increasing upvotes
      // prepare
      $st = $app['pdo']->prepare("UPDATE courses SET upvotes = upvotes+1 where id = :id");
      // execute
      $st->execute(array(":id" => $id));
      // get the number of updates
      $update_count = $st->rowCount();

      // everything done commit
      $app['pdo']->commit();

      // return with the new data telling what to do with upvotes and downvotes
      if($update_count > 0) {
        $data   = array (
          "upvotes" => 1,
          "downvotes" => 0
        );
        return $app->json(array (
          'status' => "UPVOTED",
          'data' => $data
        ), 200);
      } else {
        return $app->json(array (
          'status' => "INVALID_COURSE",
          'data' => "No such course found or you can't delete this course"
        ), 200);
      }

    }

  } catch (PDOException $e) {
    echo $e->getLine();
    $app['pdo']->rollback();
    throw new PDOException($e->getMessage(), 500);
  }

})->before($before);



// return the controller
return $course_controller;

?>
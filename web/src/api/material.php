<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Edzoo\EdzooException;
use Edzoo\EdzooS3;

$material_controller = $app['controllers_factory'];


#####################################################################
##  PUT /material/                                                ##
####  Route to upload a material                                   ##
#####################################################################

$material_controller->put('/', function(Request $req) use($app) {

  // parse the request
  $material = json_decode($req->getContent(), true);
  if (!isset($material)) {
    throw new EdzooException("Incorrect JSON format", "INVALID_JSON", 400);
  }

  // check for required fields in materials
  if(!isset($material['name']) || !isset($material['image']) || !isset($material['path'])  || !isset($material['course_id'])) {
    throw new EdzooException("Parameter missing", "INVAID_REQUEST", 400);
  }

  // get the username form token
  list($token) = sscanf($req->headers->get('Authorization'), 'token %s');
  $jwt = JWT::decode($token, $_SERVER['HTTP_USER_AGENT'], array('HS256'));
  $username = $jwt->data->user;

  // get the username from courses table by using course_id
  // prepare the query
  $st = $app['pdo']->prepare("SELECT username from courses where id = :id");
  // exceute the query
  $st->execute(array(':id' => $material['course_id']));

  // if there is a result
  if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    // if the two usernames do not match throw up
    if ($row['username'] !== $username) {
      throw new EdzooException("You cannot add material to this course", "INVALID_REQUEST", 400);
    }
  } else {
    // there is no course with that id, throw up
    throw new EdzooException("No such course", "INVALID_COURSE", 400);
  }

  // now finally upload everything to the db
  try {

    // begin the transactions
    $app['pdo']->beginTransaction();
    // prepare the query
    $st = $app['pdo']->prepare("INSERT INTO material (name, path, image, course_id,username) values(:name, :path, :image,:course_id, :username)");
    $params = array (
      ':name' => $material['name'],
      ':path' => $material['path'],
      ':image' => $material['image'],
      ':course_id' => $material['course_id'],
      ':username' => $username
    );
    // execute the query
    $st->execute($params);
    // allright everything done now commit
    $app['pdo']->commit();

    return $app->json(array (
      'status' => "MATERIAL_CREATED",
      'data' => $material['course_id']
    ), 201);

  } catch (PDOException $e) {
    $app['pdo']->rollback();
    throw new PDOException($e->getMessage(), 500);
  }

})->before($before);




#####################################################################
##  GET /material/{id}                                             ##
####  Increase the download count of the material                  ##
#####################################################################

$material_controller->get('/{id}', function($id) use($app) {

  // get the id
  $id = (int)$id;

  // update the download count with transaction support
  try {

    // begin the transaction
    $app['pdo']->beginTransaction();
    // prepare the query
    $st = $app['pdo']->prepare("UPDATE material SET downloads = downloads+1 where id = :id");
    // execute
    $st->execute(array(":id" => $id));
    // get the number of row updates
    $update_count = $st->rowCount();
    // everything alright commit
    $app['pdo']->commit();

    // check if something was updated or not
    if($update_count > 0) {
      return $app->json(array (
        'status' => "MATERIAL_UPDATED",
        'data' => "The download count is updated"
      ), 200);
    } else {
      return $app->json(array (
        'status' => "INVALID_MATERIAL",
        'data' => "No such material found"
      ), 200);
    }

  } catch (PDOException $e) {
    $app['pdo']->rollback();
    throw new PDOException($e->getMessage(), 500);
  }

})->before($before);


#####################################################################
##  DELETE /material/{id}                                          ##
####  Delete the material by id                                    ##
#####################################################################

$material_controller->delete('/{id}', function(Request $req, $id) use($app) {

  // get the id in integer
  $id = (int)$id;

  // get the username
  list($token) = sscanf($req->headers->get('Authorization'), 'token %s');
  $jwt = JWT::decode($token, $_SERVER['HTTP_USER_AGENT'], array('HS256'));
  $username = $jwt->data->user;

  // delete material
  try {

    // begin the transaction
    $app['pdo']->beginTransaction();
    // prepare the query
    $st= $app['pdo']->query("DELETE FROM material WHERE id = $id and username = '$username' RETURNING *");
    # get the number of rows deleted
    $update_count = $st->rowCount();

    // check if something was deleted or not
    if($update_count > 0) {

      # delete from amazon s3
      try {

        # create a s3 client
        $s3 = new EdzooS3();

        # get the elements to delete
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $image = $row['image'];
        $path = $row['path'];

        # delete the pdf
        $s3->delete('edzoo', 'uploads', $path);

        # delete the thumb
        $s3->delete('edzoo', 'uploads', $image);

        # alright now commit
        $app['pdo']->commit();

        # send a success response
        return $app->json(array (
          'status' => "MATERIAL_DELETED",
          'data' => $row
        ), 200);

      } catch (Exception $e) {

        # rollback
        $app['pdo']->rollback();

        echo $e->getMessage();
        # send a response that says could not delete
        return $app->json(array (
          'status' => "DELETE ERRROR",
          'data' => "Unable to delete the material"
        ), 200);

      }

    } else {

      # there was no such material send a response saying the same
      return $app->json(array (
        'status' => "INVALID_MATERIAL",
        'data' => "Either no such material exist or you are not allowed to delete it"
      ), 200);

    }

  } catch (PDOException $e) {

    # rollback
    $app['pdo']->rollback();
    throw new PDOException($e->getMessage(), 500);

  } catch (EdzooException $e) {

    #rollback
    $app['pdo']->rollback();
    throw new EdzooException($e->getMessage(), $e->getStatus(), $e->getCode());

  } catch (Exception $e) {

    # rollback and throw back
    $app['pdo']->rollback();
    throw new Exception($e->getMessage(), 500);

  }

})->before($before);

// return controller
return $material_controller;

?>
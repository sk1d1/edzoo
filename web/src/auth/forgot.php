<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Edzoo\EdzooException;

// the controllers factory
$forgot = $app['controllers_factory'];

#####################################################################
##  Forgot /forgot/                                                ##
####  It will reset the user password                              ##
#####################################################################



$forgot->post('/',function(Request $req) use($app){
  //parse the request to get variables
  $user = json_decode($req->getContent(),true);

  $res = array();
  $res['status'] = 'INTERNAL_ERROR';
  $res['data'] = 'Internal Server Error';

  if(!isset($user['username'])){
    $res['status'] = 'ERROR';
    $res['data'] = "User not provided";
    return $app->json($res, 204);
  }

  $username = $user['username'];

  $query = "SELECT username,email FROM appuser where username='$username'";
  $st = $app['pdo']->prepare($query);
  try {
    $st->execute();
  } catch (Exception $e) {
    echo $e->getMessage();
    return $app->json($res, 503);
  }

  if($row=$st->fetch(PDO::FETCH_ASSOC)){

      $username=$row['username'];
      $email=$row['email'];
      $length=mt_rand(8,10);
      $newpassword='';

      //Generate a random new password
      $validCharacters = "abcdefghijklmnopqrstuxyvwz1234567890";
      $validCharNumber = strlen($validCharacters);
      for ($i = 0; $i < $length; $i++) {
        $index = mt_rand(0, $validCharNumber-1);
        $newpassword .= $validCharacters[$index];
      }



       //convert password into raw MD5 hash
      $hash_pass = hash('sha256',$newpassword);

      try{

          $app['pdo']->beginTransaction();
           
          $query = "UPDATE appuser SET password=:newpassword where username=:username";
       
          $st = $app['pdo']->prepare($query);
          
          $st->execute(array(':newpassword' => $hash_pass,':username' => $username));

          $app['pdo']->commit();
   
      }catch (PDOException $e) {
       $app['pdo']->rollback();
       throw new PDOException($e->getMessage(), 500);
     }

      $body="Hello user! You have requested a password reset\nIf it was not you please email at sbadyals@gmail.com\n\n";

      $message = \Swift_Message::newInstance()
        ->setSubject('Password Reset for Edzoo')
        ->setFrom(array('sbadyals@gmail.com'))
        ->setTo($email)
        ->setBody($body.'New password to login is : '.$newpassword);

      try{
          $app['mailer']->send($message);
        }catch(Exception $e){
         echo $e->getMessage();

          return $app->json($res,503);
        }


      $res['status'] = 'SUCCESSFUL';
      $res['data'] = "Please check your email";

      return $app->json($res, 201);
    }

    else{
     $res['status'] = 'ERROR';
    $res['data'] = "User does not exist";

    return $app->json($res,403);
   }

});


/* ----------------------------------------------------------------------------
 *  post : /again/
 *  Sends the new password again
 *  The username
 *  Success if the password is send again
 *  There is a problem in this route regarding MD5 hashing
----------------------------------------------------------------------------
$forgot->post('/again/', function(Request $req) use($app) {
  //parse the request to get variables
  $user = json_decode($req->getContent(),true);

  $res = array();
  $res['status'] = 'INTERNAL_ERROR';
  $res['data'] = 'Internal Server Error';

  if(!isset($user['username'])){
    $res['status'] = 'ERROR';
    $res['data'] = "User not provided";
    return $app->json($res, 204);
  }

  $username = $user['username'];

  # get the password of the user
  $query = "SELECT * FROM appuser where username='$username'";
  $st = $app['pdo']->prepare($query);
  try {
    $st->execute();
  } catch (Exception $e) {
    echo $e->getMessage();
    return $app->json($res, 503);
  }

  # retrieve the password
  if ($row=$st->fetch(PDO::FETCH_ASSOC)) {

      $username=$row['username'];
      $email=$row['email'];
      $newpassword=$row['password'];

      $body="Hello user! You have requested a password reset\nIf it was not you please email at sbadyals@gmail.com\n\n";

      $message = \Swift_Message::newInstance()
        ->setSubject('Password Reset for Edzoo')
        ->setFrom(array('sbadyals@gmail.com'))
        ->setTo($email)
        ->setBody($body.'New password to login is : '.$newpassword);

      try {
        $app['mailer']->send($message);
      } catch(Exception $e){
        echo $e->getMessage();
        return $app->json($res,503);
      }

      # send the response for success
      $res['status'] = 'SUCCESSFUL';
      $res['data'] = "Please check email";
      return $app->json($res, 201);

    } else{
      # send the error response
      $res['status'] = 'ERROR';
      $res['data'] = "User does not exist";
      return $app->json($res,403);
   }

});
*/
// return the controllers factory
return $forgot;

?>
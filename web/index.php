<?php

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Silex\Application;
use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\Debug\ExceptionHandler;
use Edzoo\EdzooException;


$loader = require_once(__DIR__.'/../vendor/autoload.php');
# register the Edzoo namespace
$loader->add('Edzoo', __DIR__.'/');

# get the app
$app = new Silex\Application();
$app['debug'] = true;


# Catch all the error and exceptions
ErrorHandler::register();
ExceptionHandler::register();

# set up an error handler for custom exception
$app->error(function(EdzooException $e, $code) use($app) {
  return $app->json(array(
    'status' => $e->getStatus(),
    'data' => $e->getMessage()
  ), $e->getCode());
});


# set up an error handler for PDOException ie db realted ones
$app->error(function(\PDOException $e, $code) use($app) {
  return $app->json(array(
    'status' => 'DB_ERROR',
    'data' => $e->getMessage()
  ), $code);
});


# set up an error handler for any other exception
$app->error(function(\Exception $e, $code) use($app) {
  return $app->json(array(
    'status' => $e->getCode(),
    'data' => $e->getMessage()
  ), $code);
});



// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
  'monolog.logfile' => 'php://stderr',
));


// register for accessing the database
 //$dbopts = parse_url('postgres://sbad:123edsaqw@localhost:5432/edzoodb');
// change this before commiting to heroku
$dbopts = parse_url(getenv('DATABASE_URL'));


$app->register(new Herrera\Pdo\PdoServiceProvider(),
array(
    'pdo.dsn' => 'pgsql:dbname='.ltrim($dbopts["path"],'/').';host='.$dbopts["host"],
    'pdo.port' => $dbopts["port"],
    'pdo.username' => $dbopts["user"],
    'pdo.password' => $dbopts["pass"]
    )
);

// Register for swift mailer service

$app->register(new Silex\Provider\SwiftmailerServiceProvider());

 $app['swiftmailer.options'] = array(
  'host' => 'smtp.mandrillapp.com',
  'port' => '587',
  'username' => 'innovationecosystem.nith@gmail.com',
  'password' => 'F5NXAYT0tDMq7JkRtiU-2g',
  'encryption' => 'tls'
);


# set the database connection to use exception so that any problem with the database is
# turned into an exception
$app['pdo']->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


# a temporary solution for entry point authenticaton
$before = function(Request $req, Silex\Application $app) {

  # the array that will be finally passes in json form
  # the staus values are "INTERNAL_ERROR, ERROR, EXISTS, SUCCESS"
  $res = array();
  $res['status'] = 'INTERNAL_ERROR';
  $res['data'] = 'Internal Server Error';

  # checking if the authentication header is there or not
  if (!$req->headers->has('Authorization')) {

    $res['status'] = "UNAUTHORIZED";
    $res['data'] = 'No Authorization header found';
    return $app->json($res, 401,array(
      'WWW-Authenticate'=>"Basic realm = 'site_login'"
    ));

  } else {

    list($token) = sscanf($req->headers->get('Authorization'), 'token %s');
    try {
      $jwt = JWT::decode($token, $_SERVER['HTTP_USER_AGENT'], array('HS256'));
    } catch(Exception $e) {
      echo $e->getMessage();
      $res['status'] = "UNAUTHORIZED";
      $res['data'] = 'Invalid token';
      return $app->json($res, 401,array(
        'WWW-Authenticate'=>"Basic realm = 'site_login'"
      ));
    }

  }

};



/* ----------------------------------------------------------------------------
 *  get : /
 *  The route to ckeck the connection
 *  requitres : nothing
 *  returns : String connected with reponse code 200
---------------------------------------------------------------------------- */
$app->get('/', function() use($app) {
  return new Response('Connected',200);
});


# routes related to authorization and authenticatoin
$app->mount('/register', include __DIR__.'/src/auth/register.php');
$app->mount('/login', include __DIR__.'/src/auth/login.php');
$app->mount('/forgot', include __DIR__.'/src/auth/forgot.php');

# user based rotues
$app->mount('/user', include __DIR__.'/src/user/user.php');

# routes for keywords access
$app->mount('/keywords', include __DIR__.'/src/keywords/keywords.php');

# the main api to handle content
$app->mount('/course', include __DIR__.'/src/api/course.php');
$app->mount('/material', include __DIR__.'/src/api/material.php');

# controller for searching api
$app->mount('/search', include __DIR__.'/src/search/search.php');


# run the app and wait ...
$app->run();
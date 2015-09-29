<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// the controller
$keywords_controller = $app['controllers_factory'];



##########################################
####        GET keywords/             ####
##########################################

$keywords_controller->get('/', function() use($app) {

  $query = 'SELECT id, ';

  $lang = $app['request']->get('lang');
  if (isset($lang)) {
    $query .= $lang . ' ';
  } else {
    $query .= '* ';
  }

  // prepare the query
  $st = $app['pdo']->prepare($query . ' FROM keywords order by id');
  // execute the query
  $st->execute();
  // fetch the results
  $temp_array = array();
  $i = 0;
  while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    array_push($temp_array, $row);
    $i++;
  }

  // if we were able to fetch the keywords
  if ($i > 0) {
    return $app->json(array (
      'status' => "SUCCESS",
      'data' => $temp_array
    ), 200,array(
    'Cache-Control' => 'max-age=1814400'
  ));
  } else {
    return $app->json(array (
      'status' => "INTERNAL_ERROR",
      'data' => "Could not fetch the keywords"
    ), 500);
  }

});

// return the controller
return $keywords_controller;

?>
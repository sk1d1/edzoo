<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Edzoo\EdzooException;

// the controller factory
$search_controller = $app['controllers_factory'];


#####################################################################
##  GET /search/                                                   ##
####  Route to return course based of search parameters            ##
#####################################################################

$search_controller->get('/{name}/{offset}', function(Request $req,$name,$offset) use($app) {


  // get the name to search from the query string
  //$name = $app['request']->get('name');

  // array the keeps the binding parameters
  $parameters = array();
  // the query
  $query = "SELECT * from courses where name ilike ";

  // if there was no name in the query
  if (!isset($name)) {
    throw new EdzooException("Incorrect request", "NO_NAME", 400);
  }

  // if the name is set then lets add it to the query
  // first decode for any spaces
  //  $name = urldecode($name);
  // now slice that into array at spaces and commas
  $name_array = preg_split("/[\s,]+/", $name);

  // if there are words in the arrys
  if (count($name_array) > 0) {
    // for each word
    $names = "%".implode('%', $name_array)."%";
    $query .= ':names';
    $parameters[':names'] = $names;
  }

  // check if there are filters
  // check for type filter
  $type = $app['request']->get('type');
  if (isset($type)) {
    $query .= ' and type = :type';
    $parameters[':type'] = $type;
  }

  // check for keywords
  // Keywords list
  $keywords = $app['request']->get('keywords');
  if (isset($keywords)) {
    $query .= " and keywords in ($keywords)";
  }

  $query .= " LIMIT 10 OFFSET $offset";

  // check for level
  $levels = $app['request']->get('levels');
  if (isset($levels)) {
    $query .= ' and levels in ($levels)';
  }

  // prepare the query
  $st = $app['pdo']->prepare($query);
  // execute the query
  $st->execute($parameters);

  // check if there were results
  $temp_array = array();
  while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    array_push($temp_array, $row);
  }

  // return the result
  return $app->json(array (
    'status' => "SEARCH_RESULT",
    'data' => $temp_array
  ), 200);

});


// return the controller factory
return $search_controller;

?>
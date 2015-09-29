<?php

namespace Edzoo;

// Class that acts as
class EdzooException extends \Exception {

  // the status property
  protected $status;

  // a new constructor
  public function __construct($message = "Something wrong happened", $status = "INTERNAL_ERROR", $code = 0) {
    parent::__construct($message, $code);
    $this->status = $status;
  }

  // getter for status
  public function getStatus() {
    return $this->status;
  }

}


?>
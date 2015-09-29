<?php

namespace Edzoo;

// Class definition
class Uploader {

  // the class properties
  protected $destination;
  protected $max = 51200;
  protected $messages = [];
  protected $permitted = [
    'image/gif',
    'image/jpeg',
    'application/json'
  ];

  // constructor
  public function __construct($path) {
    // if the path is available and accessible
    if (!is_dir($path) || !is_writable($path)) {
      throw new \Exception("$path must be a valid and writeable directory");
    }
    $this->destination = $path;
  }

  // the upload function that does the job of uploading the file after checking it properly
  public function upload($file) {
    // first check the file then move it
    if ($this->checkFile($file)) {

      $this->moveFile($file);
    }
  }

  // the function that checks the file
  protected function checkFile($file) {
    $alright = true;
    // check the error level
    if ($file->getError() != 0) {
      $this->getErrorMessage($file);
      // no need to check further if no file is uploaded
      if ($file->getError() == 4) {
        return false;
      } else {
        $alright = false;
      }
    }
    // check for file size
    if (!$this->checkSize($file)) {
      $alright = false;
    }
    // check for mime type
    if (!$this->checkType($file)) {
      $alright = false;
    }

    // return the flag
    return $alright;
  }

  // the function that gets the message if there is an error uploading
  protected function getErrorMessage($file) {
    // use a switch
    switch ($file->getError()) {
      case 1 :
      case 2 :
        $this->messages[] = $file->getClientOriginalName() . ' is too big, maximum allowed size is ' . $this->getMaxSize();
        break;

      case 3 :
        $this->messages[] = $file->getClientOriginalName() . ' was paritially uploaded';
        break;

      case 4 :
        $this->messages[] = "No file was uploaded";
        break;

      default:
        $this->messages[] = "Sorry there was a problem uploading";
        break;
    }
  }

  // function to check the size of the upload and put the message
  protected function checkSize($file) {
    // checking the error code
    if ($file->getError() == 1 || $file->getError() == 2) {
      return false;
    } else if ($file->getClientSize() == 0) {
      $this->messages[] = $file->getClientOriginalName() . ' is empty';
      return false;
    } else if ($file->getClientSize() > $this->max) {
      $this->messages[] = $file->getClientOriginalName() . ' is too big, maximum allowed size is ' . $this->getMaxSize();
      return false;
    } else {
      return true;
    }

  }

  // the function to check the mime type
  protected function checkType($file) {
    // check if the mime type is in the permitted array
    if (in_array($file->getClientMimeType(), $this->permitted)) {
      return true;
    } else {
      $this->messages[] = $file->getClientOriginalName() . ' is not allowed';
      return false;
    }
  }

  // the function to print the maximum file size in KB
  public function getMaxSize() {
    return number_format($this->max/1024, 1) . 'KB';
  }

  // the function that moves the file to the destination directory
  protected function moveFile($file) {
    $new_file = $file->move($this->destination, $file->getClientOriginalName());
    // checks if the file was uploaded successfully
    if (isset($new_file)) {
      $this->messages[] = "{$file->getClientOriginalName()} was uploaded successfully";
    } else {
      $this->messages[] = "{$file->getClientOriginalName()} could not be uploaded";
    }
  }

  // return the message array
  public function getMessage() {
    return $this->messages;
  }



}


?>
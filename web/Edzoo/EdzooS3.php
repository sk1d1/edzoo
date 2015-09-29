<?php
/* ----------------------------------------------------------------------------
 *  NameSpace : Edzoo
 *  Class     : EdzooS3
 *  Upload content to Amazon S3 using sdk
---------------------------------------------------------------------------- */

namespace Edzoo;
$loader = require __DIR__.'/../../vendor/autoload.php';
use Aws\S3\S3Client;
use Edzoo\EdzooException;

class EdzooS3 {

  # protected variables
  protected $client;   # handler to aws s3 client

  /* -----------------------------------------------------------------------
   *  <start> constructor </start>
   *  Creates the s3 client
  ----------------------------------------------------------------------- */
  public function __construct() {
    try {
      $this->client = S3Client::factory(array(
        'region'   => 'ap-southeast-1',
        'version'  => 'latest',
        'credentials' => [
          'key'    => 'AKIAI3IVWUJ6PB7RGNZQ',
          'secret' => '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',
        ]
      ));
    } catch (Exception $e) {
      throw new EdzooException("Unable to connect to S3", "S3_ERROR", 500);
    }
  }
  /* -----------------------------------------------------------------------
   *  <end> constructor </end>
   *  <start> upload </start>
   *  This function uploads the file to s3
   *  @ bucket : the name of the bucket
   *  @ folder : folder name inside the bucket, please do not use trailing spaces
   *  @ key : the key to hold the element, generally the name of the file
  ----------------------------------------------------------------------- */
  public function upload ($bucket, $folder, $key, $target_file) {
    try {
      $result = $this->client->putObject([
        'Bucket'     => $bucket,
        'Key'        => $folder . '/' . $key,
        'SourceFile' => $target_file,
        'Metadata'   => array(
        )
      ]);
    } catch (Exception $e) {
      throw new EdzooException("There was an error uploading to S3 -> $e->getMessage()", 'S3_ERROR', 500);
    }
  }
  /* -----------------------------------------------------------------------
   *  <end> upload </end>
   *  <start> delete </end>
   *  Delete files from the s3 datastore
   *  @ bucket : the name of the bucket
   *  @ folder : folder name inside the bucket
   *  @ key : the key of the element to delete
  ----------------------------------------------------------------------- */
  public function delete ($bucket, $folder, $key) {
    try {
      $result = $this->client->deleteObject([
        'Bucket' => $bucket,
        'Key' => $folder . '/' . $key
      ]);
    } catch (Exception $e) {
      throw new EdzooException("There was an error deleting from s3 -> $e->getMessage()", 'S3_ERROR', 500);
    }
  }
  /* -----------------------------------------------------------------------
   *  <end> delete </end>
  ------------------------------------------------------------------------ */

}

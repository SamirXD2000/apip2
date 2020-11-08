<?php

namespace App\CustomClass;

use DB;

use App\Models\UserPersonalDocuments;

use Illuminate\Http\Request;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class s3Aws
{

  private $s3;

  public function __construct()
    {
    // Connect to AWS
    try {                       
      $this->s3 = new S3Client([
          'version' => 'latest',
          'region'  =>  env('AWS_REGION'),
          'credentials' => [
            'key'    => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY')
          ]
      ]);
     
    } catch (Exception $e) {
      return false;
    }

  }

  public function checkTypeFile($fileType,$userId){

    $sessionGet = DB::table('user_personal_documents')->where( array('user_id' => $userId , 'file_type'=> $fileType))->first();

    if ($sessionGet){
      return false;
    }

    return true;
  }

  public function generateKeyName($fileName,$userId){

    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
    $uniqId = uniqid("",false);

    $array["keyName"] = env('AWS_USERS_DOCUMENTS_ROUTE')."/".$userId."/".$uniqId.".".$ext;
    $array["keyNameWithoutExtension"] = env('AWS_USERS_DOCUMENTS_ROUTE')."/".$userId."/".$uniqId;
    $array["fileExtension"] = $ext;

    return $array;

  }

  public function uploadFile($key,$file)
  {
    //Upload File
    try {
        $this->s3->putObject([
          'Bucket' => env('AWS_BUCKET_NAME'),
          'Key'    => $key,
          'SourceFile'   => $file
        ]);
      return true;
    } catch (S3Exception $e) {
      //$this->error = $e->getAwsErrorMessage();
      //$this->error_code = $e->getAwsErrorCode();
      return false;
    } catch (Exception $e) {
      return false;
      //$this->error = $e->getMessage();
      //$this->error_code = $e->getCode();
    }

  }

  public function uploadPersonalFile($fileName,$userId,$fileType,$file){

    $fileNames = $this->generateKeyName($fileName,$userId);
    $connect = $this->uploadFile($fileNames["keyName"],$file);

    if ($connect){

        //REGISTRO ARCHIVO EN BASE DE DATOS
        $newSession = new UserPersonalDocuments;
        $newSession->user_id = $userId;
        $newSession->file_type = $fileType;
        $newSession->file_extension = $fileNames["fileExtension"];
        $newSession->key_name = $fileNames["keyName"];
        $newSession->key_name_without_extension = $fileNames["keyNameWithoutExtension"];
        $newSession->original_file_name = $fileName;
        $newSession->save();

        if (!$newSession){
          return false;
        }

        return true;
    } else {
        return false;
    }
  }

}
<?php

require_once __DIR__ . '/src/DPDAuthorisation.php';
require_once __DIR__ . '/src/DPDParcelStatus.php';

use MCS\DPDAuthorisation;
use MCS\DPDParcelStatus;

try{

    // Authorize
    // Be aware that this functionality doesn't work with test credentials
  $authorisation = new DPDAuthorisation([
    'staging' => false,
    'delisId' => '<delisId>',
    'password' => '<password>',
    'messageLanguage' => 'en_EN',
    'customerNumber' => '<customerNumber>'
  ]);
  
    // Init
  $status = new DPDParcelStatus($authorisation);

  // Retrieve the parcel's LabelNumber by it's web number
  $parcelLabelNumber = $status->getParcelLabelNumberByWebNumber('W01234567');
  
  // Retrieve the parcel's status by it's awb number
  $parcelStatus = $status->getStatus($parcelLabelNumber);

  echo '<pre>';
  print_r($parcelStatus);
  echo '</pre>';

}catch(Exception $e){
  echo $e->getMessage();		
}





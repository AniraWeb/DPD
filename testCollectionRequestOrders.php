<?php 

require_once __DIR__ . '/src/DPDAuthorisation.php';
require_once __DIR__ . '/src/DPDCollectionRequestOrders.php';
    
use MCS\DPDAuthorisation;
use MCS\DPDCollectionRequestOrders;

try{
    
    // Second parameter to disable the wsdl cache defaults to true
  $authorisation = new DPDAuthorisation([
    'staging' => false,
    'delisId' => '<delisId>',
    'password' => '<password>',
    'messageLanguage' => 'en_EN',
    'customerNumber' => '<customerNumber>'
  ]);
    
    // Second parameter to disable the wsdl cache defaults to true
    // $authorisation = new DPDAuthorisation($dpd, false);
    
    // Init the shipment with authorisation
    $shipment = new DPDCollectionRequestOrders($authorisation);
    
    // Second parameter to disable the wsdl cache defaults to true
    // $shipment = new DPDShipment($authorisation, false);

    // Set the language for the track&trace link
    $shipment->setTrackingLanguage('en_EN');

    // Enable saturday delivery
    $shipment->setSaturdayDelivery(true);   

    // Enable DPD B2C delivery method

    $shipment->setPredict([
        'channel' => 'email',
        'value' => 'someone@mail.com',
        'language' => 'EN'
    ]);
   

    // Set the general shipmentdata
    $shipment->setGeneralShipmentData([
        'product' => 'CL',
        'mpsCustomerReferenceNumber1' => 'reffer 12345'
    ]);

    // Set the printer options
    $shipment->setPrintOptions([
        'printerLanguage' => 'PDF',
        'paperFormat' => 'A6',
    ]);     

    // Set the sender's address
    $shipment->setSender([
        'name1' => 'Your Company',
        'street' => 'Street 12',
        'country' => 'NL',
        'zipCode' => '1234AB',
        'city' => 'Amsterdam',
        'email' => 'contact@yourcompany.com',
        'phone' => '1234567645'
    ]);

    // Set the receiver's address
    $shipment->setReceiver([
        'name1' => 'Joh Doe',         
        'name2' => null,       
        'street' => 'Street',       
        'houseNo' => '12',    
        'zipCode' => '1234AB',     
        'city' => 'Amsterdam',        
        'country' => 'NL',           
        'contact' => null,        
        'phone' => null,                 
        'email' => null,             
        'comment' => null 
    ]);

    // Add as many parcels as you want
    $shipment->addParcel([
        'weight' => 10000, // In gram
        'height' => 10, // In centimeters
        'width' => 20,
        'length' => 30,
        'returns' => false
    ]);
    
    $shipment->setPickup([
      'orderType' => 'collection request order',
      'pickup' => [
        'tour' => 1,
        'quantity' => 1, 
        'date' => date('Ymd',strtotime('+1day')), 
        'day' => date('w',strtotime('+1day')),
        'fromTime1' => '0900',
        'toTime1' => '1800',
        'collectionRequestAddress' => $shipment->getSender()
      ]
    ]);
    

    // Submit the shipment
    $shipment->submit();

    // Get the trackingdata
    $trackinglinks = $shipment->getParcelResponses();
    
    echo '<pre>';
    print_r($trackinglinks);
    

}catch(Exception $e){
  $result = new stdClass();
  $result->errors = [$e->getMessage()];
  $result->status = 400;
  print_r($result);
}


<?php namespace MCS;

use Exception;
use Soapclient;
use SoapFault;
use SOAPHeader;

class DPDPickup{

    protected $environment;

    protected $authorisation;

    protected $predictCountries = [
        'BE', 'NL', 'DE', 'AT', 
        'PL', 'FR', 'PT', 'GB', 
        'LU', 'EE', 'CH', 'IE', 
        'SK', 'LV', 'SI', 'LT', 
        'CZ', 'HU'
    ];

    protected $storeOrderMessage = [
        'printOptions' => [
            'paperFormat' => null,
            'printerLanguage' => null
        ],
        'order' => [
            'generalShipmentData' => [
                'sendingDepot' => null,
                'product' => null,
                'mpsCustomerReferenceNumber1' => null,
                'mpsCustomerReferenceNumber2' => null,
                'sender' => [
                    'name1' => null,
                    'name2' => null,
                    'street' => null,
                    'houseNo' => null,
                    'state' => null,
                    'country' => null,
                    'zipCode' => null,
                    'city' => null,
                    'email' => null,
                    'phone' => null,
                    'gln' => null,
                    'contact' => null,
                    'fax' => null,
                    'customerNumber' => null,
                ],
                'recipient' => [           
                    'name1' => null,         
                    'name2' => null,       
                    'street' => null,       
                    'houseNo' => null, 
                    'state' => null,
                    'country' => null,
                    'gln' => null,
                    'zipCode' => null,
                    'customerNumber' => null,
                    'contact' => null,        
                    'phone' => null,                 
                    'fax' => null,                 
                    'email' => null,            
                    'city' => null,       
                    'comment' => null 
                ]
            ],
            'productAndServiceData' => [
                'orderType' => 'consignment'
            ]
        ]
    ];

    protected $trackingLanguage = null;
    protected $label = null;
    protected $airWayBills = [];

    const TEST_SHIP_WSDL = 'https://public-ws-stage.dpd.com/services/ShipmentService/V3_1?wsdl';
    const SHIP_WSDL = 'https://public-ws.dpd.com/services/ShipmentService/V3_1?wsdl';
    const SOAPHEADER_URL = 'http://dpd.com/common/service/types/Authentication/2.0';
    const TRACKING_URL = 'https://tracking.dpd.de/parcelstatus?locale=:lang&query=:awb';

    /**
     * @param object  DPDAuthorisation    $authorisationObject 
     * @param boolean [$wsdlCache         = true]
     */
    public function __construct(DPDAuthorisation $authorisationObject, $wsdlCache = true)
    {
        $this->authorisation = $authorisationObject->authorisation;
        $this->environment = [
            'wsdlCache' => $wsdlCache,
            'shipWsdl'  => ($this->authorisation['staging'] ? self::TEST_SHIP_WSDL : self::SHIP_WSDL),
        ];   
        $this->storeOrderMessage['order']['generalShipmentData']['sendingDepot'] = $this->authorisation['token']->depot;
    }


    /**
     * Submit the parcel to the DPD webservice
     */
    public function submit()
    {

        if (isset($this->storeOrderMessage['order']['productAndServiceData']['predict'])){
            if (!in_array(strtoupper($this->storeOrderMessage['order']['generalShipmentData']['recipient']['country']), $this->predictCountries)){
                throw new Exception('Predict service not available for this destination');     
            }
        }
        
        if ($this->environment['wsdlCache']){
            $soapParams = [
                'cache_wsdl' => WSDL_CACHE_BOTH,
                'trace' => 1
            ];
        }
        else{
            $soapParams = [
                'cache_wsdl' => WSDL_CACHE_NONE,
                'exceptions' => true,
                'trace' => 1
            ];
        }
        
        try{

            $client = new Soapclient($this->environment['shipWsdl'], $soapParams);
            $header = new SOAPHeader(self::SOAPHEADER_URL, 'authentication', $this->authorisation['token']);
            $client->__setSoapHeaders($header);

            $response = $client->storeOrders($this->storeOrderMessage);

            if (isset($response->orderResult->shipmentResponses->faults)){
              throw new Exception($response->orderResult->shipmentResponses->faults->message);   
            }
            
            $this->airWayBills[] = [
              'PickupId' => $response->orderResult->shipmentResponses->mpsId,
            ];    
            
        }
        catch (SoapFault $e)
        {
         throw new Exception($e->faultstring);   
        }

    }

    /**
     * Enable DPD's B2C service. Only allowed for countries in protected $predictCountries
     * @param array $array
     *  'channel' => email|telephone|sms,
     *  'value' => emailaddress or phone number,
     *  'language' => EN
     */
    public function setPredict($array)
    {

        if (!isset($array['channel']) or !isset($array['value']) or !isset($array['language'])){
            throw new Exception('Predict array not complete');      
        }

        switch (strtolower($array['channel'])) {
            case 'email':
                $array['channel'] = 1;
                if (!filter_var($array['value'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Predict email address not valid');      
                }
                break;
            case 'telephone':
                $array['channel'] = 2;
                if (empty($array['value'])){
                    throw new Exception('Predict value (telephone) empty');      
                }
                break;
            case 'sms':
                $array['channel'] = 3;
                if (empty($array['value'])){
                    throw new Exception('Predict value (sms) empty');      
                }
                break;
            default:
                throw new Exception('Predict channel not allowed');   
        }

        if (ctype_alpha($array['language']) && strlen($array['language']) === 2){
            $array['language'] = strtoupper($array['language']);        
        }
        $this->storeOrderMessage['order']['productAndServiceData']['predict'] = $array;
    }

    /**
     * Get an array with parcelnumber and trackinglink for each package
     * @return array
     */
    public function getParcelResponses()
    {
     return $this->airWayBills;   
    }

    /**
     * Set the general shipmentdata
     * @param array $array see protected $storeOrderMessage
     */
    public function setGeneralShipmentData($array)
    {
     $this->storeOrderMessage['order']['generalShipmentData'] = array_merge($this->storeOrderMessage['order']['generalShipmentData'], $array);
    }
    
    /**
     * Enable Pickup Request
     * @param array $array
     */
    public function setPickup($array)
    {
     $this->storeOrderMessage['order']['productAndServiceData'] = array_merge($this->storeOrderMessage['order']['productAndServiceData'], $array);
    }
    
    /**
     * Set the shipment's sender
     * @param array $array see protected $storeOrderMessage
     */
    public function setSender($array)
    {
     $array['customerNumber'] = $this->authorisation['customerNumber'];
     $array['city'] = strtoupper($array['city']);
     $this->storeOrderMessage['order']['generalShipmentData']['sender'] = array_merge($this->storeOrderMessage['order']['generalShipmentData']['sender'], $array);
    }

    /**
     * Set the shipment's receiver
     * @param array $array see protected $storeOrderMessage
     */
    public function setReceiver($array)
    {
     $this->storeOrderMessage['order']['generalShipmentData']['recipient'] = array_merge($this->storeOrderMessage['order']['generalShipmentData']['recipient'], $array);
    }
   
   /**
     * Set the printoptions
     * @param array $array see protected $storeOrderMessage
     */
    public function setPrintOptions($printoptions)
    {
     $this->storeOrderMessage['printOptions'] = array_merge($this->storeOrderMessage['printOptions'], $printoptions);
    }
   
}

<?php
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'rave_response.php';
/**
 * Rave API.
 *
 * @package blesta
 * @subpackage blesta.components.modules.paystack
 * @copyright Copyright (c) 2017, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 *
 * Documentation at https://developers.flutterwave.com/
 */
class RaveApi
{
    /**
     * @var string The Rave API secret key
     * @var string The Rave API public key
     * @var string The Rave API url
     */
    private $secret_key;
    private $public_key;
    private $url;

    /**
     * Initializes the class.
     *
     * @param string $secret_key The Rave API secret key
     * @param string $public_key The Rave API public key
     */
    public function __construct($secret_key, $public_key, $api_url)
    {
        $this->secret_key = $secret_key;
        $this->public_key = $public_key;
        $this->url = $api_url;
    }

    /**
     * Send a request to the Rave API.
     *
     * @param string $method Specifies the endpoint and method to invoke
     * @param array $params The parameters to include in the api call
     * @param string $type The HTTP request type
     * @return stdClass An object containing the api response
     */
    private function postApiRequest($method, array $params = [])
    {
        $url = $this->url.$method;
        
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params)); //Post Fields
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 200);
        curl_setopt($ch, CURLOPT_TIMEOUT, 200);
    
        $headers = array('Content-Type: application/json');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
        $request = curl_exec($ch);
        $data = json_decode($request);
        
        return new RaveResponse($data);
    }

    /**
     * Build the payment request.
     *
     * @param array $params An array containing the payload arguments
     * @return stdClass An object containing the api response
     */
    public function buildPayment($params)
    {
        return $this->postApiRequest('/flwv3-pug/getpaidx/api/v2/hosted/pay', $params);
    }


    /**
     * Validate this payment.
     *
     * @param string $reference The unique reference code for this payment
     * @return stdClass An object containing the api response
     */
    public function checkPayment($verifyParams)
    {
        return $this->postApiRequest('/flwv3-pug/getpaidx/api/v2/verify', $verifyParams);
    }
}

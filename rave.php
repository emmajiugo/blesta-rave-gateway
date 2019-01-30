<?php
/**
 * Rave Gateway
 *
 * @package blesta
 * @subpackage blesta.components.gateways.nonmerchant_demo
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Rave extends NonmerchantGateway {
	/**
	 * @var string The version of this gateway
	 */
	private static $version = "1.0.1";
	/**
	 * @var string The authors of this gateway
	 */
	private static $authors = array(array('name' => "Emmajiugo", 'url' => "http://www.github.com/emmajiugo"));
	/**
	 * @var array An array of meta data for this gateway
	 */
	private $meta;
	/**
     * @var string The URL to post payments to in developer mode
     */
    private $test_url = 'https://ravesandboxapi.flutterwave.com';
    /**
     * @var string The URL to use when communicating with the live Rave API
     */
    private $live_url = 'https://api.ravepay.co';
	
	
	/**
	 * Construct a new merchant gateway
	 */
	public function __construct() {
		
		// Load components required by this gateway
		Loader::loadComponents($this, array("Input"));
		
		// Load the language required by this gateway
		Language::loadLang("rave", null, dirname(__FILE__) . DS . "language" . DS);
		
		$this->loadConfig(dirname(__FILE__) . DS . 'config.json');
	}
	
	/**
	 * Sets the currency code to be used for all subsequent payments
	 *
	 * @param string $currency The ISO 4217 currency code to be used for subsequent payments
	 */
	public function setCurrency($currency) {
		$this->currency = $currency;
	}
	
	/**
	 * Create and return the view content required to modify the settings of this gateway
	 *
	 * @param array $meta An array of meta (settings) data belonging to this gateway
	 * @return string HTML content containing the fields to update the meta data for this gateway
	 */
	public function getSettings(array $meta=null) {
		$this->view = $this->makeView("settings", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));

		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$this->view->set("meta", $meta);
		
		return $this->view->fetch();
	}
	
	/**
	 * Validates the given meta (settings) data to be updated for this gateway
	 *
	 * @param array $meta An array of meta (settings) data to be updated for this gateway
	 * @return array The meta data to be updated in the database for this gateway, or reset into the form on failure
	 */
	public function editSettings(array $meta) {
		// Verify meta data is valid
		$rules = array(
			'live_public_key' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Rave.!error.live_public_key', true)
                ]
            ],
            'live_secret_key' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Rave.!error.live_secret_key', true)
                ]
			],
			'test_public_key' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Rave.!error.test_public_key', true)
                ]
            ],
            'test_secret_key' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Rave.!error.test_secret_key', true)
                ]
            ],
            'live_mode' => [
                'valid' => [
                    'if_set' => true,
                    'rule' => ['in_array', ['true', 'false']],
                    'message' => Language::_('Rave.!error.live_mode.valid', true)
                ]
            ]
			
		);
		
		$this->Input->setRules($rules);
		
		// Validate the given meta data to ensure it meets the requirements
		$this->Input->validates($meta);
		// Return the meta data, no changes required regardless of success or failure for this gateway
		return $meta;
	}
	
	/**
	 * Returns an array of all fields to encrypt when storing in the database
	 *
	 * @return array An array of the field names to encrypt when storing in the database
	 */
	public function encryptableFields() {

		return ['live_public_key', 'live_secret_key'];
	}
	
	/**
	 * Sets the meta data for this particular gateway
	 *
	 * @param array $meta An array of meta data to set for this gateway
	 */
	public function setMeta(array $meta=null) {
		$this->meta = $meta;
	}
	
	/**
	 * Returns all HTML markup required to render an authorization and capture payment form
	 *
	 * @param array $contact_info An array of contact info including:
	 * 	- id The contact ID
	 * 	- client_id The ID of the client this contact belongs to
	 * 	- user_id The user ID this contact belongs to (if any)
	 * 	- contact_type The type of contact
	 * 	- contact_type_id The ID of the contact type
	 * 	- first_name The first name on the contact
	 * 	- last_name The last name on the contact
	 * 	- title The title of the contact
	 * 	- company The company name of the contact
	 * 	- address1 The address 1 line of the contact
	 * 	- address2 The address 2 line of the contact
	 * 	- city The city of the contact
	 * 	- state An array of state info including:
	 * 		- code The 2 or 3-character state code
	 * 		- name The local name of the country
	 * 	- country An array of country info including:
	 * 		- alpha2 The 2-character country code
	 * 		- alpha3 The 3-cahracter country code
	 * 		- name The english name of the country
	 * 		- alt_name The local name of the country
	 * 	- zip The zip/postal code of the contact
	 * @param float $amount The amount to charge this contact
	 * @param array $invoice_amounts An array of invoices, each containing:
	 * 	- id The ID of the invoice being processed
	 * 	- amount The amount being processed for this invoice (which is included in $amount)
	 * @param array $options An array of options including:
	 * 	- description The Description of the charge
	 * 	- return_url The URL to redirect users to after a successful payment
	 * 	- recur An array of recurring info including:
	 * 		- amount The amount to recur
	 * 		- term The term to recur
	 * 		- period The recurring period (day, week, month, year, onetime) used in conjunction with term in order to determine the next recurring payment
	 * @return string HTML markup required to render an authorization and capture payment form
	 */
	public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null)
    {
        // Load the models required
        Loader::loadModels($this, ['Clients']);

		$client = $this->Clients->get($contact_info['client_id']);
		
		// Get the url to send params
		if ($this->meta['live_mode']) {
			$url = $this->live_url;
			$pkey = $this->meta['live_public_key'];
			$skey = $this->meta['live_secret_key'];
		} else {
			$url = $this->test_url;
			$pkey = $this->meta['test_public_key'];
			$skey = $this->meta['test_secret_key'];
		}

		// Load Rave API
		$api = $this->getApi($skey, $pkey, $url);

		// $redirect_url1 = $this->ifSet($options['return_url']);
		$redirect_url2 = Configure::get('Blesta.gw_callback_url')
		. Configure::get('Blesta.company_id') . '/rave/?client_id='
		. $this->ifSet($contact_info['client_id']);

		// set parameter to send to API
		$invoices = serialize($invoice_amounts);
        $params = [
			'amount'=>$amount,
			'customer_email'=>$client->email,
			'currency'=>$this->currency,
			'txref'=>"BLST-".time(),
			'PBFPubKey'=>$pkey,
			'redirect_url'=>$redirect_url2,
			'meta' => array(
				[
					'metaname' => 'clientID',
					'metavalue' => $contact_info['client_id']
				],
				[
					'metaname' => 'invoices',
					'metavalue' => $invoices
				]
            ),
		];
		
		

		// Get the url to redirect the client to rave standard
		$result = $api->buildPayment($params);
		$data = $result->data();
		$rave_url = isset($data->link) ? $data->link : '';

        return $this->buildForm($rave_url);
    }

    /**
     * Builds the HTML form.
     *
     * @return string The HTML form
     */
    private function buildForm($post_to)
    {
        $this->view = $this->makeView('process', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));

        // Load the helpers required for this view
		Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('post_to', $post_to);
        return $this->view->fetch();
	}


	/**
	 * Validates the incoming POST/GET response from the gateway to ensure it is
	 * legitimate and can be trusted.
	 *
	 * @param array $get The GET data for this request
	 * @param array $post The POST data for this request
	 * @return array An array of transaction data, sets any errors using Input if the data fails to validate
	 *  - client_id The ID of the client that attempted the payment
	 *  - amount The amount of the payment
	 *  - currency The currency of the payment
	 *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
	 *  	- id The ID of the invoice to apply to
	 *  	- amount The amount to apply to the invoice
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the gateway to identify this transaction
	 */
	public function validate(array $get, array $post) {

		// Get the response callback
		$callback_data = json_decode($post['resp']);

		// Log request received
        $this->log(
            $this->ifSet($_SERVER['REQUEST_URI']),
            json_encode(
                $callback_data,
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            ),
            'output',
            true
        );

		// Get the url to send params
		if ($this->meta['live_mode']) {
			$url = $this->live_url;
			$pkey = $this->meta['live_public_key'];
			$skey = $this->meta['live_secret_key'];
		} else {
			$url = $this->test_url;
			$pkey = $this->meta['test_public_key'];
			$skey = $this->meta['test_secret_key'];
		}

		// Load Rave API
		$api = $this->getApi($skey, $pkey, $url);
		

        // Log data sent for validation
        $this->log(
            'validate',
            json_encode([
				'txref' => $callback_data->data->tx->txRef, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
			]),
            'output',
            true
		);

		// verify transaction
		$verifyParams = [
			'txref' => $callback_data->data->tx->txRef,
  			'SECKEY' => $skey 
		];
		$result = $api->checkPayment($verifyParams);
		$data = $result->data();

		// file_put_contents(time(), json_encode($data));

		// Log response received from verify
        $this->log(
            'verify response',
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'output',
            true
		);

		// Get client ID and invoice from metadata
		$metadata = $this->ifSet($data->meta);
		foreach ($metadata as $value) {
			if ($value->metaname == 'clientID') {
				$client_id = $value->metavalue;
			}

			if ($value->metaname == 'invoices') {
				$invoices = $value->metavalue;
			}
		}
		
		// decode the invoices
		$final_invoices = unserialize($invoices);
		
        //return final response for blesta
		return [
            'client_id' => $this->ifSet($client_id),
            'amount' => $this->ifSet($data->amount),
            'currency' => $this->ifSet($data->currency),
			'status' => $this->ifSet($data->chargecode) == '00' || $this->ifSet($data->chargecode) == '0' ? 'approved' : 'declined', // we wouldn't be here if it weren't, right?
			'reference_id' => null,
			'transaction_id' => $this->ifSet($data->txref),
			'parent_transaction_id' => null,
            'invoices' => $this->ifSet($final_invoices)
        ];

		
		// file_put_contents(time(), $callback_data->data->tx->txRef);
	}
	
	/**
	 * Returns data regarding a success transaction. This method is invoked when
	 * a client returns from the non-merchant gateway's web site back to Blesta.
	 *
	 * @param array $get The GET data for this request
	 * @param array $post The POST data for this request
	 * @return array An array of transaction data, may set errors using Input if the data appears invalid
	 *  - client_id The ID of the client that attempted the payment
	 *  - amount The amount of the payment
	 *  - currency The currency of the payment
	 *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
	 *  	- id The ID of the invoice to apply to
	 *  	- amount The amount to apply to the invoice
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- transaction_id The ID returned by the gateway to identify this transaction
	 */
	public function success(array $get, array $post) {

		// Get the url to send params
		if ($this->meta['live_mode']) {
			$url = $this->live_url;
			$pkey = $this->meta['live_public_key'];
			$skey = $this->meta['live_secret_key'];
		} else {
			$url = $this->test_url;
			$pkey = $this->meta['test_public_key'];
			$skey = $this->meta['test_secret_key'];
		}

		// Load Rave API
		$api = $this->getApi($skey, $pkey, $url);

		// verify transaction
		$verifyParams = [
			'txref' => $this->ifSet($get['txref']),
  			'SECKEY' => $skey 
		];
		$result = $api->checkPayment($verifyParams);
		$data = $result->data();

		// Get client ID and invoice from metadata
		$metadata = $this->ifSet($data->meta);
		foreach ($metadata as $value) {
			if ($value->metaname == 'clientID') {
				$client_id = $value->metavalue;
			}

			if ($value->metaname == 'invoices') {
				$invoices = $value->metavalue;
			}
		}
		
		// decode the invoices
		$final_invoices = unserialize($invoices);
		
        //return final response for blesta
		return [
            'client_id' => $this->ifSet($client_id),
            'amount' => $this->ifSet($data->amount),
            'currency' => $this->ifSet($data->currency),
            'status' => 'approved', // we wouldn't be here if it weren't, right?
            'transaction_id' => $this->ifSet($data->txref),
            'invoices' => $this->ifSet($final_invoices)
        ];
        
	}
	
	/**
	 * Captures a previously authorized payment
	 *
	 * @param string $reference_id The reference ID for the previously authorized transaction
	 * @param string $transaction_id The transaction ID for the previously authorized transaction
	 * @return array An array of transaction data including:
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function capture($reference_id, $transaction_id, $amount, array $invoice_amounts=null) {
		
		#
		# TODO: Return transaction data, if possible
		#
		
		$this->Input->setErrors($this->getCommonError("unsupported"));
	}
	
	/**
	 * Void a payment or authorization
	 *
	 * @param string $reference_id The reference ID for the previously submitted transaction
	 * @param string $transaction_id The transaction ID for the previously submitted transaction
	 * @param string $notes Notes about the void that may be sent to the client by the gateway
	 * @return array An array of transaction data including:
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function void($reference_id, $transaction_id, $notes=null) {
		
		#
		# TODO: Return transaction data, if possible
		#
		
		$this->Input->setErrors($this->getCommonError("unsupported"));
	}
	
	/**
	 * Refund a payment
	 *
	 * @param string $reference_id The reference ID for the previously submitted transaction
	 * @param string $transaction_id The transaction ID for the previously submitted transaction
	 * @param float $amount The amount to refund this card
	 * @param string $notes Notes about the refund that may be sent to the client by the gateway
	 * @return array An array of transaction data including:
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function refund($reference_id, $transaction_id, $amount, $notes=null) {
		
		#
		# TODO: Return transaction data, if possible
		#
		
		$this->Input->setErrors($this->getCommonError("unsupported"));
	}

	/**
     * Initializes the Rave API and returns an instance of that object with the given account information set.
     *
     * @param string $skey secret key
	 * @param string $pkey public key
     * @return RaveApi A Rave instance
     */
    private function getApi($skey, $pkey, $url)
    {
        // Load library methods
        Loader::load(dirname(__FILE__) . DS . 'lib' . DS . 'rave_api.php');

        return new RaveApi($skey, $pkey, $url);
    }
	
}
?>
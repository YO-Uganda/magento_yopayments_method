<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace YoUgandaLimited\YoPaymentsGateway\Gateway\Request;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
//use Magento\Framework\Simplexml\Element;

class AuthorizationRequest implements BuilderInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @param ConfigInterface $config
     */
    public function __construct(
        ConfigInterface $config
    ) {
        $this->config = $config;
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }


        /** @var PaymentDataObjectInterface $payment */
        $payment = $buildSubject['payment'];
        $order = $payment->getOrder();
        $address = $order->getShippingAddress();
        $currency = $order->getCurrencyCode();
        if (strcmp(strtoupper($currency), "UGX")<>0) {
            $error = "Unsupported currency ".$currency;
            throw new \Magento\Framework\Exception\LocalizedException(__($error));
        }

        $request_ = file_get_contents("php://input");
        $requestInfo = json_decode($request_);
        if (is_null($requestInfo)) {
            throw new \InvalidArgumentException('Missing request info');
            /*
            return [
                'TXN_TYPE' => 'A',
                'INVOICE' => $order->getOrderIncrementId(),
                'AMOUNT' => $order->getGrandTotalAmount(),
                'CURRENCY' => $order->getCurrencyCode(),
                //'EMAIL' => $address->getEmail(),
                'GW_ERROR' => "Invalid request"
            ];*/
        }
        //throw new \InvalidArgumentException('Payment Address: '.print_r($requestInfo, true));

        $phone = $requestInfo->paymentMethod->additional_data->mm_number;
        //throw new \InvalidArgumentException('Payment Address: '.print_r($phone, true));

        $amount = $order->getGrandTotalAmount();
        $external_ref = $order->getOrderIncrementId();
        $currency = $order->getCurrencyCode();
       
        $narrative = "Payment for Invoice {$external_ref} for amount {$currency} {$amount}";

        $api_username = $this->config->getValue('api_username', $order->getStoreId());
        $api_password = $this->config->getValue('api_password', $order->getStoreId());
        $api_username_sandbox = $this->config->getValue('api_username_sandbox', $order->getStoreId());
        $api_password_sandbox = $this->config->getValue('api_password_sandbox', $order->getStoreId());
        $use_sandbox = $this->config->getValue('use_sandbox', $order->getStoreId());
        $use_pay1 = $this->config->getValue('use_pay1', $order->getStoreId());

        if (intval($use_sandbox) == 1) {
            $this->api_username = $api_username_sandbox;
            $this->api_password = $api_password_sandbox;
            $this->url = "https://sandbox.yo.co.ug/services/yopaymentsdev/task.php";
        } else {
            $this->url = "https://paymentsapi1.yo.co.ug/ybs/task.php";
            if (intval($use_pay1) == 1) {
                $this->url = "https://pay1.yo.co.ug/ybs/task.php";
            }
            $this->api_username = $api_username;
            $this->api_password = $api_password;
        }
        
        //Validate phone first
        if (!$this->validatePhone($phone)) {
            $error = "Invalid phone number {$phone}. It should be in correct format e.g 256772123456";
            throw new \Magento\Framework\Exception\LocalizedException(__($error));
        }

        $payment_a['phone'] = $phone;
        $payment_a['amount'] = $amount;
        $payment_a['external_ref'] = $external_ref;
        $payment_a['narrative'] = $narrative;

        $res_ = $this->yoPaymentsSubmitPayment($payment_a);
        if (strcmp($res_['status'], "SUCCEEDED")<>0) {
            $error = $res_['message'];
            throw new \Magento\Framework\Exception\LocalizedException(__($error));
            /*
            return [
                'TXN_TYPE' => 'A',
                'INVOICE' => $order->getOrderIncrementId(),
                'AMOUNT' => $order->getGrandTotalAmount(),
                'CURRENCY' => $order->getCurrencyCode(),
                //'EMAIL' => $address->getEmail(),
                'GW_ERROR' => $res_['message'],
            ];
            */
        }

        return [
            'TXN_TYPE' => 'A',
            'INVOICE' => $order->getOrderIncrementId(),
            'AMOUNT' => $order->getGrandTotalAmount(),
            'CURRENCY' => $order->getCurrencyCode(),
            //'EMAIL' => $address->getEmail(),
            'TXN_ID' => $res_['transaction_id']
        ];
    }

    private function validatePhone($phone)
    {	
        if (!preg_match("/\d{1,3}\d{3}\d{6}$/", $phone))
            return false;
        return true;
    }

    /*
    * 
    * @Param Array payment[phone, phone, external_ref, narrative]
    * 
    */
    public function yoPaymentsSubmitPayment($payment)
    {
        try {
            
            //Now try submitting a Yo! Payments request
            set_time_limit(0);
            $details['NonBlocking'] = 'TRUE'; 
            $details["Account"] = $payment['phone'];
            $details["Amount"] = $payment['amount'];
            $details['Narrative'] = $payment['narrative'];
            $details['ExternalReference'] = $payment['external_ref'];
            $details['ProviderReferenceText'] = $payment['narrative'];

            $res =  $this->yoPaymentsDepositFunds($details);

            if (!is_array($res)) {
                return array(
                        "status"=>"ERROR",
                        "message"=>$res,
                    );
            }

            if (isset($res['TransactionStatus'])) {
                if ($res['TransactionStatus']== "SUCCEEDED") {
                    return array(
                        "status"=>"SUCCEEDED",
                        "message"=>"Payment received successfully",
                        'transaction_id'=>$res['TransactionReference'],
                    );
                } else if ($res['TransactionStatus']== "FAILED") {
                    return array(
                        "status"=>"FAILED",
                        "message"=>"Payment failed. Please try again. See more: "
                            .$res['StatusMessage']." ".$res['ErrorMessage'],
                    );
                    
                } else if ($res['TransactionStatus']== "PENDING") {
                    $transaction_id = $res['TransactionReference'];
                    $details_['TransactionReference'] = $transaction_id;
                    $details_['PrivateTransactionReference'] = $details['ExternalReference'];
                    //Try checking for status of payment.
                    $check_time = 0;
                    while (1) {
                        //Check if maximum number of retries has been reached.
                        if ($check_time >= 4) {
                            return array(
                                    "status"=>"UNDETERMINED",
                                    "message"=>"Undetermined payment.",
                                    'transaction_id'=>$transaction_id,
                                );
                        }

                        sleep(5);
                        $check =  $this->yoPaymentsFollowUpTransaction($details_);
                        if (isset($check['TransactionStatus'])) {
                            if ($check['TransactionStatus']== "SUCCEEDED") {
                                return array(
                                    "status"=>"SUCCEEDED",
                                    "message"=>"Payment received successfully",
                                    'transaction_id'=>$transaction_id,
                                );
                            } else if ($check['TransactionStatus']== "FAILED") {
                                return array(
                                    "status"=>"FAILED",
                                    "message"=>"Payment failed. Please try again. See more below: "
                                    .$res['StatusMessage']." ".$res['ErrorMessage'],
                                );
                            } else if ($check['TransactionStatus'] == "INDETERMINATE") {
                                return array(
                                    "status"=>"UNDETERMINED",
                                    "message"=>"Indeterminate payment - It will be resolved later."
                                );
                            }
                        } else {
                            return array(
                                    "status"=>"UNDETERMINED",
                                    "message"=>"Indeterminate payment - It will be resolved later.",
                                );
                        }
                        $check_time += 1;
                    }
                } else {
                    return array(
                        "status"=>"ERROR",
                        "message"=>$res['StatusMessage']." ".$res['ErrorMessage'],
                    );
                }
            } else {
                return array(
                    "status"=>"ERROR",
                    "message"=>$res['StatusMessage']." ".$res['ErrorMessage'],
                );
            }
        } catch (Exception $ex) {
            $e = "Error: ".$ex->getMessage()."</br/>"
            ."File: ".$ex->getFile()."<br/>"
            ."Line: ".$ex->getLine();
            return array(
                "status"=>"ERROR",
                "message"=>$e,
            );
        }
    }


    public function yoPaymentsFollowUpTransaction($data)
	{
		$xml_format = '<?xml version="1.0" encoding="UTF-8"?>
					<AutoCreate> 
						<Request>
							<APIUsername>'.$this->api_username.'</APIUsername>
							<APIPassword>'.$this->api_password.'</APIPassword>
							<Method>actransactioncheckstatus</Method>';
		foreach ($data as $field=>$value) {
			if (strlen($value)>0)
				$xml_format .= "<$field>".htmlspecialchars($value)."</$field>";
		}
		$xml_format .= '</Request> 
					</AutoCreate>';
		//$this->sent_xml = $this->yoPaymentsFormatXml($xml_format);		
		try { 
			$content = $this->yoPaymentsRequest($this->url, $xml_format);	
			if (strlen($content['error'])>0) {
				return $content['error'];
			}

			@$return_array = new \SimpleXMLElement( $content['data'] );
			//$this->returned_xml = $this->yoPaymentsFormatXml($content['data']);
		} catch (Exception $ex) {
			return $ex->getMessage();
		}

		$return = array();
		$return["Status"] = (isset($return_array->Response[0]->Status) ? (String)$return_array->Response[0]->Status : '');
		$return["StatusCode"] = (isset($return_array->Response[0]->StatusCode) ? (String)$return_array->Response[0]->StatusCode: '');
		$return["StatusMessage"] = (isset($return_array->Response[0]->StatusMessage) ? (String)$return_array->Response[0]->StatusMessage : '');
		$return["ErrorMessage"] = (isset($return_array->Response[0]->ErrorMessage) ? (String)$return_array->Response[0]->ErrorMessage : '');
		$return["TransactionStatus"] = (isset($return_array->Response[0]->TransactionStatus) ? 
		(String)$return_array->Response[0]->TransactionStatus : '');
		$return["TransactionReference"] = (isset($return_array->Response[0]->TransactionReference) ? 
		(String)$return_array->Response[0]->TransactionReference : '');
		return $return;
	}

    /*
	#This funciton below will handle the Depositing of funds from a mobile money 
	* account to your Yo! Payments account.
	*
	* @Param $data	-Assoc array of fields required as described in the API. See the example file.
	* 
	* Returns	- Integer 0 if any error occurs.
				- An associative array with response fields as described in the API document.
	*/
	public function yoPaymentsDepositFunds($data)
	{
		$arg = func_get_args();
		$xml_format = '<?xml version="1.0" encoding="UTF-8"?><AutoCreate><Request>
						<APIUsername>'.$this->api_username.'</APIUsername>
						<APIPassword>'.$this->api_password.'</APIPassword>';
		$xml_format .='<NonBlocking>TRUE</NonBlocking>';
		$xml_format .='<Method>acdepositfunds</Method>';
		foreach ($data as $field=>$value)
			$xml_format .= "<$field>".htmlspecialchars($value)."</$field>";
		$xml_format.='</Request></AutoCreate>';
		
        		
		
        try { 
            
            //$this->sent_xml = $this->yoPaymentsFormatXml($xml_format);
			$content = $this->yoPaymentsRequest($this->url, $xml_format);	
			if (strlen($content['error'])>0) {
				return $content['error'];
			}

            if (empty($content['data'])) {
                throw new \InvalidArgumentException('YoPaymentsGateway RESPONSE: Empty'.print_r($content, true));
            }
			
            $return_array = new \SimpleXMLElement($content['data']);
            
			//$this->returned_xml = $this->yoPaymentsFormatXml($content['data']);
		} catch (Exception $ex) {
            throw new \InvalidArgumentException('YoPaymentsGateway ERROR: '.$ex->getMessage());
			return $ex->getMessage();
		}
        
		
		$return = array();
		$return["Status"] = (isset($return_array->Response[0]->Status) ? (String)$return_array->Response[0]->Status : '');
		$return["StatusCode"] = (isset($return_array->Response[0]->StatusCode) ? (String)$return_array->Response[0]->StatusCode: '');
		$return["StatusMessage"] = (isset($return_array->Response[0]->StatusMessage) ? (String)$return_array->Response[0]->StatusMessage : '');
		$return["ErrorMessage"] = (isset($return_array->Response[0]->ErrorMessage) ? (String)$return_array->Response[0]->ErrorMessage : '');
		$return["TransactionStatus"] = (isset($return_array->Response[0]->TransactionStatus) ? 
		(String)$return_array->Response[0]->TransactionStatus : '');
		$return["TransactionReference"] = (isset($return_array->Response[0]->TransactionReference) ? 
		(String)$return_array->Response[0]->TransactionReference : '');
		return $return;
	}

    /*
	* formatXml formats the xml data so 
	* that it can look funcy for display.
	* 
	* Returns the funcy XML data.
	*/
	private function yoPaymentsFormatXml($xml)
	{
		$dom = new DOMDocument();
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		$dom->loadXML($xml);
		return $dom->saveXML();
	}

    private function yoPaymentsRequest($url, $data)
	{
		set_time_limit(0);
		$headers = array(
			'Content-type: text/xml', 
			'Content-length: '.strlen($data), 
			'Content-transfer-encoding: text'
		);
		try {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);		
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt($ch, CURLOPT_POST,           1 );
			curl_setopt($ch, CURLOPT_POSTFIELDS,     $data ); 
			curl_setopt($ch, CURLOPT_HTTPHEADER,     $headers ); 
			$response = curl_exec($ch);
			$error =curl_error($ch);
			return array(
                "error"=>$error, 
                "data"=>trim($response)
            );
		} catch (Exception $ex) {
			return array(
                "error"=>$ex->getMessage(), 
                "data"=>""
            );
		}
	}
}

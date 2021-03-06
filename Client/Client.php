<?php
namespace Maestrojosiah\Payment\PaypalBundle\Client;

use Maestrojosiah\Payment\PaypalBundle\Client\Authentication\KeyedCredentialsAuthenticationStrategyInterface;
use Symfony\Component\BrowserKit\Response as RawResponse;

use Maestrojosiah\Payment\CoreBundle\BrowserKit\Request;
use Maestrojosiah\Payment\CoreBundle\Plugin\Exception\CommunicationException;
use Maestrojosiah\Payment\PaypalBundle\Client\Authentication\AuthenticationStrategyInterface;

/*
 * Copyright 2010 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class Client
{
    const API_VERSION = '65.1';

    protected $authenticationStrategy;

    protected $isDebug;

    protected $curlOptions;

    public function __construct(AuthenticationStrategyInterface $authenticationStrategy, $isDebug)
    {
        $this->authenticationStrategy = $authenticationStrategy;
        $this->isDebug = !!$isDebug;
        $this->curlOptions = array();
    }

    public function requestAddressVerify($email, $street, $postalCode, $key = null)
    {
        return $this->sendApiRequest(array(
            'METHOD' => 'AddressVerify',
            'EMAIL'  => $email,
            'STREET' => $street,
            'ZIP'    => $postalCode,
        ), $key);
    }

    public function requestBillOutstandingAmount($profileId, array $optionalParameters = array(), $key = null)
    {
        return $this->sendApiRequest(array_merge($optionalParameters, array(
            'METHOD' => 'BillOutstandingAmount',
            'PROFILEID' => $profileId,
        )), $key);
    }

    public function requestCreateRecurringPaymentsProfile($token, $key = null)
    {
        return $this->sendApiRequest(array(
            'METHOD' => 'CreateRecurringPaymentsProfile',
            'TOKEN' => $token,
        ), $key);
    }

    public function requestDoAuthorization($transactionId, $amount, array $optionalParameters = array(), $key = null)
    {
        return $this->sendApiRequest(array_merge($optionalParameters, array(
            'METHOD' => 'DoAuthorization',
            'TRANSACTIONID' => $transactionId,
            'AMT' => $this->convertAmountToPaypalFormat($amount),
        )), $key);
    }

    public function requestDoReAuthorization($authorizationId, $amount, array $optionalParameters = array(), $key = null)
    {
        return $this->sendApiRequest(array_merge($optionalParameters, array(
            'METHOD' => 'DoReAuthorization',
            'AUTHORIZATIONID' => $authorizationId,
            'AMT' => $this->convertAmountToPaypalFormat($amount),
        )), $key);
    }

    public function requestDoCapture($authorizationId, $amount, $completeType, array $optionalParameters = array(), $key = null)
    {
        return $this->sendApiRequest(array_merge($optionalParameters, array(
            'METHOD' => 'DoCapture',
            'AUTHORIZATIONID' => $authorizationId,
            'AMT' => $this->convertAmountToPaypalFormat($amount),
            'COMPLETETYPE' => $completeType,
        )), $key);
    }

    public function requestDoDirectPayment($ipAddress, array $optionalParameters = array(), $key = null)
    {
        return $this->sendApiRequest(array_merge($optionalParameters, array(
            'METHOD' => 'DoDirectPayment',
            'IPADDRESS' => $ipAddress,
        )), $key);
    }

    public function requestDoExpressCheckoutPayment($token, $amount, $paymentAction, $payerId, array $optionalParameters = array(), $key = null)
    {
        return $this->sendApiRequest(array_merge($optionalParameters, array(
            'METHOD' => 'DoExpressCheckoutPayment',
            'TOKEN'  => $token,
            'PAYMENTREQUEST_0_AMT' => $this->convertAmountToPaypalFormat($amount),
            'PAYMENTREQUEST_0_PAYMENTACTION' => $paymentAction,
            'PAYERID' => $payerId,
        )), $key);
    }

    public function requestDoVoid($authorizationId, array $optionalParameters = array(), $key = null)
    {
        return $this->sendApiRequest(array_merge($optionalParameters, array(
            'METHOD' => 'DoVoid',
            'AUTHORIZATIONID' => $authorizationId,
        )), $key);
    }

    /**
     * Initiates an ExpressCheckout payment process
     *
     * Optional parameters can be found here:
     * https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_nvp_r_SetExpressCheckout
     *
     * @param float $amount
     * @param string $returnUrl
     * @param string $cancelUrl
     * @param array $optionalParameters
     * @param string $key
     * @return Response
     */
    public function requestSetExpressCheckout($amount, $returnUrl, $cancelUrl, array $optionalParameters = array(), $key = null)
    {
        return $this->sendApiRequest(array_merge($optionalParameters, array(
            'METHOD' => 'SetExpressCheckout',
            'PAYMENTREQUEST_0_AMT' => $this->convertAmountToPaypalFormat($amount),
            'RETURNURL' => $returnUrl,
            'CANCELURL' => $cancelUrl,
        )), $key);
    }

    public function requestGetExpressCheckoutDetails($token, $key = null)
    {
        return $this->sendApiRequest(array(
            'METHOD' => 'GetExpressCheckoutDetails',
            'TOKEN'  => $token,
        ), $key);
    }

    public function requestGetTransactionDetails($transactionId, $key = null)
    {
        return $this->sendApiRequest(array(
            'METHOD' => 'GetTransactionDetails',
            'TRANSACTIONID' => $transactionId,
        ), $key);
    }

    public function requestRefundTransaction($transactionId, array $optionalParameters = array(), $key = null)
    {
        return $this->sendApiRequest(array_merge($optionalParameters, array(
            'METHOD' => 'RefundTransaction',
            'TRANSACTIONID' => $transactionId
        )), $key);
    }

    public function sendApiRequest(array $parameters, $key = null)
    {
        // include some default parameters
        $parameters['VERSION'] = self::API_VERSION;

        // setup request, and authenticate it
        $request = new Request(
            $this->authenticationStrategy->getApiEndpoint($this->isDebug),
            'POST',
            $parameters
        );
        if (null !== $key && $this->authenticationStrategy instanceof KeyedCredentialsAuthenticationStrategyInterface) {
            $this->authenticationStrategy->authenticateWithKeyedCredentials($request, $key);
        } else {
            $this->authenticationStrategy->authenticate($request);
        }

        $response = $this->request($request);
        if (200 !== $response->getStatus()) {
            throw new CommunicationException('The API request was not successful (Status: '.$response->getStatus().'): '.$response->getContent());
        }

        $parameters = array();
        parse_str($response->getContent(), $parameters);

        return new Response($parameters);
    }

    public function getAuthenticateExpressCheckoutTokenUrl($token)
    {
        $host = $this->isDebug ? 'www.sandbox.paypal.com' : 'www.paypal.com';

        return sprintf(
            'https://%s/cgi-bin/webscr?cmd=_express-checkout&token=%s',
            $host,
            $token
        );
    }

    public function convertAmountToPaypalFormat($amount)
    {
        return number_format($amount, 2, '.', '');
    }

    public function setCurlOption($name, $value)
    {
        $this->curlOptions[$name] = $value;
    }

    /**
     * A small helper to url-encode an array
     *
     * @param array $encode
     * @return string
     */
    protected function urlEncodeArray(array $encode)
    {
        $encoded = '';
        foreach ($encode as $name => $value) {
            $encoded .= '&'.urlencode($name).'='.urlencode($value);
        }

        return substr($encoded, 1);
    }

    /**
     * Performs a request to an external payment service
     *
     * @throws CommunicationException when an curl error occurs
     * @param Request $request
     * @param mixed $parameters either an array for form-data, or an url-encoded string
     * @return Response
     */
    public function request(Request $request)
    {
        if (!extension_loaded('curl')) {
            throw new \RuntimeException('The cURL extension must be loaded.');
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1); // Latest TLS(1.x)
        curl_setopt_array($curl, $this->curlOptions);
        curl_setopt($curl, CURLOPT_URL, $request->getUri());
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);

        // add headers
        $headers = array();
        foreach ($request->headers->all() as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $subValue) {
                    $headers[] = sprintf('%s: %s', $name, $subValue);
                }
            } else {
                $headers[] = sprintf('%s: %s', $name, $value);
            }
        }
        if (count($headers) > 0) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        // set method
        $method = strtoupper($request->getMethod());
        if ('POST' === $method) {
            curl_setopt($curl, CURLOPT_POST, true);

            if (!$request->headers->has('Content-Type') || 'multipart/form-data' !== $request->headers->get('Content-Type')) {
                $postFields = $this->urlEncodeArray($request->request->all());
            } else {
                $postFields = $request->request->all();
            }

            curl_setopt($curl, CURLOPT_POSTFIELDS, $postFields);
        } else if ('PUT' === $method) {
            curl_setopt($curl, CURLOPT_PUT, true);
        } else if ('HEAD' === $method) {
            curl_setopt($curl, CURLOPT_NOBODY, true);
        }

        // perform the request
        if (false === $returnTransfer = curl_exec($curl)) {
            throw new CommunicationException(
                'cURL Error: '.curl_error($curl), curl_errno($curl)
            );
        }

        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $headers = array();
        if (preg_match_all('#^([^:\r\n]+):\s+([^\n\r]+)#m', substr($returnTransfer, 0, $headerSize), $matches)) {
            foreach ($matches[1] as $key => $name) {
                $headers[$name] = $matches[2][$key];
            }
        }

        $response = new RawResponse(
            substr($returnTransfer, $headerSize),
            curl_getinfo($curl, CURLINFO_HTTP_CODE),
            $headers
        );
        curl_close($curl);

        return $response;
    }
}

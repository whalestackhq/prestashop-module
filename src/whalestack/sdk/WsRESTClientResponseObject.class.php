<?php
/**
 * @author Whalestack <service@whalestack.com>
 * @copyright 2022 Whalestack
 * @license https://www.apache.org/licenses/LICENSE-2.0
 */

namespace Whalestack\Sdk;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class WsRESTClientResponseObject
 *
 * An instance of this class is returned by the get, post, put or delete methods in WsMerchantClient
 */
class WsRESTClientResponseObject
{
    /**
     * Contains the HTTP response in plain text. Usually this is a JSON string
     * @var String
     */
    public $responseBody = null;

    /**
     * Contains the HTTP response headers in plain text
     * @var String (headers are separated by \n\n)
     */
    public $responseHeaders = null;

    /**
     * The numeric HTTP status code, as given by the Whalestack server
     * @var integer
     */
    public $httpStatusCode = null;

    /**
     * Plain text curl error description, if any
     * @var String
     */
    public $curlError = null;

    /**
     * The numeric curl error code, if any
     * @var integer
     */
    public $curlErrNo = null;

    /**
     * Contains an array with the entire curl information, as given by PHP's native curl_info().
     * @var array
     */
    public $curlInfo = null;

    public function __construct($responseBody, $responseHeaders, $httpStatusCode, $curlError, $curlErrNo, $curlInfo)
    {
        foreach (get_defined_vars() as $key => $value) {
            $this->$key = $value;
        }
    }
}

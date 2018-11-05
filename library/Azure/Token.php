<?php
/** ***************************************************************************
 * @author Peter Dreuw <peter.dreuw@credativ.de>
 * @copyright Copyright (c) 2018 creadtiv GmbH
 * @license https://github.com/credativ/icingaweb2-module-azure/blob/master/LICENSE MIT License
 *
 *
 */
namespace Icinga\Module\Azure;

use Icinga\Module\Azure\restclient\RestClient;

use Icinga\Application\Logger;
use Icinga\Exception\QueryException;

/**
 * Class Token
 *
 * This keeps track of the bearer token generation mechanism and
 * its expiring. Renews token if it expires.
 *
 */

class Token {

    const API_LOGIN = "https://login.microsoftonline.com";
    const API_TOKEN_TYPE = "Bearer";
        
    /** var restc  stores rest client api handling object */
    private $restc;
    
    /** var string bearer */
    private $bearer;

    /** var int expires */
    private $expires;

    /** @var string tenant_id */
    private $tenant_id;

    /** @var string client_id */
    private $client_id;

    /** @var string client_secret */
    private $client_secret;

    /** @var string client_secret */
    private $endpoint;

    /** @var string proxy */
    private $proxy;


    /** ***********************************************************************
     * constructor for Token object

     */
    
    public function __construct( $tenant_id, 
                                 $client_id, $client_secret,
                                 $endpoint, $proxy = '',
                                 $con_timeout = 0, $timeout = 0 )
    {       
        $this->tenant_id       = $tenant_id;
        $this->client_id       = $client_id;
        $this->client_secret   = $client_secret;
        $this->endpoint        = $endpoint;
        $this->proxy	       = $proxy;      

        $this->bearer  = NULL;
        $this->expires = NULL;
        
        $this->restc = new RestClient([ 
            'base_url' => self::API_LOGIN,
            'curl_options' => [
                CURLOPT_PROXY          => $proxy,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => $con_timeout,
            ],
            
            'format'   => "json",
            'parameters'  => [
                'grant_type'    => 'client_credentials',
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'resource'      => $endpoint,
            ],
        ]);
        
        // no need for assertions as the RestClient constructor
        // cannot fail. 

        $this->requestToken();
    }

    
    /** ***********************************************************************
     * call the login api and generate a new bearer token
     * may throw exceptions if login api is unwilling.
     *
     * @return void
     */

    private function requestToken()
    {
        Logger::info("Azure API: generating new Bearer token");
        $result = $this->restc->post($this->tenant_id."/oauth2/token");


        // check if there was a curl error
        if ($result->errno != CURLE_OK)
        {
            $msg = sprintf("Azure API: Got CURL error '%s' while %s token generation",
                           $result->error, self::API_TOKEN_TYPE);
            Logger::error( $msg );
            throw new QueryException($msg);
        }
        
        // check if HTTP result code was 200 - OK
        if ($result->info->http_code != 200)
        {
            Logger::error("Azure Token: Could not get bearer token. HTTP: ".
                          $result->info->http_code." CURL: ".$result->error);           
            throw new QueryException("Could not get bearer token. HTTP: ".
                                     $result->info->http_code);
        }

        // get result data from JSON into object $decoded
        $decoded = $result->decode_response();      
        
        // check some assertions on the returned API data
        // i.e. do we have every property and are these resulst plausible?
        if (
            (
                !(
                    property_exists( $decoded, "token_type" ) and
                    property_exists( $decoded, "access_token" ) and
                    property_exists( $decoded, "expires_on" ) and
                    property_exists( $decoded, "resource" )
                )
            ) or
            ( strcmp($decoded->token_type, self::API_TOKEN_TYPE) != 0 ) or
            ( strcmp($decoded->resource, $this->endpoint) != 0 )
        )            
        {
            // if assertion fails, report an error and bail out
            Logger::error("Azure Token: Malformed result on token access ".
                          print_r($decoded,true));
            throw new QueryException("Malformed result on token access ".
                                     print_r($decoded,true));
        }

        // store bearer token and expirery in object
        $this->bearer  = $decoded->access_token;
        $this->expires = $decoded->expires_on;

        return;
    }

    
    /****************************************************
     * tests if token has expired, true if invalid
     *
     * @return bool
     */

    private function expired() {
        return (($this->expires === NULL) or ($this->expires <= time()));
    }

    
    /****************************************************
     * Returns the current bearer token string 
     *
     * @return string
     */
    public function getBearer() {
        if ($this->expired())
        {
            $this->requestToken();
        }
        return self::API_TOKEN_TYPE ." ". $this->bearer;
    }
}

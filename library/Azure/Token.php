<?php

namespace Icinga\Module\Azure;

use Icinga\Module\Azure\Constants;

use restclient\restclient;



/**
 * Class Token
 *
 * This keeps track of the bearer token generation mechanism and
 * its expiring. Renews token if it expires.
 *
 */

class Token {

    /** var api  stores rest client api handling object*/
    private $api;
    
    /** var string bearer */
    private $bearer;

    /** var int expires */
    private $expires;

    /** @var string tenant_id */
    private $tenant_id;
    
    /** @var string subscription_id */
    private $subscription_id;

    /** @var string client_id */
    private $client_id;

    /** @var string client_secret */
    private $client_secret;


        
    public function __construct( $tenant_id, $subscription_id,
                                 $client_id, $client_secret)
    {
        
        $this->tenant_id       = $tenant_id;
        $this->subscription_id = $subscription_id;
        $this->client_id       = $client_id;
        $this->client_secret   = $client_secret;

        $api = new RestClient([
            'base_url' => API_LOGIN,
            'format'   => 'json',
            'headers'  => [
                'grant_type'    => 'client_credentials',
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'resource'      => API_ENDPT,
            ],
        ]);

        // no need for assertions as the RestClient constructor
        // cannot fail. 
        
        $this->requestToken();
    }

    /****************************************************
     * call the login api and generate a new bearer token
     * may throw exceptions if login api is unwilling. 
     * @return void
     */
    private function requestToken()
    {
        $result = $this->api->post($this->tenant_id+"/oauth2/token");


        // check if HTTP result code was 200 - OK
        if ($result->info->http_code != 200)
        {
            throw new Exception("Could not get bearer token. HTTP: " +
                                $result->info->http_code);
        }

        // check some assertions on the returned API data
        if ((not ( key_exists('token_type', $result) and
                   key_exists('access_token', $result) and
                   key_exists('expires_on', $result) and
                   key_exists('resource', $result))) or
            ( $result['token_type'] != API_TOKEN_TYPE ) or
            ( $result['resource']   != API_ENDPT))
                       
        {
            throw new Exception("Malformed result on token access " +
                                $result->response);
        }

        // store bearer token and expirery in object
        $this->bearer  = $result['access_token'];
        $this->expires = $result['expires_on'];
    }

    
    /****************************************************
     * tests if token has expired, true if invalid
     * @return bool
     */
    private function expired() {
        return ($this->expires <= time());
    }

    
    /****************************************************
     * Returns the current bearer token string 
     * @return string
     */
    public function getBearer() {
        if ($this->expired())
        {
            $this->requestToken();
        }
        return $this->bearer;
    }
}



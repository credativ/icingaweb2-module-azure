<?php

namespace Icinga\Module\Azure;

use Icinga\Exception\ConfigurationError;
use Icinga\Module\Vsphere\ManagedObject\TraversalHelper;

use restclient\restclient;

DEFINE("API_LOGIN",      "https://login.microsoftonline.com");
DEFINE("API_TOKEN_TYPE", "Bearer");
DEFINE("API_ENDPT",      "https://management.azure.com/");
  


/**
 * Class Api
 *
 * This is your main entry point when working with this library
 */


class Api
{
    /** @var token */
    private $token;

    /** @var string tenant_id */
    private $tenant_id;
    
    /** @var string subscription_id */
    private $subscription_id;

    /** @var string client_id */
    private $client_id;

    /** @var string client_secret */
    private $client_secret;

    /**
     * Api constructor.
     *
     * @param string $tenant
     * @param string $subscription
     * @param string $client
     * @param string $secret
     */
    public function __construct($tenant, $subscription, $client, $secret)
    {
        $this->tenant_id       = $tenant;
        $this->subscription_id = $subscription;
        $this->client_id       = $client;
        $this->client_secret   = $secret;

        $this->token = null;
        
        $this->getToken();
    }

    private function getToken()
    {
        if (($this->token = null) or ($this->token->expired()))
        {
            $this->token = new Token( $this->tenant,
                                      $this->subscription_id,
                                      $this->client_id,
                                      $this->client_secret );
        }
        return $this->token->getBearer();
    }

}

/**
 * Class Token
 *
 * This keeps track of the bearer token generation mechanism and
 * its expiring.
 *
 */

class Token {

    /** var string bearer */
    private $bearer;

    /** var int expires */
    private $expires;
    
        
    public function __construct($tenant, $subscription, $client, $secret)
    {
        $api = new RestClient([
            'base_url' => API_LOGIN,
            'format'   => 'json',
            'headers'  => [
                'grant_type'    => 'client_credentials',
                'client_id'     => $client,
                'client_secret' => $secret,
                'resource'      => API_ENDPT,
            ],
        ]);
        $result = $api->post($tenant+"/oauth2/token");


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
        return;
    }
}



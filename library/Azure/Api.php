<?php

namespace Icinga\Module\Azure;

use Icinga\Exception\ConfigurationError;
use Icinga\Module\Azure\Constants;
use Icinga\Module\Azure\Token;

use Icinga\Module\Azure\restclient\RestClient;

use Icinga\Application\Logger;
/**
 * Class Api
 *
 * This is your main entry point when working with this library
 */


class Api
{
    /** @var token stores the current token object if initialized */
    private $token;

    /** @var restc stores the restclient object we need for tha api access */
    private $restc;

    const API_ENDPT   = "https://management.azure.com/";
   
    /** @var subscription_id we need this for the REST client URLs to call */
    private $subscription_id;

    
    /** ***********************************************************************
     * Api object constructor.
     *
     * @param string $tenant_id
     * @param string $subscription_id
     * @param string $client_id
     * @param string $client_secret
     *
     * @return void
     */

    public function __construct( $tenant_id, $subscription_id,
                                 $client_id, $client_secret )
    {
        // store API credentials we need in future     
        $this->subscription_id = $subscription_id;
        
        // get bearer token for API access with given credentials 
        $this->token = new Token( $tenant_id,
                                  $subscription_id,
                                  $client_id,
                                  $client_secret,
                                  self::API_ENDPT);

        // initialize REST client for API access.
        // Please note: API endpoint != Token Auth API endpoint
        // while we create the REST client object, we don't store
        // the bearer token right now as this might fade out. Therefore
        // we have to insert ths each time we use the REST client object
        $this->restc = new RestClient([ 
            'base_url' => self::API_ENDPT,
            'format'   => "json",
        ]);
    }


    /** ***********************************************************************
     * encapsulate API REST call for method GET  to Azure cloud API 
     *
     * the URL to query
     * @param string $url  
     *
     * the API version for the data expected
     * @param string $api_version
     *
     * @return object
     */

    protected function call_get($url, $api_version)
    {
        $this->restc->set_option('headers',
                                 [
                                     'Authorization' => $this->token->getBearer(),
                                 ]);
        $this->restc->set_option('parameters',
                                 [
                                     'api-version'   => $api_version,
                                 ]);

        return $this->restc->get($url);
    }
    

    
    /** ***********************************************************************
     * reads all resource groups from Azure API and returns an array of
     * resource group objects
     *
     * may throw QueryException on HTTP error
     *
     * @return array of objects
     *
     */

    public function getResourceGroups()
    {
        Logger::info("Azure API: querying all resource groups");

        $result = $this->call_get('subscriptions/'.
                                  $this->subscription_id.
                                  '/resourceGroups',
                                  "2014-04-01");

        // check if things have gone wrong
        if ($result->info->http_code != 200)
        {
            Logger::error("Azure API: Could not get resource groups. HTTP: ".
                          $result->info->http_code);           
            throw new QueryException("Could not get resource groups. HTTP: ".
                                     $result->info->http_code);
        }

        // decode the JSON, take only the "value" array and return it
        return $result->decode_response()->value;
    }
    

    
    /** ***********************************************************************
     * reads all resources from a resource group from the Azure API and 
     * returns an array of resource objects
     *
     * may throw QueryException on HTTP error
     *
     * @return array of objects
     *
     */

    public function getResGroupResources($resource_group)
    {
        Logger::info("Azure API: querying resource group ".$resource_group);

        $result = $this->call_get('subscriptions/'.
                                  $this->subscription_id.
                                  '/resourceGroups/'.
                                  $resource_group.
                                  '/resources',
                                  "2017-05-10");
        // check if things have gone wrong
        if ($result->info->http_code != 200)
        {
            $error = sprintf(
                "Azure API: Could not get resource group '%s' resources. HTTP: %d",
                $resource_group, $result->info->http_code);
            Logger::error( $error );           
            throw new QueryException( $error );
        }

        // get result data from JSON into object $decoded
        return $result->decode_response()->value;
    }


    

    public function getAll()
    {
        Logger::info("Azure API: querying anything available");
        $rgs =  $this->getResourceGroups();

        $objects = (object) array();
        
        foreach( $rgs as $group)
        {
            // only items that have a valid provisioning state
            if ($group->properties->provisioningState == "Succeeded")
            {
                Logger::info("blablabla");
                $content = $this->getResGroupResources($group->name);
                Logger::info(print_r($content,true));
            }
        }
    }
    

}

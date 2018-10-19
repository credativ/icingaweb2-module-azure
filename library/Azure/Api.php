<?php

namespace Icinga\Module\Azure;

use Icinga\Exception\ConfigurationError;
use Icinga\Exception\QueryException;

use Icinga\Module\Azure\Token;

use Icinga\Module\Azure\restclient\RestClient;

use Icinga\Application\Logger;

/**
 * Class Api
 *
 * This is the abstract base class for your API endpoint.
 * It provides all methods to query the Azure API itself and 
 * all generic requests to object groups / lists in Azure.
 *
 * This abstract class needs to be extended by a class to put these single
 * object queries together and assemble a full answer to IcingaWeb2 Director.
 * This extension has to implement the 'getAll' method. 
 *
 */


abstract class Api
{

    /** @var token stores the current token object if initialized */
    private $token;

    /** @var restc stores the restclient object we need for tha api access */
    private $restc;

    const API_ENDPT   = "https://management.azure.com/";
   
    /** @var subscription_id we need this for the REST client URLs to call */
    private $subscription_id;


    /** ***********************************************************************
     * Walks through all or all desired resource groups and returns
     * an array of objects of the specific Azure Objects for IcingaWeb2 Director
     * 
     *
     * @param string $rgn 
     * a space separated list of resoureceGroup names to query or '' for all
     *
     * @return array of objects
     *
     */

    abstract public function getAll( $rgn );
    
    
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
     * @param string $rgn 
     * a space separated list of resoureceGroup names to query or '' for all
     *
     * @return array of objects
     *
     */

    protected function getResourceGroups( $rgn )
    {
        if ($rgn == '')
            Logger::info("Azure API: querying all resource groups");
        else
            Logger::info("Azure API: looking for resource groups '".
                         $rgn."'.");
        

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

        
        // decode the JSON, take only the "value" array
        $azure_groups = $result->decode_response()->value;

        // make shure we don't have an empty list returned from API
        if (count($azure_groups) == 0)
        {
            $error = "Azure API: Could not find any matching resource groups.";
            Logger::error( $error );           
            throw new QueryException( $error );
        }      
        
        // if parameter is empty string, just deliver all groups 
        if ($rgn == '')
            return $azure_groups;
        
        // if not, determine which groups are wanted
        $wanted = explode(" ", $rgn);
        $return_groups = array();

        // do we have duplicate entries ?
        // if so, present a warning and remove dupes
        if(count($wanted) > count(array_unique($wanted)))
        {
            Logger::warning("Azure API: there are duplicate entries in ".
                            "configured resource groups '".
                            $rgn."'. This might be a configuration error.");
            $wanted = array_unique($wanted);
        }

        // search all azure resourceGroups and pick these configured
        // keep track which where found already
        foreach($azure_groups as $ag)
            if ((count($wanted) > 0) and (in_array($ag->name, $wanted)))
            {
                $return_groups[] = $ag;
                unset($wanted[array_search($ag->name, $wanted)]);
            }

        // check if things have gone wrong, i.e. $wanted not empty,
        // but result list is empty
        
        if (count($return_groups) == 0)
        {
            $error = sprintf(
                "Azure API: Could not find matching resource groups for '%s'.",
                $rgn);
            Logger::error( $error );           
            throw new ConfigurationError( $error );
        }      

        // check if there is something left in $wanted
        // in that case, we did not find all configured resourceGroups
        // which is worth a warning.

        if (count($wanted)>0)
            Logger::warning("Azure API: could not find resource group(s) named '".
                            implode(" ", $wanted)."'. This might be a ".
                            "configuration error.");
        
        return $return_groups;
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

    protected function getResGroupResources($resource_group)
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



    /** ***********************************************************************
     * queries all VM from a resource group and returns a list
     *
     * @return array of objects
     *
     */   
   
    protected function getVirtualMachines($group)
    {
        $resource_group = $group->name;
        
        Logger::info("Azure API: querying virtual machines from resource group ".$resource_group);

        $result = $this->call_get('subscriptions/'.
                                  $this->subscription_id.
                                  '/resourceGroups/'.
                                  $resource_group.
                                  '/providers/Microsoft.Compute/virtualMachines',
                                  "2018-06-01");
        // check if things have gone wrong
        if ($result->info->http_code != 200)
        {
            $error = sprintf(
                "Azure API: Could not get virtual machines for resource group '%s'. HTTP: %d",
                $resource_group, $result->info->http_code);
            Logger::error( $error );           
            throw new QueryException( $error );
        }

        // get result data from JSON into object $decoded
        return $result->decode_response()->value;       
    }
    

    /** ***********************************************************************
     * queries all VM from a resource group and returns a list
     *
     * @return array of objects
     *
     */   
   
    protected function getVirtualMachineSizing($vm)
    {   
        Logger::info("Azure API: querying virtual machine sizing for vm ".
                     $vm->name);

        $result = $this->call_get($vm->id.'/vmSizes',"2018-06-01");
        
        // check if things have gone wrong
        if ($result->info->http_code != 200)
        {
            $error = sprintf(
                "Azure API: Could not get virtual machine sizes for vm '%s'. HTTP: %d",
                $vm->name, $result->info->http_code);
            Logger::error( $error );           
            throw new QueryException( $error );
        }

        // get result data from JSON into object $decoded
        $vmsizes =  $result->decode_response()->value;
      
        foreach($vmsizes as $s)
        {
            if ($s->name == $vm->properties->hardwareProfile->vmSize)
            {
                return $s;
            }
        }
        Logger::info("Azure API: querying virtual machine sizing for vm ".
                     $vm->name. "was not successfull.");
        return NULL;
    }
    

    
     /** ***********************************************************************
     * queries all disks from a resource group and returns a list
     *
     * @return array of objects
     *
     */   
   
    protected function getDisks($group)
    {
        $resource_group = $group->name;
        
        Logger::info("Azure API: querying disks from resource group ".$resource_group);

        $result = $this->call_get('subscriptions/'.
                                  $this->subscription_id.
                                  '/resourceGroups/'.
                                  $resource_group.
                                  '/providers/Microsoft.Compute/disks',
                                  "2017-03-30");
        // check if things have gone wrong
        if ($result->info->http_code != 200)
        {
            $error = sprintf(
                "Azure API: Could not get disks for resource group '%s'. HTTP: %d",
                $resource_group, $result->info->http_code);
            Logger::error( $error );           
            throw new QueryException( $error );
        }

        // get result data from JSON into object $decoded
        return $result->decode_response()->value;       
    }
    
     /** ***********************************************************************
     * queries all network interfaces from a resource group and returns a list
     *
     * @return array of objects
     *
     */   
   
    protected function getNetworkInterfaces($group)
    {
        $resource_group = $group->name;
        
        Logger::info("Azure API: retrieving network interfaces from resource group ".$resource_group);

        $result = $this->call_get('subscriptions/'.
                                  $this->subscription_id.
                                  '/resourceGroups/'.
                                  $resource_group.
                                  '/providers/Microsoft.Network/networkInterfaces',
                                  "2018-07-01");
        // check if things have gone wrong
        if ($result->info->http_code != 200)
        {
            $error = sprintf(
                "Azure API: Could not get network interfaces for resource group '%s'. HTTP: %d",
                $resource_group, $result->info->http_code);
            Logger::error( $error );           
            throw new QueryException( $error );
        }

        // get result data from JSON into object $decoded
        return $result->decode_response()->value;       
    }

     /** ***********************************************************************
     * queries all public IP adresses from a resource group and returns a list
     *
     * @return array of objects
     *
     */   
   
    protected function getPublicIpAddresses($group)
    {
        $resource_group = $group->name;
        
        Logger::info("Azure API: retrieving public IP addresses from resource group ".$resource_group);

        $result = $this->call_get('subscriptions/'.
                                  $this->subscription_id.
                                  '/resourceGroups/'.
                                  $resource_group.
                                  '/providers/Microsoft.Network/publicIPAddresses',
                                  "2018-07-01");
        // check if things have gone wrong
        if ($result->info->http_code != 200)
        {
            $error = sprintf(
                "Azure API: Could not get public IP adresses for resource group '%s'. HTTP: %d",
                $resource_group, $result->info->http_code);
            Logger::error( $error );           
            throw new QueryException( $error );
        }

        // get result data from JSON into object $decoded
        return $result->decode_response()->value;       
    }

     /** ***********************************************************************
     * queries all load balancers from a resource group and returns a list
     *
     * @return array of objects
     *
     */   
   
    protected function getLoadBalancers($group)
    {
        $resource_group = $group->name;
        
        Logger::info("Azure API: querying load balancers from resource group ".$resource_group);

        $result = $this->call_get('subscriptions/'.
                                  $this->subscription_id.
                                  '/resourceGroups/'.
                                  $resource_group.
                                  '/providers/Microsoft.Network/loadBalancers',
                                  "2018-07-01");
        // check if things have gone wrong
        if ($result->info->http_code != 200)
        {
            $error = sprintf(
                "Azure API: Could not get load balancers for resource group '%s'. HTTP: %d",
                $resource_group, $result->info->http_code);
            Logger::error( $error );           
            throw new QueryException( $error );
        }

        // get result data from JSON into object $decoded
        return $result->decode_response()->value;       
    }


    /** ***********************************************************************
     * queries all application gateways from a resource group and returns a list
     *
     * @return array of objects
     *
     */   
   
    protected function getApplicationGateways($group)
    {
        $resource_group = $group->name;
        
        Logger::info("Azure API: querying application gateways from resource group ".$resource_group);

        $result = $this->call_get('subscriptions/'.
                                  $this->subscription_id.
                                  '/resourceGroups/'.
                                  $resource_group.
                                  '/providers/Microsoft.Network/applicationGateways',
                                  "2018-07-01");
        // check if things have gone wrong
        if ($result->info->http_code != 200)
        {
            $error = sprintf(
                "Azure API: Could not get application gateways for resource group '%s'. HTTP: %d",
                $resource_group, $result->info->http_code);
            Logger::error( $error );           
            throw new QueryException( $error );
        }

        // get result data from JSON into object $decoded
        return $result->decode_response()->value;       
    }


    /** ***********************************************************************
     * queries all express route circuits from a resource group and returns a list
     *
     * @return array of objects
     *
     */   
   
    protected function getExpressRouteCircuits($group)
    {
        $resource_group = $group->name;
        
        Logger::info("Azure API: querying express route circuits from resource group ".$resource_group);

        $result = $this->call_get('subscriptions/'.
                                  $this->subscription_id.
                                  '/resourceGroups/'.
                                  $resource_group.
                                  '/providers/Microsoft.Network/expressRouteCircuits',
                                  "2018-04-01");
        // check if things have gone wrong
        if ($result->info->http_code != 200)
        {
            $error = sprintf(
                "Azure API: Could not get express route circuits for resource group '%s'. HTTP: %d",
                $resource_group, $result->info->http_code);
            Logger::error( $error );           
            throw new QueryException( $error );
        }

        // get result data from JSON into object $decoded
        return $result->decode_response()->value;       
    }

    
    /** ***********************************************************************
     * queries all DB for PostgreSQL from a resource group and returns a list
     *
     * @return array of objects
     *
     */   
   
    protected function getDbForPostgreSQL($group)
    {
        $resource_group = $group->name;
        
        Logger::info("Azure API: querying Microsoft.DbForPostgreSQL from resource group ".$resource_group);

        $result = $this->call_get('subscriptions/'.
                                  $this->subscription_id.
                                  '/resourceGroups/'.
                                  $resource_group.
                                  '/providers/Microsoft.DBforPostgreSQL/servers',
                                  "2017-12-01");
        // check if things have gone wrong
        if ($result->info->http_code != 200)
        {
            $error = sprintf(
                "Azure API: Could not get Microsoft.DbForPostgreSQL for resource group '%s'. HTTP: %d",
                $resource_group, $result->info->http_code);
            Logger::error( $error );           
            throw new QueryException( $error );
        }

        // get result data from JSON into object $decoded
        return $result->decode_response()->value;       
    }
}

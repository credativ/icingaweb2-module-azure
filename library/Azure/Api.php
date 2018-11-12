<?php
/** ***************************************************************************
 * @author Peter Dreuw <peter.dreuw@credativ.de>
 * @copyright Copyright (c) 2018 creadtiv GmbH
 * @license https://github.com/credativ/icingaweb2-module-azure/blob/master/LICENSE MIT License
 *
 *
 */
namespace Icinga\Module\Azure;

use Icinga\Exception\ConfigurationError;
use Icinga\Exception\QueryException;
use Icinga\Application\Logger;


use Icinga\Module\Azure\Token;
use Icinga\Module\Azure\restclient\RestClient;


/** ***************************************************************************
 * Class Api
 *
 * This is the abstract base class for your API endpoint.
 * It provides all methods to query the Azure API itself and 
 * all generic requests to object groups / lists in Azure.
 *
 * This abstract class needs to be extended by a class to put these single
 * object queries together and assemble a full answer to IcingaWeb2 Director.
 * This extension can implement the public 'getAll' method or use the default. 
 * Furthermore, the concept of the gettAll method should be iterating through 
 * the Azure resource group names and calling the scanResourceGroup method for 
 * each one. This is the second abstract method to be implemented.
 * 
 */


abstract class Api
{

    /** 
     * stores the current token object if initialized
     * @property string token
     */
    private $token;

    /**
     * stores the restclient object we need for tha api access 
     * @property object restc
     */
    private $restc;

    /**
     * we need this for the REST client URLs to call 
     * @property string subscription_id
     */
    protected $subscription_id;


    /** 
     * log message for getAll 
     * should be redifined in subclass 
     *
     * @staticvar string MSG_LOG_GET_ALL
     */
    protected const MSG_LOG_GET_ALL =
                                    "Azure API: Call to getAll primitive".
                                    "- should never be seen!";

    /**
     * array of field names to be returned by implementation.
     *
     * @staticvar array FIELDS_RETURNED
     *
     * empty for abstract base class. 
     */
    public const FIELDS_RETURNED = array();


    /** 
     * URL of the Microsoft Azure management API endpoint
     *
     * @staticvar string API_ENDPT
     */
    public const API_ENDPT   = "https://management.azure.com/";
   
   

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

    public function getAll( $rgn )
    {
        // log individual log message as constant should be redefined
        // in subclasses
        
        Logger::info(static::MSG_LOG_GET_ALL);
        $rgs =  $this->getResourceGroups( $rgn );

        $objects = array();

        // walk through any resourceGroups
        foreach( $rgs as $group )  
        {          
            $objects = $objects + $this->scanResourceGroup( $group );
        }
        return $objects;
    }


    
    /** ***********************************************************************
     * takes all information on specific object type from a given resource group
     * and returns it in the format IcingaWeb2 Director expects
     *
     * @param string $group
     * name of the resource group in question
     *
     * @return array of objects
     *
     */
    
    abstract protected function scanResourceGroup( $group );

        
    /** ***********************************************************************
     * Api object constructor.
     *
     * @param string $tenant_id
     * @param string $subscription_id
     * @param string $client_id
     * @param string $client_secret
     * @param string $proxy
     * @param integer con_timeout
     * @param integer timeout
     *
     * @return void
     */

    public function __construct( $tenant_id, $subscription_id,
                                 $client_id, $client_secret,
                                 $proxy = '', $con_timeout = 0,
                                 $timeout = 0 )
    {        
        // store API credentials we need in future     
        $this->subscription_id = $subscription_id;
        
        // get bearer token for API access with given credentials 
        $this->token = new Token( $tenant_id,
                                  $client_id, $client_secret,
                                  self::API_ENDPT,
                                  $proxy, $con_timeout, $timeout );

        // initialize REST client for API access.
        // Please note: API endpoint != Token Auth API endpoint
        // while we create the REST client object, we don't store
        // the bearer token right now as this might fade out. Therefore
        // we have to insert ths each time we use the REST client object
        $this->restc = new RestClient([ 
            'base_url' => self::API_ENDPT,
            'curl_options' => [
                CURLOPT_PROXY          => $proxy,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => $con_timeout,
            ],
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
        if ($result->errno != CURLE_OK)
            $this->raiseCurlError( $result->error,
                                   "querying resource groups");

        if ($result->info->http_code != 200)
        {
            Logger::error("Azure API: Could not get resource group(s). HTTP: ".
                          $result->info->http_code);           
            throw new QueryException("Could not get resource group(s). HTTP: ".
                                     $result->info->http_code);
        }

        
        // decode the JSON, take only the "value" array
        $azure_groups = $result->decode_response()->value;

        // make shure we don't have an empty list returned from API
        if (count($azure_groups) == 0)
        {
            $error = "Azure API: Could not find any matching resource group.";
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
                "Azure API: Could not find matching resource group for '%s'.",
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
     * reads all subscriptions from Azure API and returns an array of
     * subscription objects
     *
     * may throw QueryException on HTTP error
     *
     * @return array of objects
     *
     */

    protected function getSubscriptions( )
    {
        Logger::info("Azure API: querying all subscriptions available");
        
        $result = $this->call_get('subscriptions', "2014-04-01");

        // check if things have gone wrong
        if ($result->errno != CURLE_OK)
            $this->raiseCurlError( $result->error,
                                   "querying subscriptions");

        if ($result->info->http_code != 200)
        {
            Logger::error("Azure API: Could not get subscription(s). HTTP: ".
                          $result->info->http_code);           
            throw new QueryException("Could not get subscription(s). HTTP: ".
                                     $result->info->http_code);
        }

        
        // decode the JSON, take only the "value" array
        $azure_subs = $result->decode_response()->value;

        // make shure we don't have an empty list returned from API
        if (count($azure_subs) == 0)
        {
            $error = "Azure API: Could not find any subscriptions.";
            Logger::error( $error );           
            throw new QueryException( $error );
        }            
        
        return $azure_subs;
    }
    

    /** ***********************************************************************
     * reads all resources from a resource group from the Azure API and 
     * returns an array of resource objects
     *
     * may throw QueryException on HTTP error
     *
     * @param string $resource_group
     * name of resoureceGroup to query
     *
     * @return array of objects
     *
     */

    protected function getResGroupResources($resource_group)
    {
        Logger::info("Azure API: querying resource group '".$resource_group."'");

        $result = $this->call_get('subscriptions/'.
                                  $this->subscription_id.
                                  '/resourceGroups/'.
                                  $resource_group.
                                  '/resources',
                                  "2017-05-10");
        // check if things have gone wrong
        if ($result->errno != CURLE_OK)
            $this->raiseCurlError( $result->error,
                            "querying resource group resources");

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
     * @param object $group 
     * resoureceGroup object to work on
     *
     * @return array of objects
     *
     */   
   
    protected function getVirtualMachines($group)
    {
        $resource_group = $group->name;
        
        Logger::info("Azure API: querying virtual machines from resource group '".
            $resource_group."'");

        $result = $this->call_get('subscriptions/'.
                                  $this->subscription_id.
                                  '/resourceGroups/'.
                                  $resource_group.
                                  '/providers/Microsoft.Compute/virtualMachines',
                                  "2018-06-01");
        // check if things have gone wrong
        if ($result->errno != CURLE_OK)
            $this->raiseCurlError( $result->error,
                            "querying virtual machines");

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
     * queries the sizing for a given VM and returns a sizing object
     *
     * @param object $vm
     * virtualMachine object to retrieve resource sizing data for
     *
     * @return array of objects
     *
     */   
   
    protected function getVirtualMachineSizing($vm)
    {   
        Logger::info("Azure API: querying virtual machine sizing for vm '".
                     $vm->name."'");

        $result = $this->call_get($vm->id.'/vmSizes',"2018-06-01");
        
        // check if things have gone wrong
        if ($result->errno != CURLE_OK)
            $this->raiseCurlError( $result->error,
                            "querying virtual machine sizes");

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
        Logger::info("Azure API: querying virtual machine sizing for vm '".
                     $vm->name."' was not successfull.");
        return NULL;
    }
    

    
    /** ***********************************************************************
     * queries all disks from a resource group and returns a list
     *
     * @param object $group 
     * resoureceGroup object to work on
     *
     * @return array of objects
     *
     */   
   
    protected function getDisks($group)
    {
        $resource_group = $group->name;
        
        Logger::info("Azure API: querying disks from resource group '".
                     $resource_group."'");

        $result = $this->call_get('subscriptions/'.
                                  $this->subscription_id.
                                  '/resourceGroups/'.
                                  $resource_group.
                                  '/providers/Microsoft.Compute/disks',
                                  "2017-03-30");
        
        // check if things have gone wrong
        if ($result->errno != CURLE_OK)
            $this->raiseCurlError( $result->error,
                            "querying disks");

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
     * @param object $group 
     * resoureceGroup object to work on
     *
     * @return array of objects
     *
     */   
   
    protected function getNetworkInterfaces($group)
    {
        $resource_group = $group->name;
        
        Logger::info("Azure API: retrieving network interfaces from resource group '".
                     $resource_group."'");

        $result = $this->call_get('subscriptions/'.
                                  $this->subscription_id.
                                  '/resourceGroups/'.
                                  $resource_group.
                                  '/providers/Microsoft.Network/networkInterfaces',
                                  "2018-07-01");
        // check if things have gone wrong
        if ($result->errno != CURLE_OK)
            $this->raiseCurlError( $result->error,
                            "querying network interfaces");

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
     * @param object $group 
     * resoureceGroup object to work on
     *
     * @return array of objects
     *
     */   
   
    protected function getPublicIpAddresses($group)
    {
        $resource_group = $group->name;
        
        Logger::info("Azure API: retrieving public IP addresses from resource group '".
                     $resource_group."'");

        $result = $this->call_get('subscriptions/'.
                                  $this->subscription_id.
                                  '/resourceGroups/'.
                                  $resource_group.
                                  '/providers/Microsoft.Network/publicIPAddresses',
                                  "2018-07-01");
        // check if things have gone wrong
        if ($result->errno != CURLE_OK)
            $this->raiseCurlError( $result->error,
                            "querying public ip addresses");

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
     * @param object $group 
     * resoureceGroup object to work on
     *
     * @return array of objects
     *
     */     
    protected function getLoadBalancers($group)
    {
        $resource_group = $group->name;
        
        Logger::info("Azure API: querying load balancers from resource group '".
                     $resource_group."'");

        $result = $this->call_get('subscriptions/'.
                                  $this->subscription_id.
                                  '/resourceGroups/'.
                                  $resource_group.
                                  '/providers/Microsoft.Network/loadBalancers',
                                  "2018-07-01");
        // check if things have gone wrong
        if ($result->errno != CURLE_OK)
            $this->raiseCurlError( $result->error,
                            "querying load balancers");

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
     * @param object $group 
     * resoureceGroup object to work on
     *
     * @return array of objects
     *
     */   
  
    protected function getApplicationGateways($group)
    {
        $resource_group = $group->name;
        
        Logger::info("Azure API: querying application gateways from resource group '".
                     $resource_group."'");

        $result = $this->call_get('subscriptions/'.
                                  $this->subscription_id.
                                  '/resourceGroups/'.
                                  $resource_group.
                                  '/providers/Microsoft.Network/applicationGateways',
                                  "2018-07-01");
        // check if things have gone wrong
        if ($result->errno != CURLE_OK)
            $this->raiseCurlError( $result->error,
                            "querying application gateways");

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
     * @param object $group 
     * resoureceGroup object to work on
     *
     * @return array of objects
     *
     */   
   
    protected function getExpressRouteCircuits($group)
    {
        $resource_group = $group->name;
        
        Logger::info("Azure API: querying express route circuits from resource group '".
                     $resource_group."'");

        $result = $this->call_get('subscriptions/'.
                                  $this->subscription_id.
                                  '/resourceGroups/'.
                                  $resource_group.
                                  '/providers/Microsoft.Network/expressRouteCircuits',
                                  "2018-04-01");
        // check if things have gone wrong
        if ($result->errno != CURLE_OK)
            $this->raiseCurlError( $result->error,
                            "querying express route circuits");

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
     * @param object $group 
     * resoureceGroup object to work on
     *
     * @return array of objects
     *
     */   
   
    protected function getDbForPostgreSQL($group)
    {
        $resource_group = $group->name;
        
        Logger::info("Azure API: querying Microsoft.DbForPostgreSQL from resource group '".
                     $resource_group."'");

        $result = $this->call_get('subscriptions/'.
                                  $this->subscription_id.
                                  '/resourceGroups/'.
                                  $resource_group.
                                  '/providers/Microsoft.DBforPostgreSQL/servers',
                                  "2017-12-01");
        // check if things have gone wrong
        if ($result->errno != CURLE_OK)
            $this->raiseCurlError( $result->error,
                            "querying Microsoft.DbForPostgreSQL");

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


    /** ***********************************************************************
     * queries the Azure metrics definitions list for a given object 
     * and returns the list as a comma seperated string
     *
     * @param object $group 
     * resoureceGroup object to work on
     *
     * @param string classname
     * resoureceGroup object class to retrieve metrics list for
     *
     * @return string
     *
     */   
   
    protected function getMetricDefinitionsList($id)
    {
        Logger::info("Azure API: querying Metric Definitions List for '".
                     $id."'.");

        $result = $this->call_get($id.
                                  '/providers/microsoft.insights/metricDefinitions',
                                  "2018-01-01");
        // check if things have gone wrong
        if ($result->errno != CURLE_OK)
            $this->raiseCurlError( $result->error,
                            "querying Azure Metric Definitions list");

        if ($result->info->http_code != 200)
        {
            $error = sprintf(
                "Azure API: Could not get Azure Metric Definitions on '%s'. HTTP: %d",
                $id, $result->info->http_code);
            Logger::error( $error );           
            throw new QueryException( $error );
        }

        // get result data from JSON into object $decoded
        // and create return string

        $retval = "";

        foreach( $result->decode_response()->value as $metric)
        {
            $retval = $retval.','.$metric->name->value;
        }

        return ltrim($retval, ',');
    }


    /** ***********************************************************************
     * logs and raises an error for any CURL operation that went wrong
     *
     * @param string $errormsg
     * the CURL error string
     *
     * @param string $text
     * description of action in progress when CURL req went wrong
     *
     * @return void
     *
     */

    protected function raiseCurlError( $errormsg, $text )
    {
        Logger::info("test");
        $msg = sprintf("Azure API: Got CURL error '%s' while %s.",
                       $errormsg, $text);
        Logger::error( $msg );
        throw new QueryException($msg);
    }
}

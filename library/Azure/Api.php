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



    /** ***********************************************************************
     * queries all VM from a resource group and returns a list
     *
     * @return array of objects
     *
     */   
   
    public function getVirtualMachines($group)
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
   
    public function getVirtualMachineSizing($vm)
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
   
    public function getDisks($group)
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
   
    public function getNetworkInterfaces($group)
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
   
    public function getPublicIpAddresses($group)
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
   
    public function getLoadBalancers($group)
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
   
    public function getApplicationGateways($group)
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
     * takes all information on virtual machines from a resource group and 
     * returns it in the format IcingaWeb2 Director expects
     *
     * @return array of objects
     *
     */

    public function scanVMResource($group)
    {
        // only items that have a valid provisioning state
        if ($group->properties->provisioningState != "Succeeded")
        {
            Logger::info("Azure API: Resoure group ".$group->name.
                         " invalid provisioning state.");
            return array();
        }

        // get data needed
        $virtual_machines   = $this->getVirtualMachines($group);
        // $disks              = $this->getDisks($group);
        $network_interfaces = $this->getNetworkInterfaces($group);
        $public_ip          = $this->getPublicIpAddresses($group);

        $objects = array();

        foreach($virtual_machines as $current)
        {
            // skip anything not provisioned.
            if ($current->properties->provisioningState == "Succeeded")
            {
                $object = (object) [
                    'name'           => $current->name,
                    'id'             => $current->id,
                    'location'       => $current->location,
                    'osType'         => (
                        property_exists($current->properties->storageProfile->osDisk,
                                        'osType')?
                        $current->properties->storageProfile->osDisk->osType : ""
                    ),
                    'osDiskName'     => (
                        property_exists($current->properties->storageProfile->osDisk,'name')?
                        $current->properties->storageProfile->osDisk->name : ""
                    ),
                    'dataDisks'      => count($current->properties->storageProfile->dataDisks),
                    'privateIP'      => "",
                    'network_interfaces_count' => 0,
                ];

                // scan network interfaces and fint the ones belonging to
                // the current vm

                foreach($network_interfaces as $interf)
                {
                    // In Azure, a network interface may not have a VM attached :-(
                    // and make shure, we match the current vm
                    if (
                        property_exists($interf->properties, 'virtualMachine') and
                        $interf->properties->virtualMachine->id == $current->id )
                    {
                        
                        $object->network_interfaces_count++;
                        
                        $object->privateIP =
                                           $interf->properties->
                                           ipConfigurations[0]->properties->
                                           privateIPAddress;
                        // check, if this interface has got a public IP address
                        if (property_exists(
                            $interf->properties->ipConfigurations[0]->properties,
                            'publicIPAddress'))
                        {
                            foreach($public_ip as $pubip)
                            {
                                if (($interf->properties->ipConfigurations[0]->properties->publicIPAddress->id ==
                                     $pubip->id) and
                                    (property_exists($pubip->properties,'ipAddress')))
                                {
                                    $object->publicIP = $pubip->properties->ipAddress;
                                }
                                else
                                    if (!property_exists($object, 'publicIP'))
                                        $object->publicIP = "";
                            }
                        }
                    }
                }  // end foreach network interfaces


                // get the sizing done
                $vmsize =  $this->getVirtualMachineSizing($current);

                if ($vmsize == NULL)
                {
                    $object->cores = NULL;
                    $object->resourceDiskSizeInMB = NULL;
                    $object->memoryInMB = NULL;
                    $object->maxdataDiscCount = NULL;
                }
                else
                {
                    $object->cores = $vmsize->numberOfCores;
                    $object->resourceDiskSizeInMB = $vmsize->resourceDiskSizeInMB;
                    $object->memoryInMB = $vmsize->memoryInMB;
                    $object->maxDataDiscCount = $vmsize->maxDataDiskCount;
                }

                // add this VM to the list.
                $objects[] = $object;
            }
        }
        
        return $objects;
    }


    /** ***********************************************************************
     * takes all information on load balancers from a resource group and 
     * returns it in the format IcingaWeb2 Director expects
     *
     * @return array of objects
     *
     */

    public function scanLBResource($group)
    {
        // only items that have a valid provisioning state
        if ($group->properties->provisioningState != "Succeeded")
        {
            Logger::info("Azure API: Resoure group ".$group->name.
                         " invalid provisioning state.");
            return array();
        }

        // get data needed
        $load_balancers = $this->getLoadBalancers($group);
        $public_ip      = $this->getPublicIpAddresses($group);

        
        $objects = array();

        foreach($load_balancers as $current)
        {
            $object = (object) [
                'name'              => $current->name,
                'id'                => $current->id,
                'location'          => $current->location,
                'provisioningState' => $current->properties->provisioningState,
                'frontEndPublicIP'  => NULL,
            ];

            // search for the public ip               
            foreach($public_ip as $pubip)
            {
                if (($current->properties->frontendIPConfigurations[0]->
                     properties->publicIPAddress->id == $pubip->id)
                    and
                    (property_exists($pubip->properties,'ipAddress')))
                {
                    $object->frontEndPublicIP = $pubip->properties->ipAddress;
                }
            }
   
            // add this VM to the list.
            $objects[] = $object;
        }
        return $objects;
    }

    
    /** ***********************************************************************
     * takes all information on application gateways from a resource group and 
     * returns it in the format IcingaWeb2 Director expects
     *
     * @return array of objects
     *
     */

    public function scanAppGWResource($group)
    {
        // only items that have a valid provisioning state
        if ($group->properties->provisioningState != "Succeeded")
        {
            Logger::info("Azure API: Resoure group ".$group->name.
                         " invalid provisioning state.");
            return array();
        }

        // get data needed
        $app_gateways = $this->getApplicationGateways($group);
        $public_ip    = $this->getPublicIpAddresses($group);

        
        $objects = array();

        foreach($app_gateways as $current)
        {
            $object = (object) [
                'name'              => $current->name,
                'id'                => $current->id,
                'location'          => $current->location,
                'provisioningState' => $current->properties->provisioningState,
                'frontEndPublicIP'  => NULL,
                'frontEndPrivateIP' => (
                    (property_exists($current->properties,
                                     'frontendIPConfigurations') and
                     property_exists($current->properties->
                                     frontendIPConfigurations[0]->properties,
                                     'privateIPAddress')) ?
                    $current->properties->frontendIPConfigurations[0]->
                    properties->privateIPAddress : NULL
                ),
                'operationalState'  => $current->properties->operationalState,
                'frontEndPort'      => (
                    property_exists($current, 'frontendPorts') ?
                    $current->frontendPorts[0]->properties->port : NULL
                ),
                'httpFrontEndPort'  => (
                    (property_exists($current, 'httpListeners') and
                     property_exists($current->httpListeners->properties, 'frontendPort')) ?
                    $current->httpListeners->properties->frontendPort : NULL
                ),
                'enabledHTTP2'      => $current->properties->enableHttp2,
                'enabledWAF'        => (
                    (property_exists($current, 'webApplicationFirewallConfiguration') and
                     property_exists($current->properties->webApplicationFirewallConfiguration,'enabled'))?
                    $current->properties->webApplicationFirewallConfiguration->enabled : FALSE
                ),
            ];

            // search for the public ip, but only if there is one configured. 
            if (property_exists($current->properties,'frontendIPConfigurations')
                and
                property_exists($current->properties->frontendIPConfigurations[0]->properties,
                                'publicIPAddress'))
            {
                foreach($public_ip as $pubip)
                {
                    if (($current->properties->frontendIPConfigurations[0]->
                         properties->publicIPAddress->id == $pubip->id)
                        and
                        (property_exists($pubip->properties,'ipAddress')))
                    {
                        $object->frontEndPublicIP = $pubip->properties->ipAddress;
                    }
                }
            }
            // add this VM to the list.
            $objects[] = $object;
        }
        return $objects;
    }

    
    public function getAllVM()
    {
        Logger::info("Azure API: querying any VM available");
        $rgs =  $this->getResourceGroups();

        $objects = array();

        // walk through any resourceGroups
        foreach( $rgs as $group )  
        {          
            $objects = $objects + $this->scanVMResource( $group );
        }
        return $objects;
    }

    
    public function getAllLB()
    {
        Logger::info("Azure API: querying any LoadBalancer available");
        $rgs =  $this->getResourceGroups();

        $objects = array();

        // walk through any resourceGroups
        foreach( $rgs as $group )  
        {          
            $objects = $objects + $this->scanLBResource( $group );
        }
        return $objects;
    }

    public function getAllAppGW()
    {
        Logger::info("Azure API: querying any Application Gateway available");
        $rgs =  $this->getResourceGroups();

        $objects = array();

        // walk through any resourceGroups
        foreach( $rgs as $group )  
        {          
            $objects = $objects + $this->scanAppGWResource( $group );
        }

        return $objects;
    }

    
}

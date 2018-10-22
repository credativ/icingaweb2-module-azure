<?php

namespace Icinga\Module\Azure;

use Icinga\Exception\ConfigurationError;
use Icinga\Exception\QueryException;
use Icinga\Application\Logger;

use Icinga\Module\Azure\Api;



/**
 * Class Load Balancers
 *
 * This is your main entry point when querying virtual machines from 
 * Azure API. 
 *
 */


class LoadBalancers extends Api
{

    /** Log Message for getAll **/
    protected const
        MSG_LOG_GET_ALL = "Azure API: querying any LoadBalancer in configured resource groups";
    
    /** ***********************************************************************
     * takes all information on load balancers from a resource group and 
     * returns it in the format IcingaWeb2 Director expects
     *
     * @return array of objects
     *
     */

    public function scanResourceGroup($group)
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
}
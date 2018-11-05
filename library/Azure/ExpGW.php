<?php

namespace Icinga\Module\Azure;

use Icinga\Exception\ConfigurationError;
use Icinga\Exception\QueryException;
use Icinga\Application\Logger;

use Icinga\Module\Azure\Api;

/**
 * Class Api
 *
 * This is your main entry point when querying Express Route Circuit from 
 * Azure API. 
 *
 */


class ExpGW extends Api
{
    /** Log Message for getAll **/
    protected const
        MSG_LOG_GET_ALL =
        "Azure API: querying any Express Route Circuits in configured resource groups.";

    
    /** ***********************************************************************
     * takes all information on express route circuits from a resource group and 
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
        $exp_routes = $this->getExpressRouteCircuits($group);

        $objects = array();

        foreach($exp_routes as $current) 
        {
            $object = (object) [
                'name'                     => $current->name,
                'subscriptionId'           => $this->subscription_id,
                'id'                       => $current->id,
                'location'                 => $current->location,
                'provisioningState'        => $current->properties->provisioningState,
                'peeringLocation'          => NULL,
                'serviceProviderName'      => NULL,
                'bandwidthInMbps'          => NULL,
                'circuitProvisioningState' => $current->properties->circuitProvisioningState,
                'allowClassicOperations'   => $current->properties->allowClassicOperations,
                'serviceProviderProvisioningState' =>
                $current->properties->serviceProviderProvisioningState,
            ];

            if (property_exists($current->properties,'serviceProviderProperties'))
            {
                if (property_exists(
                        $current->properties->serviceProviderProperties,
                        'peeringLocation'))
                    $object->peeringLocation = $current->properties->
                                             serviceProviderProperties->
                                             peeringLocation;
                
                if (property_exists(
                        $current->properties->serviceProviderProperties,
                        'serviceProviderName'))
                    $object->peeringLocation = $current->properties->
                                             serviceProviderProperties->
                                             serviceProviderName;

                if (property_exists(
                        $current->properties->serviceProviderProperties,
                        'bandwidthInMbps'))
                    $object->peeringLocation = $current->properties->
                                             serviceProviderProperties->
                                             bandwidthInMbps;
            }

            // add this VM to the list.
            $objects[] = $object;
        }
        return $objects;
    }
}

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
    /** 
     * Log Message for getAll 
     *
     * @staticvar string MSG_LOG_GET_ALL
     */
    protected const
        MSG_LOG_GET_ALL =
        "Azure API: querying any Express Route Circuits in configured resource groups.";

    /**
     * array of field names to be returned by implementation.
     *
     * @staticvar array FIELDS_RETURNED
     */
    public const FIELDS_RETURNED = array(
        'name',
        'subscriptionId',
        'id',
        'location',
        'provisioningState',
        'bandwidthInMbps',
        'circuitProvisioningState',
        'allowClassicOperations',
        'peeringLocation',
        'serviceProviderName',
        'serviceProviderProvisioningState',
        'metricDefinitions',
    );


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
            // get metric definitions list
            $metrics = $this->getMetricDefinitionsList($current->id);

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
                'metricDefinitions'        => $metrics,
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
                    $object->serviceProviderName = $current->properties->
                                                 serviceProviderProperties->
                                                 serviceProviderName;

                if (property_exists(
                        $current->properties->serviceProviderProperties,
                        'bandwidthInMbps'))
                    $object->bandwidthInMbps = $current->properties->
                                             serviceProviderProperties->
                                             bandwidthInMbps;
            }

            // add this VM to the list.
            $objects[] = $object;
        }
        return $objects;
    }
}

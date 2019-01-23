<?php
/** ***************************************************************************
 * @author Peter Dreuw <peter.dreuw@credativ.de>
 * @copyright Copyright (c) 2018, 2019 credativ GmbH
 * @license https://github.com/credativ/icingaweb2-module-azure/blob/master/LICENSE MIT License
 *
 *
 */
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
        "Azure API: querying any Express Route Circuits ".
        "in configured resource groups.";

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
        'type',
        'provisioningState',
        'serviceProviderBandwidthInMbps',
        'bandwidthInGbps',
        'circuitProvisioningState',
        'allowClassicOperations',
        'peeringLocation',
        'serviceProviderName',
        'serviceProviderProvisioningState',
        'metricDefinitions',
        'allowGlobalReach',
        'etag',
        'expressRoutePort',
        'gatewayManagerEtag',
        'serviceKey',
        'serviceProviderNotes',
        'stag',
        'skuName',
        'skuTier',
        'skuFamily',
        'tags',
        'authorizations',
        'peerings',
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
        // log if there are resource groups with surprising provisioning state
        if ($group->properties->provisioningState != "Succeeded")
        {
            Logger::info("Azure API: Resoure group ".$group->name.
                         " invalid provisioning state.");
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
                'type'                     => $current->type,
                'id'                       => $current->id,
                'location'                 => $current->location,
                'etag'                     => $current->etag,
                'provisioningState'        =>
                $current->properties->provisioningState,
                'peeringLocation'          => NULL,
                'serviceProviderName'      => NULL,
                'serviceProviderBandwidthInMbps' => NULL,
                'bandwidthInGbps'          => (
                    property_exists($current->properties,
                                    'bandwidthInGbps') ?
                    $current->properties->bandwidthInGbps : NULL
                ),
                'circuitProvisioningState' =>
                $current->properties->circuitProvisioningState,
                'allowClassicOperations'   =>
                $current->properties->allowClassicOperations,
                'serviceProviderProvisioningState' =>
                $current->properties->serviceProviderProvisioningState,
                'metricDefinitions'        => $metrics,
                'allowGlobalReach'         => (
                    property_exists($current->properties, 'allowGlobalReach') ?
                    $current->properties->allowGlobalReach : NULL
                ),
                'expressRoutePort'         => (
                    property_exists($current->properties, 'expressRoutePort') ?
                    $current->properties->expressRoutePort : NULL
                ),
                'gatewayManagerEtag'       => (
                    property_exists(
                        $current->properties, 'gatewayManagerEtag') ?
                    $current->properties->gatewayManagerEtag : NULL
                ),
                'serviceKey'               => (
                    property_exists($current->properties, 'serviceKey') ?
                    $current->properties->serviceKey : NULL
                ),
                'serviceProviderNotes'     => (
                    property_exists($current->properties,
                                    'serviceProviderNotes') ?
                    $current->properties->serviceProviderNotes : NULL
                ),
                'stag'                     => (
                    property_exists($current->properties, 'stag') ?
                    $current->properties->stag : NULL
                ),
                'skuName'                  => NULL,
                'skuTier'                  => NULL,
                'skuFamily'                => NULL,
                'tags'                     => "{}",
                'authorizations'           => NULL,
                'peerings'                 => NULL,
            ];

            if ( property_exists( $current->properties, 'authorizations' ))
            {
                $authstr = "";

                foreach( $current->properties->authorizations as $auth )
                {
                    $authstr = $authstr.','.$auth->name;
                }
                $object->authorizations = ltrim($authstr, ',');
            }

            if ( property_exists( $current->properties, 'peerings' ))
            {
                $peerstr = "";

                foreach( $current->properties->peerings as $peering )
                {
                    $peerstr = $peerstr.','.$peering->name;
                }
                $object->peerings = ltrim($peerstr, ',');
            }

            if ( property_exists( $current, 'tags' ) )
            {
                $object->tags = json_encode( $current->tags );
            }

            if (
                property_exists(
                    $current->properties,'serviceProviderProperties')
            )
            {
                if (
                    property_exists(
                        $current->properties->serviceProviderProperties,
                        'peeringLocation')
                )
                    $object->peeringLocation = $current->properties->
                                             serviceProviderProperties->
                                             peeringLocation;

                if (
                    property_exists(
                        $current->properties->serviceProviderProperties,
                        'serviceProviderName')
                )
                    $object->serviceProviderName = $current->properties->
                                                 serviceProviderProperties->
                                                 serviceProviderName;

                if (
                    property_exists(
                        $current->properties->serviceProviderProperties,
                        'bandwidthInMbps')
                )
                    $object->
                        serviceProviderBandwidthInMbps =
                        $current->properties->serviceProviderProperties->
                        bandwidthInMbps;
            }

            if ( property_exists( $current, 'sku' ) )
            {
                $object->skuName = (
                    property_exists( $current->sku, 'name') ?
                    $current->sku->name : NULL
                );

                $object->skuTier = (
                    property_exists( $current->sku, 'tier') ?
                    $current->sku->tier : NULL
                );

                $object->skuFamily = (
                    property_exists( $current->sku, 'family') ?
                    $current->sku->family : NULL
                );
            }

            // add this VM to the list.
            $objects[] = $object;
        }
        return $objects;
    }
}

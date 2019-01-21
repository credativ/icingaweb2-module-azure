<?php
/** ***************************************************************************
 * @author Peter Dreuw <peter.dreuw@credativ.de>
 * @copyright Copyright (c) 2019 credativ GmbH
 * @license https://github.com/credativ/icingaweb2-module-azure/blob/master/LICENSE MIT License
 *
 *
 */
namespace Icinga\Module\Azure;

use Icinga\Exception\ConfigurationError;
use Icinga\Exception\QueryException;
use Icinga\Application\Logger;

use Icinga\Module\Azure\Api;
use Icinga\Module\Azure\ProvidedHook\Director\ImportSource;

/**
 * Class ContainerRegistries
 *
 * This class handles aggregating the Azure API data into IcingaWeb2 Director.
 *
 * This module works somewhat different to most others. Azure has two "list"
 * calls for container registries. One is catch all and one is list by resource
 * group. Therefore we use either list if this call is not bound to any
 * resource group or list by resource group if we got resource groups to query
 * configured.
 * To archieve this behavior, we need to override the standard getAll() from
 * base class to distinct here. To simplify code, we need another function
 * which assembles the resulting object per registry found. This is normally
 * done in the scanResourceGroup($group) method directly.
 *
 */


class ContainerRegistries extends Api
{

    /** 
     * Log Message for getAll 
     *
     * @staticvar string MSG_LOG_GET_ALL
     */
    protected const
        MSG_LOG_GET_ALL =
        "Azure API: querying any Container Registries in ".
        "configured resource groups.";

    /**
     * array of field names to be returned by implementation.
     *
     * @staticvar array FIELDS_RETURNED
     */
    public const FIELDS_RETURNED = array(
        'name',
        'id',
        'subscriptionId',
        'location',
        'type',
        'metricDefinitions',
        'tags',
        'skuName',
        'skuTier',
        'loginServer',
        'creationDate',
        'provisioningState',
        'statusDisplayStatus',
        'statusMessage',
        'statusTimestamp',
        'adminUserEnabled',
        'storageAccountId',
        'policiesQuarantinePolicyStatus',
        'policiesTrustPolicyStatus',
        'policiesTrustPolicyType',
    );


    /** ***********************************************************************
     * assembles information for a container registry into an object
     *
     * @return object
     *
     */
    protected function assembleObject($current)
    {
        // get metric definitions list
        $metrics = $this->getMetricDefinitionsList($current->id);

        $object = (object) [
            'name'                => $current->name,
            'id'                  => $current->id,
            'subscriptionId'      => $this->subscription_id,
            'type'                => $current->type,
            'location'            => $current->location,
            'metricDefinitions'   => $metrics,
            'provisioningState'   => $current->properties->provisioningState,
            'tags'                => "{}",
            'skuName'             => NULL,
            'skuTier'             => NULL,
            'loginServer'         => (
                property_exists($current->properties, 'loginServer') ?
                $current->properties->loginServer : NULL
            ),
            'creationDate'        => (
                property_exists($current->properties, 'creationDate') ?
                $current->properties->creationDate : NULL
            ),
            'statusDisplayStatus' => NULL,
            'statusMessage'       => NULL,
            'statusTimestamp'     => NULL,
            'adminUserEnabled'    => (
                property_exists($current->properties, 'adminUserEnabled') ?
                $current->properties->adminUserEnabled : NULL
            ),
            'storageAccountId'    => (
                property_exists($current->properties, 'storageAccount') ?
                $current->properties->storageAccount->id : NULL
            ),
            'policiesQuarantinePolicyStatus' => NULL,
            'policiesTrustPolicyStatus'      => NULL,
            'policiesTrustPolicyType'        => NULL,
        ];

        if ( property_exists( $current, 'tags' ) )
        {
            $object->tags = json_encode( $current->tags );
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
        }

        if ( property_exists( $current, 'status' ))
        {
            $object->statusDisplayStatus = (
                property_exists( $current->status, 'displayStatus') ?
                $current->status->displayStatus : NULL
            );
            $object->statusMessage = (
                property_exists( $current->status, 'message') ?
                $current->status->message : NULL
            );
            $object->statusTimestamp = (
                property_exists( $current->status, 'timestamp') ?
                $current->status->timestamp : NULL
            );
        }

        // handle policy data from api
        $policies = $this->getContainerRegistryPolicies( $object->id );

        if (is_object( $policies ))
        {
            if (property_exists($policies, 'quarantinePolicy'))
                $object->policiesQuarantinePolicyStatus
                    = $policies->quarantinePolicy->status;

            if (property_exists($policies, 'trustPolicy'))
            {
                $object->policiesTrustPolicyStatus
                    = $policies->trustPolicy->status;

                $object->policiesTrustPolicyType
                    = $policies->trustPolicy->type;
            }
        }

        return $object;
    }


    /** ***********************************************************************
     * takes all information on container regisiries from a resource group
     * and returns it in the format IcingaWeb2 Director expects
     *
     * @return array of objects
     *
     */

    public function scanResourceGroup($group)
    {
        // log if there are resource groups with surprising provisioning
        // state
        if ($group->properties->provisioningState != "Succeeded")
        {
            Logger::info("Azure API: Resoure group ".$group->name.
                         " invalid provisioning state.");
        }

        // get data needed
        $container_registries = $this->getContainerRegistries($group);

        $objects = array();

        foreach($container_registries as $current)
        {
            // add this VM to the list.
            $objects[] = $this->assembleObject($current);
        }

        return $objects;
    }


    /** ***********************************************************************
     * takes all information on all container registries available
     * and returns it in the format IcingaWeb2 Director expects
     *
     * @return array of objects
     *
     */

    public function scanAllContainerRegistries()
    {
        // get data needed
        $container_registries = $this->getAllContainerRegistries();

        $objects = array();

        foreach($container_registries as $current)
        {
            // add this VM to the list.
            $objects[] = $this->assembleObject($current);
        }

        return $objects;
    }


    /** ***********************************************************************
     * Walks through all or all desired resource groups and returns
     * an array of objects of the specific Azure Objects for IcingaWeb2 Director
     * Special case here is that there are different API calls for given
     * resource groups versus all items (unbound of resource groups).
     * Therefore this overrides the base class implementation.
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

        $objects = array();

        if ($rgn == ImportSource::RESOURCE_GROUP_JOKER)
        {
            // this is configured to to the resource group independent
            // Azure API query
            Logger::debug(
                "Azure API: Doing resource group independent query." );
            $objects = $this->scanAllContainerRegistries( NULL );
        }
        else
        {
            // we are doing a resource group orientated query here:
            // get all wanted resource groups and iterate over them
            $rgs =  $this->getResourceGroups( $rgn );
            Logger::debug( "Azure API: found ". count( $rgs ). " elements." );

            // walk through any resourceGroups
            foreach( $rgs as $group )
            {
                $objects = array_merge_recursive(
                    $objects, $this->scanResourceGroup( $group )
                );
            }
        }

        Logger::debug( "Azure API: returning ". count( $objects ).
                       " elements." );

        return $objects;
    }


}

<?php
/** ***************************************************************************
 * @author Peter Dreuw <peter.dreuw@credativ.de>
 * @copyright Copyright (c) 2019 credativ GmbH
 * @license https://github.com/credativ/icingaweb2-module-azure/blob/master/LICENSE MIT License
 *
 *
 */
namespace Icinga\Module\Azure;

use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\QueryException;
use Icinga\Application\Logger;

use Icinga\Module\Azure\MsPgSQLabstract;

/**
 * Class Api
 *
 * This is your main entry point when querying virtual network rules from 
 * PostgreSQL servers on the Azure API.
 *
 */

class MsPgSQLVirtNetRules extends MsPgSQLabstract
{

    /**
     * Log Message for getAll
     *
     * @staticvar string MSG_LOG_GET_ALL
     */
    protected const
        MSG_LOG_GET_ALL =
        "Azure API: querying any PostgreSQL virtual network rules on ".
        "configured servers and resource groups.";

    /**
     * array of field names to be returned by implementation.
     *
     * @staticvar array FIELDS_RETURNED
     */
    public const FIELDS_RETURNED = array(
        'name',
        'subscriptionId',
        'id',
        'type',
        'metricDefinitions',
        'ignoreMissingVnetServiceEndpoint',
        'state',
        'virtualNetworkSubnetId',
    );

    /** ***********************************************************************
     * takes all information on PostgreSQL virtual network rules from a server
     * and returns it in the format IcingaWeb2 Director expects
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
        $server = $this->config["postgresql_server"];
        $rules = $this->getPostgreSQLVirtualNetworkRules($server);

        $objects = array();

        foreach($rules as $current)
        {
            // get metric definitions list
            $metrics = $this->getMetricDefinitionsList($current->id);

            $object = (object) [
                'name'                => $current->name,
                'subscriptionId'      => $this->subscription_id,
                'id'                  => $current->id,
                'type'                => $current->type,
                'metricDefinitions'   => $metrics,
                'state'               => (
                    property_exists($current->properties, "state") ?
                    $current->properties->state : NULL
                ),
                'ignoreMissingVnetServiceEndpoint' => (
                    property_exists(
                        $current->properties,
                        "ignoreMissingVnetServiceEndpoint"
                    ) ?
                    $current->properties->ignoreMissingVnetServiceEndpoint :
                    NULL
                ),
                'virtualNetworkSubnetId' => (
                    property_exists(
                        $current->properties, "virtualNetworkSubnetId") ?
                    $current->properties->virtualNetworkSubnetId : NULL
                ),
            ];

            // add this VM to the list.
            $objects[] = $object;
        }
        return $objects;
    }
}

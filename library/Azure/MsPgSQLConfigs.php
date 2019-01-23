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
 * This is your main entry point when querying Configurations from PostgreSQL
 * Servers on the Azure API.
 *
 */

class MsPgSQLConfigs extends MsPgSQLabstract
{

    /**
     * Log Message for getAll
     *
     * @staticvar string MSG_LOG_GET_ALL
     */
    protected const
        MSG_LOG_GET_ALL =
        "Azure API: querying any PostgreSQL Configurations on ".
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
        'allowedValues',
        'dataType',
        'defaultValue',
        'description',
        'source',
        'value',
    );


    /** ***********************************************************************
     * takes all information on PostgreSQL Databases from a resource group
     * and server and returns it in the format IcingaWeb2 Director expects
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
        $configs = $this->getPostgreSQLConfigs($server);

        $objects = array();

        foreach($configs as $current)
        {
            // get metric definitions list
            $metrics = $this->getMetricDefinitionsList($current->id);

            $object = (object) [
                'name'                => $current->name,
                'subscriptionId'      => $this->subscription_id,
                'id'                  => $current->id,
                'type'                => $current->type,
                'metricDefinitions'   => $metrics,
                'allowedValues'       => (
                    property_exists($current->properties, "allowedValues") ?
                    $current->properties->allowedValues : NULL
                ),
                'dataType'            => (
                    property_exists($current->properties, "dataType") ?
                    $current->properties->dataType : NULL
                ),
                'defaultValue'        => (
                    property_exists($current->properties, "defaultValue") ?
                    $current->properties->defaultValue : NULL
                ),
                'description'         => (
                    property_exists($current->properties, "description") ?
                    $current->properties->description : NULL
                ),
                'source'              => (
                    property_exists($current->properties, "source") ?
                    $current->properties->source : NULL
                ),
                'value'         => (
                    property_exists($current->properties, "value") ?
                    $current->properties->value : NULL
                ),
            ];

            // add this VM to the list.
            $objects[] = $object;
        }
        return $objects;
    }
}

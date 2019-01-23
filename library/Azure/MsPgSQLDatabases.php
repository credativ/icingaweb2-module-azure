<?php
/** ***************************************************************************
 * @author Peter Dreuw <peter.dreuw@credativ.de>
 * @copyright Copyright (c) 2018, 2019 credativ GmbH
 * @license https://github.com/credativ/icingaweb2-module-azure/blob/master/LICENSE MIT License
 *
 *
 */
namespace Icinga\Module\Azure;

use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\QueryException;
use Icinga\Application\Logger;

use Icinga\Module\Azure\Api;

/**
 * Class Api
 *
 * This is your main entry point when querying Databases from PostgreSQL
 * Servers on the Azure API.
 *
 */

class MsPgSQLDatabases extends Api
{

    /**
     * Log Message for getAll
     *
     * @staticvar string MSG_LOG_GET_ALL
     */
    protected const
        MSG_LOG_GET_ALL =
        "Azure API: querying any PostgreSQL Databases on ".
        "configured servers and resource groups.";

    /**
     * static array with names of fields that get configured in a form extension
     * delivered by this class, cf. function extendForm().
     *
     * @staticvar string CONFIG_FIELDS
     */
    public const CONFIG_FIELDS = [ 'postgresql_server' ];

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
        'metricDefinitions',
        'charset',
        'collation',
    );


    /** ***********************************************************************
     * Generates an dictionary for Express route Ciruits available in given
     * resource group.
     *
     * @param QuickForm form
     * a form object to be extended
     *
     * @return void
     *
     */

    protected function enumPostgreSQLservers( $resgroupname )
    {

        $resgroups = $this->getResourceGroups( $resgroupname );

        $retval = array();

        foreach( $resgroups as $group )
        {
            $pgsql = $this->getMsDbPostgreSQLServers( $group );
            foreach($pgsql as $server)
            {
                $retval[$server->id] = $server->name;
            }
        }

        Logger::debug( "Azure API: Dump of available PostgreSQL servers: ".
                       print_r($retval, true));

        return $retval;
    }


    /** ***********************************************************************
     * callback for the importer form manager to call for extensions of
     * the config form. This subclass needs the name of the dependent
     * express route circuit. For uniqueness, we save the ID not the name.
     *
     * @param QuickForm form
     * a form object to be extended
     *
     * @return void
     *
     */

    public function extendForm( QuickForm $form )
    {
        $rgn = $form->getSentOrObjectSetting('resource_group_names');

        $form->addElement('select', 'postgresql_server', array(
            'label'        => $form->translate('PostgreSQL server'),
            'description'  => $form->translate(
                'Select the PostgreSQL server you want to query. '),
            'required'     => true,
            'multiOptions' => $form->optionalEnum(
                $this->enumPostgreSQLservers($rgn)
            ),
        ));
        return;
    }


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
        $databases = $this->getPostgreSQLDatabases($server);

        $objects = array();

        foreach($databases as $current)
        {
            // get metric definitions list
            $metrics = $this->getMetricDefinitionsList($current->id);

            $object = (object) [
                'name'                => $current->name,
                'subscriptionId'      => $this->subscription_id,
                'id'                  => $current->id,
                'type'                => $current->type,
                'location'            => $current->location,
                'metricDefinitions'   => $metrics,
                'charset'             => (
                    property_exists($current->properties, "charset") ?
                    $current->properties->charset : NULL
                ),
                'collation'           => (
                    property_exists($current->properties, "collation") ?
                    $current->properties->collation : NULL
                ),
            ];

            // add this VM to the list.
            $objects[] = $object;
        }
        return $objects;
    }
}

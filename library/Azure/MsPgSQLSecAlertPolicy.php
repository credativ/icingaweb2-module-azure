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
 * This is your main entry point when querying firewall rules from PostgreSQL
 * servers on the Azure API.
 *
 */

class MsPgSQLSecAlertPolicy extends MsPgSQLabstract
{

    /**
     * Log Message for getAll
     *
     * @staticvar string MSG_LOG_GET_ALL
     */
    protected const
        MSG_LOG_GET_ALL =
        "Azure API: querying PostgreSQL security alert policies on ".
        "configured server.";

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
        'disabledAlerts',
        'emailAccountAdmins',
        'emailAddresses',
        'retentionDays',
        'state',
        'storageAccountAccessKey',
        'storageEndpoint',
    );


    /**
     * static array with names of fields that get configured in a form extension
     * delivered by this class, cf. function extendForm().
     *
     * @staticvar string CONFIG_FIELDS
     */
    public const CONFIG_FIELDS = array(
        'postgresql_server',
        'pgsql_sec_alert_policies'
    );


    /** ***********************************************************************
     * callback for the importer form manager to call for extensions of
     * the config form. This subclass needs the name of the dependent
     * MS PostgreSQl server and the names of the inquiered security alert
     * policies.
     *
     * @param QuickForm form
     * a form object to be extended
     *
     * @return void
     *
     */

    public function extendForm( QuickForm $form )
    {
        parent::extendForm( $form );  // get the PosgreSQL server id in the form

        $form->addElement('text', 'pgsql_sec_alert_policies', array(
            'label'        => $form->translate('Security alert policy names'),
            'description'  => $form->translate(
                'Names of security alert policies to import, '.
                'separated by a white space (\'  \')'
            ),
            'required'     => true,
            'class'        => 'autosubmit',
        ));
        return;
    }

    /** ***********************************************************************
     * takes all information on PostgreSQL firewall rule list from a server
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
        $names = explode(" ", $this->config["pgsql_sec_alert_policies"]);

        $objects = array();

        foreach( $names as $policy_name)
        {
            // remove orphan white spaces
            $policy_name = trim( $policy_name );

            if (strlen($policy_name) > 0)
            {
                // retrieve Security Policy for given name and server
                $policy = $this->getMsDbPostgreSQLServerSecurityPolicy(
                    $server, $policy_name);

                // get metric definitions list
                $metrics = $this->getMetricDefinitionsList($current->id);

                $object = (object) [
                    'name'                => $current->name,
                    'subscriptionId'      => $this->subscription_id,
                    'id'                  => $current->id,
                    'type'                => $current->type,
                    'metricDefinitions'   => $metrics,
                    'disabledAlerts'      => (
                        property_exists(
                            $current->properties, "disabledAlerts" ) ?
                        join( ' ', $current->properties->disabledAlerts ) :
                        NULL
                    ),
                    'emailAccountAdmins'  => (
                        property_exists(
                            $current->properties, "emailAccountAdmins" ) ?
                        $current->properties->emailAccountAdmins : NULL
                    ),
                    'emailAddresses'      => (
                        property_exists(
                            $current->properties, "emailAddresses") ?
                        join( ' ', $current->properties->emailAddresses ) :
                        NULL
                    ),
                    'retentionDays'       => (
                        property_exists(
                            $current->properties, "retentionDays" ) ?
                        $current->properties->retentionDays : NULL
                    ),
                    'state'               => (
                        property_exists( $current->properties, "state" ) ?
                        $current->properties->state : NULL
                    ),
                    'storageAccountAccessKey' => (
                        property_exists(
                            $current->properties, "storageAccountAccessKey" ) ?
                        $current->properties->storageAccountAccessKey : NULL
                    ),
                    'storageEndpoint'     => (
                        property_exists(
                            $current->properties, "storageEndpoint" ) ?
                        $current->properties->storageEndpoint : NULL
                    ),
                ];

                // add this policy to the list.
                $objects[] = $object;
            }
        }
        return $objects;
    }
}

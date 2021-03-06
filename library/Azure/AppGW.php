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
 * This is your main entry point when querying Application Gateways from
 * Azure API.
 *
 */


class AppGW extends Api
{

    /**
     * Log Message for getAll
     *
     * @staticvar string MSG_LOG_GET_ALL
     */
    protected const
        MSG_LOG_GET_ALL =
        "Azure API: querying any Application Gateway in configured resource groups.";

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
        'frontEndPublicIP',
        'frontEndPrivateIP',
        'operationalState',
        'frontEndPort',
        'enabledHTTP2',
        'enabledWAF',
        'metricDefinitions',
    );


    /** ***********************************************************************
     * takes all information on application gateways from a resource group
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
        $app_gateways = $this->getApplicationGateways($group);
        $public_ip    = $this->getPublicIpAddresses($group);


        $objects = array();

        foreach($app_gateways as $current)
        {
            // get metric definitions list
            $metrics = $this->getMetricDefinitionsList($current->id);

            $object = (object) [
                'name'              => $current->name,
                'id'                => $current->id,
                'subscriptionId'    => $this->subscription_id,
                'type'              => $current->type,
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
                    property_exists($current->properties, 'frontendPorts') ?
                    $current->properties->frontendPorts[0]->properties->port :
                    NULL
                ),
                'enabledHTTP2'      => $current->properties->enableHttp2,
                'enabledWAF'        => (
                    ( property_exists(
                        $current, 'webApplicationFirewallConfiguration') and
                      property_exists(
                          $current->properties->
                          webApplicationFirewallConfiguration,'enabled')
                    ) ?
                    $current->properties->
                    webApplicationFirewallConfiguration->enabled : FALSE
                ),
                'metricDefinitions'=> $metrics,
            ];

            // search for the public ip, but only if there is one configured.
            if (
                property_exists(
                    $current->properties,'frontendIPConfigurations')
                and
                property_exists(
                    $current->properties->
                    frontendIPConfigurations[0]->properties,
                    'publicIPAddress')
            )
            {
                foreach($public_ip as $pubip)
                {
                    if (($current->properties->frontendIPConfigurations[0]->
                         properties->publicIPAddress->id == $pubip->id)
                        and
                        (property_exists($pubip->properties,'ipAddress')))
                    {
                        $object->frontEndPublicIP = $pubip->properties->
                                                  ipAddress;
                    }
                }
            }
            // add this VM to the list.
            $objects[] = $object;
        }
        return $objects;
    }
}

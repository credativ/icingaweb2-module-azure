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

use Icinga\Module\Azure\Api;

/**
 * Class Api
 *
 * This is your main entry point when querying Express Route Circuit Peerings
 * from Azure API.
 *
 */


class ExpGWpeerings extends Api
{
    /**
     * Log Message for getAll
     *
     * @staticvar string MSG_LOG_GET_ALL
     */
    protected const
        MSG_LOG_GET_ALL =
        "Azure API: querying any Express Route Circuits Peerings ".
        "in configured resource groups.";

    /**
     * static array with names of fields that get configured in a form extension
     * delivered by this class, cf. function extendForm().
     *
     * @staticvar string CONFIG_FIELDS
     */
    public const CONFIG_FIELDS = [ 'express_route_circuits' ];

    /**
     * array of field names to be returned by implementation.
     *
     * @staticvar array FIELDS_RETURNED
     */
    public const FIELDS_RETURNED = array(
        'name',
        'id',
        'etag',
        'metricDefinitions',
        'type',
        'subscriptionId',
        'expressRouteCircuitName',
        'provisioningState',
        'peeringType',
        'azureASN',
        'peerASN',
        'primaryPeerAddressPrefix',
        'primaryAzurePort',
        'secondaryPeerAddressPrefix',
        'secondaryAzurePort',
        'state',
        'statsPrimaryBytesIn',
        'statsPrimaryBytesOut',
        'statsSecondaryBytesIn',
        'statsSecondaryBytesOut',
        'vlanId',
        'lastModifiedBy',
        'gatewayManagerEtag',
        'sharedKey',

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

    protected function enumExpressRouteCircuits( $resgroupname )
    {

        $resgroups = $this->getResourceGroups( $resgroupname );

        $retval = array();

        foreach( $resgroups as $group )
        {
            $erc = $this->getExpressRouteCircuits( $group );
            foreach($erc as $circuit)
            {
                $retval[$circuit->id] = $circuit->name;
            }
        }

        Logger::debug( "Azure API: Dump of available Express Route Circuits: ".
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

        $form->addElement('select', 'express_route_circuits', array(
            'label'        => $form->translate('Express Route Circuit'),
            'description'  => $form->translate(
                'Select the Express Route Circuit you want to query. '),
            'required'     => true,
            'multiOptions' => $form->optionalEnum(
                $this->enumExpressRouteCircuits($rgn)
            ),
        ));
        return;
    }


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

        Logger::debug( "Azure API: dump of additional config data: ".
                       print_r($this->config, true));

        // prepare storage for return values
        $objects = array();

        // load express route circuits in this res group
        $exp_circuits = $this->getExpressRouteCircuits( $group );

        Logger::debug(
            "Azure API: looking for configured Express Route Circuit with id '".
            $this->config['express_route_circuits']."'."
        );

        // search for the right one...
        foreach($exp_circuits as $circuit)
        {
            Logger::debug(
                "Azure API: testing Express Route Circuit id '".
                $circuit->id."' named '".$circuit->name
            );

            if ( strcasecmp( $circuit->id,
                             $this->config['express_route_circuits'] ) == 0 )
            {
                // get data needed
                $exp_routes_peerings =
                                 $this->getExpressRouteCircuitsPeerings(
                                     $group, $circuit->name );

                foreach($exp_routes_peerings as $current)
                {
                    // get metric definitions list
                    $metrics = $this->getMetricDefinitionsList($current->id);

                    $object = (object) [
                        'name'                     => $current->name,
                        'expressRouteCircuitName'  => $circuit->name,
                        'subscriptionId'           => $this->subscription_id,
                        'type'                     =>
                        'Microsoft.Network/expressRouteCircuits/peerings',
                        'id'                       => $current->id,
                        'etag'                     => $current->etag,
                        'metricDefinitions'        => $metrics,
                        'provisioningState'        => (
                            property_exists(
                                $current->properties, 'provisioningState') ?
                            $current->properties->provisioningState : NULL
                        ),
                        'azureASN'                 => (
                            property_exists(
                                $current->properties, 'azureASN') ?
                            $current->properties->azureASN : NULL
                        ),
                        'peerASN'                  => (
                            property_exists(
                                $current->properties, 'peerASN') ?
                            $current->properties->peerASN : NULL
                        ),
                        'primaryPeerAddressPrefix' => (
                            property_exists(
                                $current->properties,
                                'primaryPeerAddressPrefix') ?
                            $current->properties->primaryPeerAddressPrefix :
                            NULL
                        ),
                        'primaryAzurePort'         => (
                            property_exists(
                                $current->properties,
                                'primaryAzurePort') ?
                            $current->properties->primaryAzurePort :
                            NULL
                        ),
                        'secondaryPeerAddressPrefix' => (
                            property_exists(
                                $current->properties,
                                'secondaryPeerAddressPrefix') ?
                            $current->properties->secondaryPeerAddressPrefix :
                            NULL
                        ),
                        'secondaryAzurePort'         => (
                            property_exists(
                                $current->properties,
                                'secondaryAzurePort') ?
                            $current->properties->secondaryAzurePort :
                            NULL
                        ),
                        'vlanId'                     => (
                            property_exists(
                                $current->properties, 'vlanId') ?
                            $current->properties->vlanId : NULL
                        ),
                        'lastModifiedBy'             => (
                            property_exists(
                                $current->properties, 'lastModifiedBy') ?
                            $current->properties->lastModifiedBy : NULL
                        ),
                        'sharedKey'                  => (
                            property_exists(
                                $current->properties, 'sharedKey') ?
                            $current->properties->sharedKey : NULL
                        ),
                        'gatewayManagerEtag'         => (
                            property_exists(
                                $current->properties, 'gatewayManagerEtag') ?
                            $current->properties->gatewayManagerEtag : NULL
                        ),
                        'state'                      => (
                            property_exists(
                                $current->properties, 'state') ?
                            $current->properties->state : NULL
                        ),
                        'statsPrimaryBytesIn'        => NULL,
                        'statsPrimaryBytesOut'       => NULL,
                        'statsSecondaryBytesIn'      => NULL,
                        'statsSecondaryBytesOut'     => NULL,

                        'peeringType'                => (
                            property_exists(
                                $current->properties, 'peeringType') ?
                            $current->properties->peeringType : NULL
                        ),
                        'expressRouteConnection'     => (
                            property_exists(
                                $current->properties,
                                'expressRouteConnection') ?
                            $current->properties->expressRouteConnection : NULL
                        ),
                    ];

                    // insert the current stats if stats object is present
                    // in response
                    if (property_exists($current->properties, 'stats'))
                    {
                        $stats =  $current->properties->stats;

                        $object->statsPrimaryBytesIn  = $stats->primaryBytesIn;
                        $object->statsPrimaryBytesOut = $stats->primaryBytesOut;
                        $object->statsSecondaryBytesIn
                            = $stats->secondaryBytesIn;
                        $object->statsSecondaryBytesOut
                            = $stats->secondaryBytesOut;
                    }

                    // add this to the list.
                    $objects[] = $object;
                }
            }
        }
        return $objects;
    }
}

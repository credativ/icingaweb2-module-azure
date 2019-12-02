<?php
/** ***************************************************************************
 * @author Peter Dreuw <peter.dreuw@credativ.de>
 * @copyright Copyright (c) 2018 credativ GmbH
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
 * Class Virtual Machines
 *
 * This is your main entry point when querying virtual machines from
 * Azure API.
 *
 */


class VirtualMachines extends Api
{
    /** Log Message for getAll
     *
     * @staticvar string MSG_LOG_GET_ALL
     */
    protected const
        MSG_LOG_GET_ALL =
        "Azure API: querying VM available in configured resource groups.";

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
                                     'osType',
                                     'osDiskName',
                                     'dataDisks',
                                     'network_interfaces_count',
                                     'publicIP',
                                     'privateIP',
                                     'cores',
                                     'resourceDiskSizeInMB',
                                     'memoryInMB',
                                     'maxDataDiscCount',
                                     'provisioningState',
                                     'metricDefinitions',
    );

    /** ***********************************************************************
     * takes all information on virtual machines from a resource group and
     * returns it in the format IcingaWeb2 Director expects
     *
     * @return array of objects
     *
     */

    protected function scanResourceGroup($group)
    {
        // log if there are resource groups with surprising provisioning state
        if ($group->properties->provisioningState != "Succeeded")
        {
            Logger::info("Azure API: Resoure group ".$group->name.
                         " invalid provisioning state.");
        }

        // get data needed
        $virtual_machines   = $this->getVirtualMachines($group);
        $network_interfaces = $this->getNetworkInterfaces($group);
        $public_ip          = $this->getPublicIpAddresses($group);

        $objects = array();

        foreach($virtual_machines as $current)
        {
            // get metric definitions list
            $metrics = $this->getMetricDefinitionsList($current->id);

            $object = (object) [
                'name'             => $current->name,
                'subscriptionId'   => $this->subscription_id,
                'id'               => $current->id,
                'location'         => $current->location,
                'type'             => $current->type,
                'osType'           => (
                    property_exists(
                        $current->properties->storageProfile->osDisk,
                        'osType') ?
                    $current->properties->storageProfile->osDisk->osType : ""
                ),
                'osDiskName'       => (
                    property_exists(
                        $current->properties->storageProfile->osDisk, 'name' ) ?
                    $current->properties->storageProfile->osDisk->name : ""
                ),
                'dataDisks'        => count(
                    $current->properties->storageProfile->dataDisks
                ),
                'privateIP'        => NULL,
                'network_interfaces_count' => 0,
                'publicIP'         => NULL,
                'cores'            => NULL,
                'resourceDiskSizeInMB' => NULL,
                'memoryInMB'       => NULL,
                'maxdataDiscCount' => NULL,
                'provisioningState'=> $current->properties->provisioningState,
                'metricDefinitions'=> $metrics,
            ];

            // scan network interfaces and fint the ones belonging to
            // the current vm

            foreach($network_interfaces as $interf)
            {
                // In Azure, a network interface may not have a VM attached :-(
                // and make shure, we match the current vm
                if (
                    property_exists($interf->properties, 'virtualMachine') and
                    property_exists($interf->properties->virtualMachine, 'id') and
                    $interf->properties->virtualMachine->id == $current->id )
                {
                    $object->network_interfaces_count++;

                    $object->privateIP =
                                       $interf->properties->
                                       ipConfigurations[0]->properties->
                                       privateIPAddress;
                    // check, if this interface has got a public IP address
                    if (property_exists(
                        $interf->properties->ipConfigurations[0]->properties,
                        'publicIPAddress'))
                    {
                        foreach($public_ip as $pubip)
                        {
                            if ((
                                $interf->properties->ipConfigurations[0]->
                                properties->publicIPAddress->id ==
                                $pubip->id
                            ) and (
                                property_exists(
                                    $pubip->properties,'ipAddress'
                                )
                            ))
                            {
                                $object->publicIP =
                                                  $pubip->properties->ipAddress;
                            }
                        }
                        if ($object->publicIP == NULL) {
                            Logger::info( "Azure API: Public IP for \'".
                                          $interf->id.
                                          "\' not found." );
                        }
                    }
                }
                else
                {
                    Logger::info( "Azure API: Network interface  \'".
                                  $interf->id.
                                  "\' without configured VM id." );
                }
            }  // end foreach network interfaces

            // get the sizing done
            $vmsize =  $this->getVirtualMachineSizing($current);

            if ($vmsize != NULL)
            {
                $object->cores = $vmsize->numberOfCores;
                $object->resourceDiskSizeInMB = $vmsize->resourceDiskSizeInMB;
                $object->memoryInMB = $vmsize->memoryInMB;
                $object->maxDataDiscCount = $vmsize->maxDataDiskCount;
            }

            // add this VM to the list.
            $objects[] = $object;
        }

        return $objects;
    }
}

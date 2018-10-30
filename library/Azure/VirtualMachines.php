<?php
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
    /** Log Message for getAll **/
    protected const
        MSG_LOG_GET_ALL =
        "Azure API: querying VM available in configured resource groups.";     

    /** ***********************************************************************
     * takes all information on virtual machines from a resource group and 
     * returns it in the format IcingaWeb2 Director expects
     *
     * @return array of objects
     *
     */

    protected function scanResourceGroup($group)
    {
        // only items that have a valid provisioning state
        if ($group->properties->provisioningState != "Succeeded")
        {
            Logger::info("Azure API: Resoure group ".$group->name.
                         " invalid provisioning state.");
            return array();
        }

        // get data needed
        $virtual_machines   = $this->getVirtualMachines($group);
        $network_interfaces = $this->getNetworkInterfaces($group);
        $public_ip          = $this->getPublicIpAddresses($group);

        $objects = array();

        foreach($virtual_machines as $current)
        {
            // skip anything not provisioned.
            $object = (object) [
                'name'             => $current->name,
                'id'               => $current->id,
                'location'         => $current->location,
                'osType'           => (
                    property_exists($current->properties->storageProfile->osDisk,
                                    'osType')?
                    $current->properties->storageProfile->osDisk->osType : ""
                ),
                'osDiskName'       => (
                    property_exists($current->properties->storageProfile->osDisk,'name')?
                    $current->properties->storageProfile->osDisk->name : ""
                ),
                'dataDisks'        => count($current->properties->storageProfile->dataDisks),
                'privateIP'        => NULL,
                'network_interfaces_count' => 0,
                'publicIP'         => NULL,
                'cores'            => NULL,
                'resourceDiskSizeInMB' => NULL,
                'memoryInMB'       => NULL,
                'maxdataDiscCount' => NULL,
                'provisioningState'=> $current->properties->provisioningState,
            ];

            // scan network interfaces and fint the ones belonging to
            // the current vm

            foreach($network_interfaces as $interf)
            {
                // In Azure, a network interface may not have a VM attached :-(
                // and make shure, we match the current vm
                if (
                    property_exists($interf->properties, 'virtualMachine') and
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
                            if (($interf->properties->ipConfigurations[0]->properties->publicIPAddress->id ==
                                 $pubip->id) and
                                (property_exists($pubip->properties,'ipAddress')))
                            {
                                $object->publicIP = $pubip->properties->ipAddress;
                            }
                        }
                    }
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

<?php

namespace Icinga\Module\Azure;

use Icinga\Exception\ConfigurationError;
use Icinga\Exception\QueryException;

use Icinga\Module\Azure\Token;

use Icinga\Module\Azure\restclient\RestClient;

use Icinga\Application\Logger;
/**
 * Class Api
 *
 * This is your main entry point when working with this library
 */


class VirtualMachines extends Api
{

   

    /** ***********************************************************************
     * takes all information on virtual machines from a resource group and 
     * returns it in the format IcingaWeb2 Director expects
     *
     * @return array of objects
     *
     */

    protected function scanVMResource($group)
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
        // $disks              = $this->getDisks($group);
        $network_interfaces = $this->getNetworkInterfaces($group);
        $public_ip          = $this->getPublicIpAddresses($group);

        $objects = array();

        foreach($virtual_machines as $current)
        {
            // skip anything not provisioned.
            if ($current->properties->provisioningState == "Succeeded")
            {
                $object = (object) [
                    'name'           => $current->name,
                    'id'             => $current->id,
                    'location'       => $current->location,
                    'osType'         => (
                        property_exists($current->properties->storageProfile->osDisk,
                                        'osType')?
                        $current->properties->storageProfile->osDisk->osType : ""
                    ),
                    'osDiskName'     => (
                        property_exists($current->properties->storageProfile->osDisk,'name')?
                        $current->properties->storageProfile->osDisk->name : ""
                    ),
                    'dataDisks'      => count($current->properties->storageProfile->dataDisks),
                    'privateIP'      => "",
                    'network_interfaces_count' => 0,
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
                                else
                                    if (!property_exists($object, 'publicIP'))
                                        $object->publicIP = "";
                            }
                        }
                    }
                }  // end foreach network interfaces


                // get the sizing done
                $vmsize =  $this->getVirtualMachineSizing($current);

                if ($vmsize == NULL)
                {
                    $object->cores = NULL;
                    $object->resourceDiskSizeInMB = NULL;
                    $object->memoryInMB = NULL;
                    $object->maxdataDiscCount = NULL;
                }
                else
                {
                    $object->cores = $vmsize->numberOfCores;
                    $object->resourceDiskSizeInMB = $vmsize->resourceDiskSizeInMB;
                    $object->memoryInMB = $vmsize->memoryInMB;
                    $object->maxDataDiscCount = $vmsize->maxDataDiskCount;
                }

                // add this VM to the list.
                $objects[] = $object;
            }
        }
        
        return $objects;
    }


   
    /** ***********************************************************************
     * Walks through all or all desired resource groups and returns
     * an array of objects of virtual machines for IcingaWeb2 Director
     * 
     *
     * @param string $rgn 
     * a space separated list of resoureceGroup names to query or '' for all
     *
     * @return array of objects
     *
     */
    
    public function getAllVM( $rgn )
    {
        Logger::info("Azure API: querying VM available in configured resource groups.");
        $rgs =  $this->getResourceGroups( $rgn );

        $objects = array();

        // walk through any resourceGroups
        foreach( $rgs as $group )  
        {          
            $objects = $objects + $this->scanVMResource( $group );
        }
        return $objects;
    }  
}

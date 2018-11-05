<?php
namespace Icinga\Module\Azure;

use Icinga\Exception\ConfigurationError;
use Icinga\Exception\QueryException;
use Icinga\Application\Logger;

use Icinga\Module\Azure\Api;


/**
 * Class Virtual Machines Disks
 *
 * This is your main entry point when querying disks for virtual machines from 
 * Azure API. 
 *
 */


class VirtualMachinesDisks extends Api
{
    /** Log Message for getAll **/
    protected const
        MSG_LOG_GET_ALL =
        "Azure API: querying disks available in correlation to virtual ".
        "machines in configured resource groups.";     

    /** ***********************************************************************
     * takes all information on virtual machines disks from a resource group 
     * and returns it in the format IcingaWeb2 Director expects
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
        $disks              = $this->getDisks($group);

        $objects = array();

        foreach($disks as $current)
        {
            $object = (object) [
                'name'              => $current->name,
                'subscriptionId'    => $this->subscription_id,
                'id'                => $current->id,
                'location'          => $current->location,
                'managedBy'         => $current->managedBy,
                'diskState'         => $current->properties->diskState,
                'provisioningState' => $current->properties->provisioningState,
                'timeCreated'       => $current->properties->timeCreated,
                'diskSizeGB'        => $current->properties->diskSizeGB,
                'osType'            => $current->properties->osType,
                
                'createOption'      => (
                    property_exists($current->properties,'creationData') ?
                    (
                        property_exists($current->properties->creationData,
                                        'createOption') ?
                        $current->properties->creationData->createOption :
                        NULL ) :
                    NULL
                ),
                
                'imageReferenceId'  => (
                    property_exists($current->properties,'creationData') ?
                    (
                        property_exists($current->properties->creationData,
                                        'imageReference') ?
                        (
                            property_exists($current->properties->creationData->
                                            imageReference, 'id') ?
                            $current->properties->creationData->imageReference->id :
                            NULL ) :
                        NULL ) :
                    NULL ),
                
                'imageReferenceLun'  => (
                    property_exists($current->properties,'creationData') ?
                    (
                        property_exists($current->properties->creationData,
                                        'imageReference') ?
                        (
                            property_exists($current->properties->creationData->
                                            imageReference, 'lun') ?
                            $current->properties->creationData->imageReference->lun :
                            NULL ) :
                        NULL ) :
                    NULL ),
                
                'sourceUri'  => (
                    property_exists($current->properties,'creationData') ?
                    (
                        property_exists($current->properties->creationData,
                                        'sourceUri') ?
                        $current->properties->creationData->sourceUri :
                        NULL ) :
                    NULL ),
                
                'sourceResourceId'  => (
                    property_exists($current->properties,'creationData') ?
                    (
                        property_exists($current->properties->creationData,
                                        'sourceResourceId') ?
                        $current->properties->creationData->sourceResourceId :
                        NULL ) :
                    NULL ),

                'encryptionEnabled' => (
                    property_exists($current->properties,'encryptionSettings') ?
                    (
                        property_exists($current->properties->encryptionSettings,
                                        'enabled') ?
                        $current->properties->encryptionSettings->enabled :
                        NULL ) :
                    NULL ),
                'vmName'            => NULL,
                'vmUsageType'       => 'none',
                'vmCaching'         => NULL,
                'vmLun'             => NULL,
                'vmLocation'        => NULL,
                'vmProvisioningState' => NULL,
            ];                


            // find the matching managing virtual machine and fill in some
            // data on this
            
            foreach($virtual_machines as $vm)
            {
                if ($vm->id == $current->managedBy)
                {
                    // set the name of the vm this disk belongs to
                    // and other usefull stuff
                    $object->vmName              = $vm->name;
                    $object->vmLocation          = $vm->location;
                    $object->vmProvisioningState = $vm->properties->provisioningState;

                    // determine if the usage type is 'osDisk', 'dataDisk'
                    // or 'none' if not found
                    if ( $vm->properties->storageProfile->osDisk->managedDisk->id ==
                         $current->id)
                    {
                        // yes, this one is the osDisk.
                        $object->vmUsageType = 'osDisk';
                        
                        if (property_exists($vm->properties->storageProfile->osDisk,
                                            'caching'))
                            $object->vmCaching =
                                               $vm->properties->storageProfile->osDisk->caching;

                        if (property_exists(
                            $vm->properties->storageProfile->osDisk,
                            'lun'))
                            $object->vmLun =
                                           $vm->properties->storageProfile->osDisk->lun;
                    }

                    else
                        // no, we have to search the dataDisks
                        foreach( $vm->properties->storageProfile->dataDisks as $data_disk)
                        {
                            // Please note: by the time of writing this,
                            // the Azure API reports the resource group name
                            // in upper case for the dataDisk array :-(
                            
                            if (strtolower($data_disk->managedDisk->id) ==
                                strtolower($current->id))
                            {
                                $object->vmUsageType = 'dataDisk';
                                if (property_exists($data_disk, 'caching'))
                                    $vm->vmCaching = $data_disk->caching;
                                if (property_exists($data_disk, 'lun'))
                                    $vm->vmLun = $data_disk->lun;
                            }
                        }
                }
            }

            // add this VM to the list.
            $objects[] = $object;
        }
        
        return $objects;
    }  
}

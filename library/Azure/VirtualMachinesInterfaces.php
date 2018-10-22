<?php
namespace Icinga\Module\Azure;

use Icinga\Exception\ConfigurationError;
use Icinga\Exception\QueryException;
use Icinga\Application\Logger;

use Icinga\Module\Azure\Api;


/**
 * Class Virtual Machines Interfaces
 *
 * This is your main entry point when querying interfaces atteched to virtual 
 * machines from Azure API. 
 *
 * Please note, the interfaces will show only one ip configuration on the VM
 * query class VirtualMachines. IN opposition, this class 
 * "VirtualMachinesInterfaces" will create an interface object for each 
 * ip configuration found on the network interface. Therefore the object id 
 * man not be unique and I introduced a second field uniqueId, which is 
 * assembled from the interface id as well as the ip configuration id.
 *
 */


class VirtualMachinesInterfaces extends Api
{
    /** Log Message for getAll **/
    protected const
        MSG_LOG_GET_ALL =
        "Azure API: querying interfaces available in configured resource groups ".
        "and in correlation to virtual machines if there is a vm attached";     

    /** ***********************************************************************
     * takes all information on virtual machine interfaces from a resource 
     * group and returns it in the format IcingaWeb2 Director expects
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

        foreach($network_interfaces as $current)
        {
            $object = (object) [
                'name'                        => $current->name,
                'id'                          => $current->id,
                'uniqueId'                    => $current->id, // see above
                'location'                    => $current->location,
                'etag'                        => $current->etag,
                'provisioningState'           => $current->properties->provisioningState,
                'macAddress'                  => (
                    property_exists($current->properties, 'macAddress') ?
                    $current->properties->macAddress : NULL ),
                'enableAcceleratedNetworking' => (
                    property_exists($current->properties,
                                    'enableAcceleratedNetworking') ?
                    $current->properties->enableAcceleratedNetworking :
                    false ),
                'enableIPForwarding'          => (
                    property_exists($current->properties,
                                    'enableIPForwarding') ?
                    $current->properties->enableIPForwarding :
                    false ),
                'networkSecurityGroupId'      => (
                    property_exists($current->properties,
                                    'networkSecurityGroup') ?
                    (
                        property_exists($current->properties->networkSecurityGroup,
                                        'id ') ?
                        $current->properties->networkSecurityGroup->id :
                        NULL) :
                    NULL ),
                'dnsServers'                  => '',
                'appliedDnsServers'           => '',
                'internalDnsNameLabel'        => NULL,
                'internalFqdn'                => NULL,
                'internalDomainNameSuffix'    => NULL,
                'virtualMachineId'            =>(
                    property_exists($current->properties,
                                    'virtualMachine') ?
                    (
                        property_exists($current->properties->virtualMachine,'id') ?
                        $current->properties->virtualMachine->id : NULL) :
                    NULL ),
                'vmName'                      => NULL,
                'vmLocation'                  => NULL,
                'vmProvisioningState'         => NULL,
                'ipConfName'                  => NULL,
                'ipConfId'                    => NULL,
                'ipConfEtag'                  => NULL,
                'ipConfProvisioningState'     => NULL,
                'ipConfPrivateIPAddress'      => NULL,
                'ipConfPrivateIPAllocationMethod'  => NULL,
                'ipConfSubnetId'              => NULL,
                'ipConfPrimary'               => NULL,
                'ipConfPrivateIPAddressVersion'    => NULL,
                'ipConfInUseWithService'      => NULL,
                'ipConfPublicIPAddressId'     => NULL,
                'ipConfPublicIPAddressName'   => NULL,
                'ipConfPublicIPAddress'       => NULL,
                'ipConfPublicIPAddressProvState'   => NULL,
                'ipConfPublicIPAddressVersion'=> NULL,
                'ipConfPublicIPAllocationMethod'   => NULL,
                'ipConfPublicIPAddressLocation'    => NULL,
                'ipConfPublicIPAddressIdleTimeoutInMinutes' => NULL,
            ];


            // fill in matching vm data
            if ($object->virtualMachineId != NULL)
                foreach($virtual_machines as $vm)
                    if ($vm->id == $object->virtualMachineId)
                    {
                        $object->vmName              = $vm->name;
                        $object->vmLocation          = $vm->location;
                        $object->vmProvisioningState = $vm->properties->provisioningState;
                    }          
   

            // handle DNS settings if available
            if (property_exists($current->properties, 'dnsSettings'))
            {
                if (property_exists($current->properties->dnsSettings,
                                    'dnsServers'))
                {
                    // concat all server ip intersected by a ", " (comma-space)
                    foreach($current->properties->dnsSettings->dnsServers as
                            $server)
                        $object->dnsServers .= $server.", ";
                    // chop last comma-space
                    if ($object->dnsServers != '')
                        $object->dnsServers = substr($object->dnsServers,
                                                     0, 
                                                     strlen($object->dnsServers)-2);
                }
                
                if (property_exists($current->properties->dnsSettings,
                                    'appliedDnsServers'))
                {
                    // concat all server ip intersected by a ", " (comma-space)
                    foreach($current->properties->dnsSettings->appliedDnsServers as
                            $server)
                        $object->appliedDnsServers .= $server.", ";
                    // chop last comma-space
                    if ($object->appliedDnsServers != '')
                        $object->appliedDnsServers = substr($object->appliedDnsServers,
                                                     0, 
                                                     strlen($object->appliedDnsServers)-2);
                }

                $object->internalDnsNameLabel = property_exists(
                    $current->properties->dnsSettings, 'internalDnsNameLabel') ?
                    $current->properties->dnsSettings->internalDnsNameLabel : NULL;

                $object->internalFqdn = property_exists(
                    $current->properties->dnsSettings, 'internalFqdn') ?
                    $current->properties->dnsSettings->internalFqdn : NULL;

                $object->internalDomainNameSuffix = property_exists(
                    $current->properties->dnsSettings, 'internalDomainNameSuffix') ?
                    $current->properties->dnsSettings->internalDomainNameSuffix : NULL;

            }

            // at this point, the object is done except for the ipConfigurations
            // if there are no ipConfigurations yet, we're done.
            // as there can be multiple ip configurations on an interface,
            // we will clone this object for each of the ip configurations and
            // push the copies into the result array.


            if (count($current->properties->ipConfigurations) < 1)
                $objects[] = $object;
            else
                foreach($current->properties->ipConfigurations as $ipConf)
                {
                    $w = clone $object;

                    $w->ipConfName              = $ipConf->name;
                    $w->ipConfId                = $ipConf->id;
                    $w->uniqueId               .= "-&&&-".$ipConf->id;
                    $w->ipConfEtag              = $ipConf->etag;
                    $w->ipConfProvisioningState = $ipConf->properties->provisioningState;

                    $w->ipConfPrivateIPAddress
                        = property_exists($ipConf->properties,'privateIPAddress') ?
                        $ipConf->properties->privateIPAddress : NULL;

                    $w->ipConfPrivateIPAllocationMethod
                        = property_exists($ipConf->properties,'privateIPAllocationMethod') ?
                        $ipConf->properties->privateIPAllocationMethod : NULL;

                    $w->ipConfSubnetId
                        = property_exists($ipConf->properties, 'subnet') ?
                        (
                            property_exists($ipConf->properties->subnet, 'id') ?
                            $ipConf->properties->subnet->id : NULL
                        ) : NULL;

                    $w->ipConfPrimary
                        = property_exists($ipConf->properties, 'primary') ?
                        $ipConf->properties->primary : NULL;

                    $w->ipConfPrivateIPAddressVersion
                        = property_exists($ipConf->properties, 'privateIPAddressVersion') ?
                        $ipConf->properties->privateIPAddressVersion : NULL;

                    $w->ipConfInUseWithService
                        = property_exists($ipConf->properties, 'isInUseWithService') ?
                        $ipConf->properties->isInUseWithService : NULL;

                    // now deal with the linked public IP part
                    if (property_exists($ipConf->properties, 'publicIPAddress'))
                    {
                        $w->ipConfPublicIPAddressId = $ipConf->properties->publicIPAddress->id;
                        // find matching public IP address
                        foreach($public_ip as $pubIp)
                            if ($pubIp->id ==  $w->ipConfPublicIPAddressId)
                            {
                                $w->ipConfPublicIPAddressName
                                    = $pubIp->name;

                                $w->ipConfPublicIPAddressProvState
                                    = $pubIp->properties->provisioningState;

                                $w->ipConfPublicIPAddressLocation
                                    = $pubIp->location;

                                $w->ipConfPublicIPAddress
                                    = property_exists($pubIp->properties,
                                                      'ipAddress') ?
                                    $pubIp->properties->ipAddress : NULL;
                                
                                $w->ipConfPublicIPAddressVersion
                                    = property_exists($pubIp->properties,
                                                      'publicIPAddressVersion') ?
                                    $pubIp->properties->publicIPAddressVersion :
                                    NULL;
                                
                                $w->ipConfPublicIPAllocationMethod
                                    = property_exists($pubIp->properties,
                                                      'publicIPAllocationMethod') ?
                                    $pubIp->properties->publicIPAllocationMethod :
                                    NULL;
                                    
                                $w->ipConfPublicIPAddressIdleTimeoutInMinutes
                                    = property_exists($pubIp->properties,
                                                      'idleTimeoutInMinutes') ?
                                    $pubIp->properties->idleTimeoutInMinutes :
                                    NULL;

                                // we found it, no further iteration necessary.
                                break;
                            }
                    }
                    $objects[] = $w;
                }
        }
        return $objects;
    }  
}

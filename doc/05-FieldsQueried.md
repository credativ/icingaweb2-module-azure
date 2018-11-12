<a name="Import-Source"></a>Fields imported by Azure Importer Plugin
====================================================================

This is a short breakdown of information returned by the various importer
object types. Obviously, each object type has to return its individual set
of fields to match the resources given by Azure.

Any query returns at least these fields by the time of writing:

* name
* subscriptionId
* id
* location

The 'name' is the name chosen in Microsoft Azure for the returned object. The
'subscriptionId' is just for convenience to group inventories if you have
multiple subscriptions you are importing of. An 'id' is simply the returned
id from Azure which is the path of the URI of the object in the API for most of
times, which includes the subscription id itself. The 'id' field may not be
unique. The 'location' field is the location returned from Azure and contains
the Azure region the object is located in.

The 'metricDefinitions' field is a comma separated list with metric names
available for this object type, if available. (cf.
https://docs.microsoft.com/en-us/azure/monitoring-and-diagnostics/monitoring-supported-metrics?toc=/azure/azure-monitor/toc.json )

Virtual Machines
----------------

The **Virtual machines** object type does return these fields:

* name
* subscriptionId
* id
* location
* osType
* osDiskName
* dataDisks
* network_interfaces_count
* publicIP
* privateIP
* cores
* resourceDiskSizeInMB
* memoryInMB
* maxDataDiscCount
* prosioningState
* metricDefinitions

Please note that this reports the private and/or public IP of the first
interface found.




Virtual Machines (Disks)
------------------------

The **Virtual machines (disks)** object type does return these fields:

* name
* subscriptionId
* id
* location
* managedBy
* diskState
* provisioningState
* timeCreated
* diskSizeGB
* osType
* createOption
* imageReferenceId
* imageReferenceLun
* sourceUri
* sourceResourceId
* encryptionEnabled
* vmName
* vmUsageType
* vmCaching
* vmLocation
* vmProvisioningState


Virtual Machines (Interfaces)
-----------------------------

Please note, the interfaces will show only one ip configuration on the VM
query class VirtualMachines. In opposition, this class 
"VirtualMachinesInterfaces" will create an interface object for each 
ip configuration found on the network interface. Therefore the object id 
man not be unique and I introduced a second field uniqueId, which is 
assembled from the interface id as well as the ip configuration id.

The **Virtual machines (interfaces)** object type does return these fields:

* name
* subscriptionId
* id
* uniqueId
* location
* etag
* provisioningState
* macAddress
* enableAcceleratedNetworking
* enableIPForwarding
* networkSecurityGroupId
* dnsServers
* appliedDnsServers
* internalDnsNameLabel
* internalFqdn
* internalDomainNameSuffix
* virtualMachineId
* vmName
* vmLocation
* vmProvisioningState
* ipConfName
* ipConfId
* ipConfEtag
* ipConfProvisioningState
* ipConfPrivateIPAddress
* ipConfPrivateIPAllocationMethod
* ipConfSubnetId
* ipConfPrimary
* ipConfPrivateIPAddressVersion
* ipConfInUseWithService
* ipConfPublicIPAddressId
* ipConfPublicIPAddressName
* ipConfPublicIPAddress
* ipConfPublicIPAddressProvState
* ipConfPublicIPAddressVersion
* ipConfPublicIPAllocationMethod
* ipConfPublicIPAddressLocation
* ipConfPublicIPAddressIdleTimeoutInMinutes
* metricDefinitions


Load Balancers
--------------

* name
* subscriptionId
* id
* location
* provisioningState
* frontEndPublicIP
* metricDefinitions


Application Gateways
--------------------

* name
* subscriptionId
* id
* location
* provisioningState
* frontEndPublicIP
* frontEndPrivateIP
* operationalState
* frontEndPort
* enabledHTTP2
* enabledWAF
* metricDefinitions


Express Route Circuits
----------------------

* name
* subscriptionId
* id
* location
* provisioningState
* bandwitdthInMbps
* circuitProvisioningState
* peeringlocation
* serviceProviderName
* serviceproviderProvisioningState
* metricDefinitions


Microsoft DB for PosgreSQL (server)
-----------------------------------

* name
* subscriptionId
* id
* location
* version
* tier
* capacity
* sslEnforcement
* userVisibleState
* fqdn
* earliestRestoreDate
* storageMB
* backupRetentionDays
* geoRedundantBackup
* metricDefinitions


Resource Groups
---------------

The simple query for resource groups available in the given subscription
might not be too usefull in Azure but on the one hand you could monitor
the provisioning state. The primary reason for this to exist is as a helper
for the dynamically generated configuration menu for all importers based
on this Azure API director plugin.

* name
* subscriptionId
* id
* location
* provisioningState


Subscriptions
-------------

The simple query for subscriptions available with the given credentials.
This might not be too usefull in Azure but on the one hand you could monitor
the subscription state. The primary reason for this to exist is as a helper
for the dynamically generated configuration menu for all importers based
on this Azure API director plugin.

* name
* subscriptionId
* id
* state
* locationPlacementId
* quotaId
* spendingLimit



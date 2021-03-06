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
* provider
* type

The 'name' is the name chosen in Microsoft Azure for the returned object. The
'subscriptionId' is just for convenience to group inventories if you have
multiple subscriptions you are importing of. An 'id' is simply the returned
id from Azure which is the path of the URI of the object in the API for most of
times, which includes the subscription id itself. The 'id' field may not be
unique. The 'location' field is the location returned from Azure and contains
the Azure region the object is located in.

The 'type' field is just a static string derived from the API query
to give a reference to the main API call that was used and to provide the
IcingaWeb2 Director user with something to group results.

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
* type
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
* tags
* state

Please note that this reports the private and/or public IP of the first
interface found.




Virtual Machines (Disks)
------------------------

The **Virtual machines (disks)** object type does return these fields:

* name
* subscriptionId
* id
* location
* type
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
* type
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

https://docs.microsoft.com/en-us/rest/api/load-balancer/loadbalancers/list

* name
* subscriptionId
* id
* type
* location
* provisioningState
* frontEndPublicIP
* metricDefinitions


Application Gateways
--------------------

https://docs.microsoft.com/en-us/rest/api/application-gateway/applicationgateways/list

* name
* subscriptionId
* id
* type
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

https://docs.microsoft.com/en-us/rest/api/expressroute/expressroutecircuits/list

* name
* subscriptionId
* id
* location
* type
* provisioningState
* bandwithInGbps
* circuitProvisioningState
* allowClassicOperations
* serviceProviderName
* serviceproviderProvisioningState
* serviceProviderBandwitdthInMbps  1)
* peeringlocation
* metricDefinitions
* etag
* expressRoutePort
* gatewayManagerEtag
* serviceKey
* serviceProviderNotes
* stag
* skuName
* skuTier
* skuFamily
* tags
* authorizations
* peerings


1) was renamed from bandwithInMbps


Express Route Circuits (Authorizations)
---------------------------------------

https://docs.microsoft.com/en-us/rest/api/expressroute/expressroutecircuitauthorizations/list

* name
* id
* etags
* metricDefinitions
* type
* subscriptionId
* provisioningState
* expressRouteCircuitName
* authorizationKey
* authorizationUseStatus


Express Route Circuits (Peerings)
---------------------------------

https://docs.microsoft.com/en-us/rest/api/expressroute/expressroutecircuitpeerings/list

* name
* id
* etag
* metricDefinitions
* type
* subscriptionId
* expressRouteCircuitName
* provisioningState
* peeringType
* azureASN
* peerASN
* primaryPeerAddressPrefix
* primaryAzurePort
* secondaryPeerAddressPrefix
* secondaryAzurePort
* state
* statsPrimaryBytesIn
* statsPrimaryBytesOut
* statsSecondaryBytesIn
* statsSecondaryBytesOut
* vlanId
* lastModifiedBy
* gatewayManagerEtag
* sharedKey


Microsoft DB for PostgreSQL (server)
------------------------------------

https://docs.microsoft.com/en-us/rest/api/postgresql/servers/list

* name
* subscriptionId
* id
* location
* type
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


Microsoft DB for PostgreSQL (databases)
---------------------------------------

https://docs.microsoft.com/en-us/rest/api/postgresql/databases/listbyserver

* name
* subscriptionId
* id
* location          - the location of the server
* type
* metricDefinitions - currently empty
* charset
* collation


Microsoft DB for PostgreSQL (configurations)
--------------------------------------------

https://docs.microsoft.com/en-us/rest/api/postgresql/configurations/listbyserver

* name
* subscriptionId
* id
* location
* type
* metricDefinitions
* allowedValues
* dataType
* defaultValue
* description
* source
* value

Microsoft DB for PostgreSQL (firewall rules)
--------------------------------------------

https://docs.microsoft.com/en-us/rest/api/postgresql/firewallrules/listbyserver

* name
* subscriptionId
* id
* type
* metricDefinitions
* endIpAddress
* startIpAddress

Microsoft DB for PostgreSQL (virtual network rules)
------------------------------------------------

https://docs.microsoft.com/en-us/rest/api/postgresql/virtualnetworkrules/listbyserver

* name
* subscriptionId
* id
* type
* metricDefinitions
* ignoreMissingVnetServiceEndpoint
* state
* virtualNetworkSubnetId

Microsoft DB for PostgreSQL (security alert policies)
------------------------------------------------------

https://docs.microsoft.com/en-us/rest/api/postgresql/serversecurityalertpolicies/get

* name
* subscriptionId
* id
* type
* metricDefinitions
* disabledAlerts
* emailAccountAdmins
* emailAddresses
* retentionDays
* state
* storageAccountAccessKey
* storageEndpoint

Microsoft DB for PostgreSQL (location based performance tiers)
----------------------------------------------------------------------

https://docs.microsoft.com/en-us/rest/api/postgresql/locationbasedperformancetier/list

This importer gets all locations from any MS PostgreSQL server (SAAS) available
in the current subscription. On these locations, the location based performance
tier API is queried and the result is presented here. "slo" stands for "service
level objective".

* name
* subscriptionId
* id
* type
* metricDefinitions
* sloHardwareGeneration
* sloId
* sloMaxBackupRetentionDays
* sloMaxStorageMB
* sloMinBackupRetentionDays
* sloMinStorageMB
* sloVCore


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
* type
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
* type
* state
* locationPlacementId
* quotaId
* spendingLimit


Container Registries
--------------------

https://docs.microsoft.com/en-us/rest/api/containerregistry/registries/list

* name
* id
* subscriptionId
* location
* type
* metricDefinitions
* tags
* skuName
* skuTier
* loginServer
* creationDate
* provisioningState
* statusDisplayStatus
* statusMessage
* statusTimestamp
* adminUserEnabled
* storageAccountId
* policiesQuarantinePolicyStatus
* policiesTrustPolicyStatus
* policiesTrustPolicyType

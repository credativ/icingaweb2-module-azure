<a name="Import-Source"></a>Fields imported by Azure Importer Plugin
====================================================================

This is a short breakdown of information returned by the various importer
object types. Obviously, each object type has to return its individual set
of fields to match the resources given by Azure.


Virtual Machines
----------------

The **Virtual machines** object type does return these fields:

* name
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

Please note that this reports the private and/or public IP of the first
interface found.


Load Balancers
--------------

* name
* id
* location
* provisioningState
* frontEndPublicIP


Application Gateways
--------------------

* name
* id
* location
* provisioningState
* frontEndPublicIP
* frontEndPrivateIP
* operationalState
* frontEndPort
* enabledHTTP2
* enabledWAF


Express Route Circuits
----------------------

* name
* id
* location
* provisioningState
* bandwitdthInMbps
* circuitProvisioningState
* peeringlocation
* serviceProviderName
* serviceproviderProvisioningState


Microsoft DB for PosgreSQL (server)
-----------------------------------

* name
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



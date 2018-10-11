Microsoft Azure director importer module for Icinga Web 2
=========================================================

This is a simple Azure module for Icinga Web 2. Currently implented is an
Import Source provider for [Icinga Director](https://github.com/Icinga/icingaweb2-module-director).
It can import either virtual machines, load blanacers or application gateways
from all, one or multiple resource groups availsable to the given api
credentials.

This data can be used to be deployed immediately as new (virtual) hosts to your
[Icinga](https://www.icinga.org/) monitoring system.


Documentation
-------------

### Basics
 * [Installation](doc/01-Installation.md)
 * [ImportSource](doc/02-ImportSource.md)
 * [Troubleshooting](doc/99-Troubleshooting.md)

Furthermore, there is a [ChangeLog](ChangeLog) file.


Dependencies
------------

This module has no dependencies on any SDK or other external files except
for the php-curl extension, which must be enabled. 


Credits
-------

This module makes use of

https://github.com/tcdent/php-restclient

published under the MIT license. The REST client code was slightly modified to
fit into namespaces and uses valid exception classes suitable for IcingaWeb2

The code was inspired by the [Icinga Web 2 module for vSphere](https://github.com/Icinga/icingaweb2-module-vsphere)
as well as by the [AWS module for Icinga Web 2](https://github.com/Icinga/icingaweb2-module-aws).



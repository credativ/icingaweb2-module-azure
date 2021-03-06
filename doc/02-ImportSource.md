<a name="Import-Source"></a>Director Import Source configuration
================================================================

To create a new **Microsoft Azure** Import Source for the [Icinga Director](
https://github.com/Icinga/icingaweb2-module-director) please go to the Director
Dashboard and choose *Import data sources*.

This brings you to your list of configured **Import Source** definitions,
we want to **Add** a new one.

![Create an importer](/doc/screenshot/readme/importer_overview.png)

Select an appropriate name for the Importer and choose "Microsoft Azure" from
the "Source Type" drop down list. You may also add a good description, too.

Selecting **Microsoft Azure** extends the form and gives you these additional
fields:

* Key column name
* Azure Tenant ID
* Azure Subscription ID
* Azure Client ID
* Azure Cient Secret
* Object Type
* Resource Groups
* Proxy URL
* Connection timeout
* Request Timeout


![Create or edit importer settings](/doc/screenshot/edit_importer.png)

The **key column name** defaults to "name" and will be appropriate in most
situations.

The **Azure Tenant ID, Subscription ID, Client ID** and **Client** Secret are
the credentials you have to obtain from your Azure Account. Please confere the
appropriate documentation on this. The Subscription ID will get selectable after
you entered the Tenant and Client ID as well as the Client secret.
Make shure your IcingaWeb2 host has either internet access or you already setup
the proxy configuration above as this will apply already for retrieving the
subscription id. 

![Object Types](/doc/screenshot/azure_object_types.png)

**Object Type** is a dropdown list showing the object types this importer
plugin can query from the Azure API. Depending on the object type the
importer queries, it will return different column names matching the data
available on these objects in the Azure data structures.

Therefore, you cannot mix object types within one importer instance. You
will have to configure an importer for virtual machines and another for
retrieving information on load balancers eg.

**Resource Groups** is a list of resource group names available in your Azure
account. You can select one or choose "all". 

**Proxy URL** is the URL of a http proxy obviously. It could be like
http://example.com:port. For details on this look into the php-curl
documentation as this goes straight forward into the curl request setup as
parameter CURLOPT_PROXY.

**Request timeout** is the timeout for an individual API request in seconds.
This timeout starts running after the connection was established.

**Connection Timeout** in secondes is the time, the curl library waits to reach
the remote API endpoint. If it times out, the request will be canceled and an
error will be raised/logged.


You can click on the **Preview** tab of the importer to see a fast preview query
like this:

![Preview](/doc/screenshot/vm_preview.png)


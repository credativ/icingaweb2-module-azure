<a name="Import-Source"></a>Director Import Source configuration
================================================================

To create a new **Microsoft Azure** Import Source for the [Icinga Director](
https://github.com/Icinga/icingaweb2-module-director) please go to the Director
Dashboard and choose *Import data sources*.

This brings you to your list of configured **Import Source** definitions,
we want to **Add** a new one.

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

The **key column name** defaults to "name" and will be appropriate in most
situations.

The **Azure Tenant ID, Subscription ID, Client ID** and **Client** Secret are
the credentials you have to obtain from your Azure Account. Please confere the
appropriate documentation on this.

**Object Type** is a dropdown list showing the object types this importer
plugin can query from the Azure API. Depending on the object type the
importer queries, it will return different column names matching the data
available on these objects in the Azure data structures.

Therefore, you cannot mix object types within one importer instance. You
will have to configure an importer for virtual machines and another for
retrieving information on load balancers eg.

**Resource Groups** can contain a list of resource group names in your Azure
account. You can keep this empty. In this case, the importer will choose any
resource group available for the given credentials. If you have multiple
resource groups available to the given credentials, you can enter the names
of the resource groups to be imported here. Make shure, you split these
with a single space.


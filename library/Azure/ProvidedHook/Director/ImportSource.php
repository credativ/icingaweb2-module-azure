<?php
namespace Icinga\Module\Azure\ProvidedHook\Director;

use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Web\Form\QuickForm;

use Icinga\Exception\ConfigurationError;
use Icinga\Exception\AuthenticationException;

use Icinga\Application\Logger;

// import classes for importer
use Icinga\Module\Azure\VirtualMachines;
use Icinga\Module\Azure\LoadBalancers;
use Icinga\Module\Azure\AppGW;
use Icinga\Module\Azure\ExpGW;
use Icinga\Module\Azure\MsPgSQL;


class ImportSource extends ImportSourceHook
{

    /** @var Api */
    private $api;   // stores API object

    
    /** names, fields and shortcut codes for objects */
    
    const supportedObjectTypes = array(
        'vm'      => array(
            'name'   => 'Virtual Machines',
            'class'  => 'Icinga\Module\Azure\VirtualMachines',
            'fields' =>  array(
                'name',
                'id',
                'location',
                'osType',
                'osDiskName',
                'dataDisks',
                'network_interfaces_count',
                'publicIP',
                'privateIP',
                'cores',
                'resourceDiskSizeInMB',
                'memoryInMB',
                'maxDataDiscCount',
            ),
        ),
        
        'lb'      => array(
            'name'   => 'Load Balancers',
            'class'  => 'Icinga\Module\Azure\LoadBalancers',
            'fields' => array(
                'name',
                'id',
                'location',
                'provisioningState',
                'frontEndPublicIP',              
            ),
        ),
        
        'appgw'   => array(
            'name'   => 'Application Gateways',
            'class'  => 'Icinga\Module\Azure\AppGW',
            'fields' => array(
                'name',
                'id',
                'location',
                'provisioningState',
                'frontEndPublicIP',
                'frontEndPrivateIP',
                'operationalState',
                'frontEndPort',
                'enabledHTTP2',
                'enabledWAF',              
            ),
        ),

        'expgw'   => array(
            'name'   => 'Express Route Circuits',
            'class'  => 'Icinga\Module\Azure\ExpGW',
            'fields' => array(
                'name',
                'id',
                'location',
                'provisioningState',
                'bandwidthInMbps',
                'circuitProvisioningState',
                'allowClassicOperations',
                'peeringLocation',
                'serviceProviderName',
                'serviceProviderProvisioningState',
            ),
        ),
        
        'mspgsql' => array(
            'name'   => 'Db for PostgreSQL',
            'class'  => 'Icinga\Module\Azure\MsPgSQL',
            'fields' =>  array(
                'name',
                'id',
                'location',
                'version',
                'tier',
                'capacity',
                'sslEnforcement',
                'userVisibleState',
                'fqdn',
                'earliestRestoreDate',
                'storageMB',
                'backupRetentionDays',
                'geoRedundantBackup',
            )
        ),
        
    );

        
    public function getName()
    {
        return 'Microsoft Azure';
    }


    public function fetchData()
    {

        $start = microtime(true);
        $query = $this->getObjectType();
            
        // query config which resourceGroups to deal with
        $rg = $this->getSetting('resource_group_names', '');


        // retrieve all data we want from the class we choose
        $objects = $this->api($query)->getAll( $rg );
        
 
        // log some timing data
        $duration = microtime(true) - $start;
        Logger::info('Azure API: %s import run took %f seconds',
                     self::supportedObjectTypes[$query]['name'],
                     $duration);
        
        return $objects;
    }

    
    protected function api($query)
    {
        if ($this->api === null) {

            // api is uninitialized, create it.
            // therefore we're doing some magic:
            // the class name is stored in a static const array
            // which is used for configuration stuff like the visible
            // name. Sadly we have to make use of a helper variable here
            // as PHP does not permit the const string in the new command
            // and I don't want to use the reflection stuff to keep things
            // simple
            $myclassname = self::supportedObjectTypes[$query]['class'];
            $this->api = new $myclassname(
                $this->getSetting('tenant_id'),
                $this->getSetting('subscription_id'),
                $this->getSetting('client_id'),
                $this->getSetting('client_secret')
            );
        }
        return $this->api;
    }


    public function listColumns()
    {
        return self::supportedObjectTypes[$this->getObjectType()]['fields'];
    }

    
    /**
     * @inheritdoc
     */
    public static function getDefaultKeyColumnName()
    {
        return 'name';
    }


    
    protected function getObjectType()
    {
        // Compat for old configs, vm used to be the only available type:
        $type = $this->getSetting('object_type', 'vm');
        if (! in_array($type, array('vm', 'lb', 'appgw', 'expgw', 'mspgsql'))) {
            Logger::error('Azure API: Got invalid Azure object type: "%s"',
                          $type);
            throw new ConfigurationError(
                'Azure API: Got invalid Azure object type: "%s"',
                $type
            );
        }
        return $type;
    }

    
    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'tenant_id', array(
            'label'        => $form->translate('Azure Tenant ID'),
            'description'  => $form->translate(
                'This is the Azure Tenant ID of the account you '.
                'want to query. This Subscription ID must match the one '.
                'you used to create the application clients credentials with.'),
            'required'     => true,
        ));
        $form->addElement('text', 'subscription_id', array(
            'label'        => $form->translate('Azure Subscription ID'),
            'description'  => $form->translate(
                'This is the Azure Subscription ID of the account you '.
                'want to query. This Subscription ID must match the one '.
                'you used to create the application clients credentials with.'),
            'required'     => true,
        ));
        $form->addElement('text', 'client_id', array(
            'label'        => $form->translate('Azure client ID'),
            'description'  => $form->translate(
                'This is the Azure client ID of the account you '.
                'want to query. This ID must be generated on '.
                'https://portal.azure.com using the Tenant ID and '.
                'Subscription ID above.'),
            'required'     => true,
        ));
        $form->addElement('text', 'client_secret', array(    // TODO: set 'text' to 'password'
            'label'        => $form->translate('Azure client secret'),
            'description'  => $form->translate(
                'This is the secret you got when creating the Client ID.'),
            'required'     => true,
        ));
        $form->addElement('select', 'object_type', array(
            'label'        => 'Object type',
            'required'     => true,
            'description'  => $form->translate(
                'Object type to import. This Azure API importer can deal '.
                'with one object type only. To have multiple object types, '.
                'e.g. VM and LoadBalancers in your import, you need to '.
                'add this Azure API importer multiple times.'
            ),
            'multiOptions' => $form->optionalEnum(
                static::enumObjectTypes($form)
            ),
            'class'        => 'autosubmit',
        ));
        $form->addElement('text', 'resource_group_names', array(
            'label'        => $form->translate('Resource Groups'),
            'description'  => $form->translate(
                'Enter the Resource Group names you want to query. '.
                'Intersect them with a space or leave this empty '.
                'to query all resource groups in your account. '.
                'Please note that these names are case sensitive.'),
            'required'     => false,
        ));

    }

    protected static function enumObjectTypes($form)
    {
        $list = array();
        
        foreach(self::supportedObjectTypes as $key => $value)
        {
            $list[$key] = $form->translate($value['name']);
        }

        return $list;
    }
    
}

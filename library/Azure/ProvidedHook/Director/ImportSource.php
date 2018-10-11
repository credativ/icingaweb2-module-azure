<?php
namespace Icinga\Module\Azure\ProvidedHook\Director;

use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Web\Form\QuickForm;

use Icinga\Exception\ConfigurationError;
use Icinga\Exception\AuthenticationException;

use Icinga\Module\Azure\Api;

use Icinga\Application\Logger;


class ImportSource extends ImportSourceHook
{

    /** @var Api */
    private $api;   // stores API object

    public function __construct( )
    {
    }
              

        
    public function getName()
    {
        return 'Microsoft Azure';
    }


    public function fetchData()
    {
        // query config which resourceGroups to deal with
        $rg = $this->getSetting('resource_group_names', '');
        
        switch($this->getObjectType())
        {
        case 'vm':
            $objects = $this->api()->getAllVM( $rg );
            break;
        case 'lb':
            $objects = $this->api()->getAllLB( $rg );
            break;
        case 'appgw':
            $objects = $this->api()->getAllAppGW( $rg );
            break;
        }

        return $objects;
    }

    
    protected function api()
    {
        if ($this->api === null) {
            // api is uninitialized, create it.
            $this->api = new Api(
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
        switch($this->getObjectType())
        {
        case 'vm':
            return array(
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
            );
        case 'lb':
            return array(
                'name',
                'id',
                'location',
                'provisioningState',
                'frontEndPublicIP',              
            );
        case 'appgw':
            return array(
                'name',
                'id',
                'location',
                'provisioningState',
                'frontEndPublicIP',
                'frontEndPrivateIP',
                'operationalState',
                'frontEndPort',
                'httpFrontEndPort',
                'enabledHTTP2',
                'enabledWAF',              
            );
        }
        
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
        if (! in_array($type, array('vm', 'lb', 'appgw'))) {
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
        $form->addElement('text', 'client_secret', array(
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
        return array(
            'vm'          => $form->translate('Virtual Machines'),
            'lb'          => $form->translate('Load Balancers'),
            'appgw'       => $form->translate('Application Gateways'),
        );
    }
    
}

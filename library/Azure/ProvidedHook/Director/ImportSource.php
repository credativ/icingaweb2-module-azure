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
        // make shure, we are connected to the Azure API
        // preload api credentials and generate bearer token      
        $objects = $this->api()->getAll();

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
        
    }

    /**
     * @inheritdoc
     */
    public static function getDefaultKeyColumnName()
    {
        return 'name';
    }

    
    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'tenant_id', array(
            'label'        => $form->translate('Azure Tenant ID'),
            'description'  => $form->translate('This is the Azure Tenant ID of the account you '.
                                               'want to query. This Subscription ID must match the one '.
                                               'you used to create the application clients credentials with.'),
            'required'     => true,
        ));
        $form->addElement('text', 'subscription_id', array(
            'label'        => $form->translate('Azure Subscription ID'),
            'description'  => $form->translate('This is the Azure Subscription ID of the account you '.
                                               'want to query. This Subscription ID must match the one '.
                                               'you used to create the application clients credentials with.'),
            'required'     => true,
        ));
        $form->addElement('text', 'client_id', array(
            'label'        => $form->translate('Azure client ID'),
            'description'  => $form->translate('This is the Azure client ID of the account you '.
                                               'want to query. This ID must be generated on '.
                                               'https://portal.azure.com using the Tenant ID and Subscription ID above.'),
            'required'     => true,
        ));
        $form->addElement('text', 'client_secret', array(
            'label'        => $form->translate('Azure client secret'),
            'description'  => $form->translate('This is the secret you got when creating the Client ID.'),
            'required'     => true,
        ));
    }

}

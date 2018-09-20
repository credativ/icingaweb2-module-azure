<?php
namespace Icinga\Module\Azure\ProvidedHook\Director;

use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Web\Form\QuickForm;

use Icinga\Module\Azure\Api;


use restclient\restclient;


class ImportSource extends ImportSourceHook
{

    /** @var Api */
    protected $api;
    

    public function getName()
    {
        return 'Microsoft Azure';
    }


    public function fetchData()
    {
        $api = $this->api();

        //        $objects = $this->callOnManagedObject('fetchWithDefaults', $api);
        //        $api->idLookup()->enrichObjects($objects);

        return Util::createNestedObjects($objects);
    }

    
    protected function api()
    {
        if ($this->api === null) {
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
        // TODO: Taken from AWS, must adapted to Azure
        return array(
            'name',
            'image',
            'architecture',
            'root_device_type',
            'root_device_name',
            'hypervisor',
            'instance_type',
            'virt_type',
            'public_ip',
            'public_dns',
            'private_ip',
            'private_dns',
            'monitoring_state',
            'security_groups',
            'tags',
            'tags.Name',
            'tags.aws:autoscaling:groupName',
            'tags.aws:cloudformation:logical-id',
            'tags.aws:cloudformation:stack-id',
            'tags.aws:cloudformation:stack-name',
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

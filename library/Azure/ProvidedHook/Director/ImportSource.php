<?php
/** ***************************************************************************
 * @author Peter Dreuw <peter.dreuw@credativ.de>
 * @copyright Copyright (c) 2018 creadtiv GmbH
 * @license https://github.com/credativ/icingaweb2-module-azure/blob/master/LICENSE MIT License
 *
 *
 */
namespace Icinga\Module\Azure\ProvidedHook\Director;

use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Web\Form\QuickForm;

use Icinga\Exception\ConfigurationError;
use Icinga\Exception\AuthenticationException;

use Icinga\Application\Logger;

// import classes for importer
use Icinga\Module\Azure\VirtualMachines;
use Icinga\Module\Azure\VirtualMachinesDisks;
use Icinga\Module\Azure\VirtualMachinesInterfaces;
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
            'fields' => VirtualMachines::FIELDS_RETURNED,
        ),

        'vmdisks' => array(
            'name'   => 'Virtual Machines (Disks)',
            'class'  => 'Icinga\Module\Azure\VirtualMachinesDisks',
            'fields' => VirtualMachinesDisks::FIELDS_RETURNED,
        ),

        'vmnetif' => array(
            'name'   => 'Virtual Machines (Interfaces)',
            'class'  => 'Icinga\Module\Azure\VirtualMachinesInterfaces',
            'fields' => VirtualMachinesInterfaces::FIELDS_RETURNED,
        ),

        'lb'      => array(
            'name'   => 'Load Balancers',
            'class'  => 'Icinga\Module\Azure\LoadBalancers',
            'fields' => LoadBalancers::FIELDS_RETURNED,
        ),

        'appgw'   => array(
            'name'   => 'Application Gateways',
            'class'  => 'Icinga\Module\Azure\AppGW',
            'fields' => AppGW::FIELDS_RETURNED,
        ),

        'expgw'   => array(
            'name'   => 'Express Route Circuits',
            'class'  => 'Icinga\Module\Azure\ExpGW',
            'fields' => ExpGW::FIELDS_RETURNED,
        ),

        'mspgsql' => array(
            'name'   => 'Db for PostgreSQL',
            'class'  => 'Icinga\Module\Azure\MsPgSQL',
            'fields' =>  MsPgSQL::FIELDS_RETURNED,
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
                $this->getSetting('client_secret'),
                $this->getSetting('proxy',''),
                $this->getSetting('con_timeout',0),
                $this->getSetting('timeout', 0)
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
        if (! array_key_exists($type, self::supportedObjectTypes)) {
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
        $form->addElement('text', 'proxy', array(
            'label'        => $form->translate('Proxy url'),
            'description'  => $form->translate(
                "Enter your proxy configuration in following format: ".
                "'http://example.com:Port' OR ".
                "'socks5://example.com:port' etc."),
            'required'     => false,
        ));
        $form->addElement('text', 'con_timeout', array(
            'label'        => $form->translate('Connection timeout'),
            'description'  => $form->translate(
                'Connection timeout in seconds. This is the maximum '.
                'time to wait until giving up. Set to "0" to wait '.
                'infinetly'),
            'required'     => false,
        ));
        $form->addElement('text', 'timeout', array(
            'label'        => $form->translate('Request timeout'),
            'description'  => $form->translate(
                'Timeout in seconds to wait for the request to finish. '.
                'Please note, that the Azure API might not be too fast, so '.
                'don\'t choose this too short. Make shure, your PHP process '.
                'does not timeout meanwhile (i.e. check your php settings). '.
                'Setting this to 0 means no timeout. This timeout starts '.
                'after the connection is established and is per CURL request.'.
                'A full import run may consist of several requests!'),
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

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
use Icinga\Module\Azure\ExpGWauth;
use Icinga\Module\Azure\MsPgSQL;
use Icinga\Module\Azure\ResourceGroup;
use Icinga\Module\Azure\Subscription;


class ImportSource extends ImportSourceHook
{

    /** @var Api */
    private $api;   // stores API object


    private const RESOURCE_GROUP_JOKER = "<*all*>";

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

        'expgwauth'  => array(
            'name'   => 'Express Route Circuits (Authorization)',
            'class'  => 'Icinga\Module\Azure\ExpGWauth',
            'fields' => ExpGWauth::FIELDS_RETURNED,
        ),

        'mspgsql' => array(
            'name'   => 'Db for PostgreSQL',
            'class'  => 'Icinga\Module\Azure\MsPgSQL',
            'fields' =>  MsPgSQL::FIELDS_RETURNED,
        ),
        'resgrp'  => array(
            'name'   => 'Resource Groups',
            'class'  => 'Icinga\Module\Azure\ResourceGroup',
            'fields' =>  ResourceGroup::FIELDS_RETURNED,
        ),
        'subscr'  => array(
            'name'   => 'Subscriptions',
            'class'  => 'Icinga\Module\Azure\Subscription',
            'fields' =>  Subscription::FIELDS_RETURNED,
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
        if ($rg == self::RESOURCE_GROUP_JOKER)
            $rg = '';

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


    /**
     * @inheritdoc
     */
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
        // get connection relevant information first

        $form->addElement('text', 'proxy', array(
            'label'        => $form->translate('Proxy url'),
            'description'  => $form->translate(
                "Enter your proxy configuration in following format: ".
                "'http://example.com:Port' OR ".
                "'socks5://example.com:port' etc."),
            'required'     => false,
            'class'        => 'autosubmit',
        ));
        $form->addElement('text', 'con_timeout', array(
            'label'        => $form->translate('Connection timeout'),
            'description'  => $form->translate(
                'Connection timeout in seconds. This is the maximum '.
                'time to wait until giving up. Set to "0" to wait '.
                'infinetly'),
            'required'     => false,
            'class'        => 'autosubmit',
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
            'class'        => 'autosubmit',
        ));

        // get minimum credentials necessary
        $form->addElement('text', 'tenant_id', array(
            'label'        => $form->translate('Azure Tenant ID'),
            'description'  => $form->translate(
                'This is the Azure Tenant ID of the account you '.
                'want to query. This Subscription ID must match the one '.
                'you used to create the application clients credentials with.'),
            'required'     => true,
            'class'        => 'autosubmit',
        ));
        $form->addElement('text', 'client_id', array(
            'label'        => $form->translate('Azure client ID'),
            'description'  => $form->translate(
                'This is the Azure client ID of the account you '.
                'want to query. This ID must be generated on '.
                'https://portal.azure.com using the Tenant ID and '.
                'Subscription ID above.'),
            'required'     => true,
            'class'        => 'autosubmit',
        ));
        $form->addElement('text', 'client_secret', array(    // TODO: set 'text' to 'password'
            'label'        => $form->translate('Azure client secret'),
            'description'  => $form->translate(
                'This is the secret you got when creating the Client ID.'),
            'required'     => true,
            'class'        => 'autosubmit',
        ));

        
        $tenant_id     = $form->getSentOrObjectSetting('tenant_id');
        $client_id     = $form->getSentOrObjectSetting('client_id');
        $client_secret = $form->getSentOrObjectSetting('client_secret');

        // if the minimum credentials are not set, stay where we are...
        if ((!$tenant_id) or (!$client_id) or (!$client_secret))
            return;

        // if we got enough credential information, lets find
        // available subscriptions first
        try
        {
            $api = new Subscription(
                $tenant_id, "",
                $client_id, $client_secret,
                $form->getSentOrObjectSetting('proxy'),
                intval($form->getSentOrObjectSetting('con_timeout')),
                intval($form->getSentOrObjectSetting('timeout')));

            $subscr = $api->getAll("");
        }
        catch(Exception $e)
        {
            // in case something went wrong.. stay here...
            Logger::info("Azure API: could not find subscription ID when creating importer.");
            return;
        }

        // show retrieved subscriptions available for credentials
        $form->addElement('select', 'subscription_id', array(
            'label'        => $form->translate('Azure Subscription ID'),
            'description'  => $form->translate(
                'This is the Azure Subscription ID of the account you '.
                'want to query. This Subscription ID must match the one '.
                'you used to create the application clients credentials with.'),
            'required'     => true,
            'multiOptions' => $form->optionalEnum(
                static::enumSubscriptions($subscr)
            ),
            'class'        => 'autosubmit',
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


        $subscription_id = $form->getSentOrObjectSetting('subscription_id');

        // if the minimum credentials are not set, stay where we are...
        if (!$subscription_id)
            return;

        // if we got enough credential information, lets find
        // available resource groups
        try
        {
            $api = new ResourceGroup(
                $tenant_id, $subscription_id,
                $client_id, $client_secret,
                $form->getSentOrObjectSetting('proxy'),
                intval($form->getSentOrObjectSetting('con_timeout')),
                intval($form->getSentOrObjectSetting('timeout')));

            $resgroup = $api->getAll("");
        }
        catch(Exception $e)
        {
            // in case something went wrong.. stay here...
            Logger::info("Azure API: could not find resource groups when creating importer.");
            return;
        }


        $form->addElement('select', 'resource_group_names', array(
            'label'        => $form->translate('Resource Groups'),
            'description'  => $form->translate(
                'Enter the Resource Group names you want to query. '.
                'Intersect them with a space or leave this empty '.
                'to query all resource groups in your account. '.
                'Please note that these names are case sensitive.'),
            'required'     => false,

            // does not work as needed yet
            //            'multiple' => 'true',

            'multiOptions' => $form->optionalEnum(
                static::enumresourceGroups($form, $resgroup)
            ),
        ));


        $object_type = $form->getSentOrObjectSetting('object_type');

        // if the minimum credentials are not set, stay where we are...
        if (!$object_type)
            return;


        // ok, to this point, we got anything we need for standard configuration
        // but there might be some object class specific stuff to be done.
        // so call the object class form configuration method, which is a bit
        // trick because the class name is part of the form information accuired
        // somewhat above.

        // to do this, we have to do the same as in $this->api()


        try {
            $myclassname = self::supportedObjectTypes[$object_type]['class'];
            $temp_api = new $myclassname(
                $tenant_id, $subscription_id,
                $client_id, $client_secret,
                $form->getSentOrObjectSetting('proxy'),
                intval($form->getSentOrObjectSetting('con_timeout')),
                intval($form->getSentOrObjectSetting('timeout'))
            );

            $temp_api->extendForm( $form );
        }
        catch(Exception $e)
        {
            // in case something went wrong.. stay here...
            Logger::info("Azure API: could not use form extensions when creating importer.");
            return;
        }

    }


    protected static function enumSubscriptions($subscr)
    {
        $list = array();

        foreach($subscr as $sub)
        {
            $list[$sub->subscriptionId] = $sub->name.' ('.$sub->subscriptionId.')';
        }

        return $list;
    }


    protected static function enumResourceGroups($form, $groups)
    {
        $list = array();

        $list[self::RESOURCE_GROUP_JOKER] = $form->translate('<all resource groups>');

        foreach($groups as $group)
        {
            $list[$group->name] = $group->name;
        }

        return $list;
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

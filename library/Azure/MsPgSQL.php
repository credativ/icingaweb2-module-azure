<?php

namespace Icinga\Module\Azure;

use Icinga\Exception\ConfigurationError;
use Icinga\Exception\QueryException;
use Icinga\Application\Logger;

use Icinga\Module\Azure\Api;

/**
 * Class Api
 *
 * This is your main entry point when querying Application Gateways from 
 * Azure API. 
 *
 */



class MsPgSQL extends Api
{

    /**
     * Log Message for getAll
     *
     * @staticvar string MSG_LOG_GET_ALL
     */
    protected const
        MSG_LOG_GET_ALL =
        "Azure API: querying any 'DB for PostgreSQL' services in configured resource groups.";

    /**
     * array of field names to be returned by implementation.
     *
     * @staticvar array FIELDS_RETURNED
     */
    public const FIELDS_RETURNED = array(
        'name',
        'subscriptionId',
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
    );


    /** ***********************************************************************
     * takes all information on Microsoft.DBforPostgreSQL from a resource group and 
     * returns it in the format IcingaWeb2 Director expects
     *
     * @return array of objects
     *
     */

    public function scanResourceGroup($group)
    {
        // only items that have a valid provisioning state
        if ($group->properties->provisioningState != "Succeeded")
        {
            Logger::info("Azure API: Resoure group ".$group->name.
                         " invalid provisioning state.");
            return array();
        }

        // get data needed
        $dbservers = $this->getDbForPostgreSQL($group);

        $objects = array();

        foreach($dbservers as $current) 
        {
            $object = (object) [
                'name'                => $current->name,
                'subscriptionId'      => $this->subscription_id,
                'id'                  => $current->id,
                'location'            => $current->location,
                'version'             => $current->properties->version ,
                'tier'                => $current->sku->tier,
                'capacity'            => $current->sku->capacity,
                'sslEnforcement'      => $current->properties->sslEnforcement,
                'userVisibleState'    => $current->properties->userVisibleState,
                'fqdn'                => $current->properties->fullyQualifiedDomainName,
                'earliestRestoreDate' => $current->properties->earliestRestoreDate,
                'storageMB'           => $current->properties->storageProfile->storageMB,
                'backupRetentionDays' => $current->properties->storageProfile->backupRetentionDays,
                'geoRedundantBackup'  => $current->properties->storageProfile->geoRedundantBackup,
            ];

            // add this VM to the list.
            $objects[] = $object;
        }
        return $objects;
    }
}

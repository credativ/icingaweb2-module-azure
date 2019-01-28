<?php
/** ***************************************************************************
 * @author Peter Dreuw <peter.dreuw@credativ.de>
 * @copyright Copyright (c) 2018, 2019 credativ GmbH
 * @license https://github.com/credativ/icingaweb2-module-azure/blob/master/LICENSE MIT License
 *
 *
 */
namespace Icinga\Module\Azure;

use Icinga\Exception\ConfigurationError;
use Icinga\Exception\QueryException;
use Icinga\Application\Logger;

use Icinga\Module\Azure\Api;

/**
 * Class Api
 *
 * This is your main entry point when querying PostgreSQL Servers from
 * Azure API.
 *
 */

class MsPgSQLLocBasedPerfTier extends Api
{

    /**
     * Log Message for getAll
     *
     * @staticvar string MSG_LOG_GET_ALL
     */
    protected const
        MSG_LOG_GET_ALL =
        "Azure API: querying any 'DB for PostgreSQL' location based ".
        "performance tier in configured subscription.";

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
        'type',
        'metricDefinitions',
        'sloEdition',
        'sloHardwareGeneration',
        'sloId',
        'sloMaxBackupRetentionDays',
        'sloMaxStorageMB',
        'sloMinBackupRetentionDays',
        'sloMinStorageMB',
        'sloVCore',
    );


    /** ***********************************************************************
     * replaces Api->getAll as this is not Resource Group bound in the API and 
     * there is no need to iterate over the resource groups.
     *
     * takes all information on Microsoft.DBforPostgreSQL location based
     * performance tier for a given subscription and returns it in the format
     * IcingaWeb2 Director expects
     *
     * @param string $rgn
     * a space separated list of resoureceGroup names to query or '' for all
     * her: this is ignored. 
     *
     * @return array of objects
     *
     */

    public function getAll($rgn)
    {
        Logger::info(static::MSG_LOG_GET_ALL);

        // get data needed
        $lbpt = $this-> getPostgreSQLLocationBasedPerformanceTier( $rgn );

        $objects = array();

        foreach($lbpt as $location => $tiers)
        {

            foreach($tiers as $current)
                foreach($current->serviceLevelObjectives as $slo)
                {
                    $object = (object) [
                        'name'                     => $current->id,
                        'subscriptionId'           => $this->subscription_id,
                        'id'                       => $current->id,
                        'location'                 => $location,
                        'type'                     =>
                        'Microsoft.DBforPostgreSQL/locations/performanceTiers',
                        'metricDefinitions'        => NULL,
                        'sloEdition'               => $slo->edition,
                        'sloHardwareGeneration'    => (
                            property_exists($slo, "hardwareGeneration") ?
                            $slo->hardwareGeneration: NULL
                        ),
                        'sloId'                    => $slo->id,
                        'sloMaxBackupRetentionDays'=> (
                            property_exists($slo, "maxBackupRetentionDays") ?
                            $slo->maxBackupRetentionDays : NULL
                        ),
                        'sloMaxStorageMB'          => (
                            property_exists($slo, "maxStorageMB") ?
                            $slo->maxStorageMB : NULL
                        ),
                        'sloMinBackupRetentionDays'          => (
                            property_exists($slo, "minBackupRetentionDays") ?
                            $slo->minBackupRetentionDays : NULL
                        ),
                        'sloMinStorageMB'          => (
                            property_exists($slo, "minStorageMB") ?
                            $slo->minStorageMB : NULL
                        ),
                        'sloVCore'          => (
                            property_exists($slo, "vCore") ?
                            $slo->vCore : NULL
                        ),
                    ];
                    // add this VM to the list.
                    $objects[] = $object;
                }
        }

        Logger::debug( "Azure API: returning ". count( $objects ).
                       " elements." );

        return $objects;
    }

    function scanResourceGroup($group)
    {
    }
}

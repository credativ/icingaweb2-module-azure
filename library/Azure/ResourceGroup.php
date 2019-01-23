<?php
/** ***************************************************************************
 * @author Peter Dreuw <peter.dreuw@credativ.de>
 * @copyright Copyright (c) 2018 credativ GmbH
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
 * This is your main entry point when querying Resource Groups from
 * Azure API.
 *
 * This API implementation might not be too usefull in Icinga2 itself
 * but we need it for our dynamic configuration menu in ImportSource
 *
 */


class ResourceGroup extends Api
{

    /**
     * Log Message for getAll
     *
     * @staticvar string MSG_LOG_GET_ALL
     */
    protected const
        MSG_LOG_GET_ALL =
        "Azure API: querying any resource groups available with credentials.";

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
        'provisioningState',
    );


    /** ***********************************************************************
     * takes all information on application gateways from a resource group and
     * returns it in the format IcingaWeb2 Director expects
     *
     * @return array of objects
     *
     */

    public function scanResourceGroup($current)
    {
        // this is pretty much a fake generator as
        // the getAll function in parent class already pulled
        // the resource groups for us.
        // But to avoid code overhead, we simply return a
        // one-element-array with the known data for the
        // current group.
        $object = (object) [
            'name'              => $current->name,
            'id'                => $current->id,
            'subscriptionId'    => $this->subscription_id,
            'location'          => $current->location,
            'type'              => 'resourceGroups',
            'provisioningState' => $current->properties->provisioningState,
        ];

        $objects[] = $object;

        return $objects;
    }
}

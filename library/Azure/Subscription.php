<?php

namespace Icinga\Module\Azure;

use Icinga\Exception\ConfigurationError;
use Icinga\Exception\QueryException;
use Icinga\Application\Logger;

use Icinga\Module\Azure\Api;

/**
 * Class Api
 *
 * This is your main entry point when querying subscriptions from 
 * Azure API. 
 *
 * This API implementation might not be too usefull in Icinga2 itself
 * but we need it for our dynamic configuration menu in ImportSource
 *
 */


class Subscription extends Api
{

    /** 
     * Log Message for getAll 
     *
     * @staticvar string MSG_LOG_GET_ALL
     */
    protected const
        MSG_LOG_GET_ALL =
        "Azure API: querying any subscription available with credentials.";

    /**
     * array of field names to be returned by implementation.
     *
     * @staticvar array FIELDS_RETURNED
     */
    public const FIELDS_RETURNED = array(
        'name',
        'subscriptionId',
        'id',
        'state',
        'locationPlacementId',
        'quotaId',
        'spendingLimit',
    );


    /** ***********************************************************************
     * Returns an array of subscription objects.
     *
     * @param string $rgn 
     * A space separated list of resoureceGroup names to query or '' for all.
     * This will be ignored as resource groups are bound to subscriptions,
     * so there is nothing to filter with this parameter.
     *
     * This code replaces the parental code, that is used normally for sake
     * of this special case. 
     *
     * @return array of objects
     *
     */

    public function getAll( $rgn )
    {      
        Logger::info(static::MSG_LOG_GET_ALL);

        $objects = array();

        $objects = $this->scanResourceGroup( "" );
       
        return $objects;
    }


    
        
    /** ***********************************************************************
     * takes all information on application gateways from a resource group and 
     * returns it in the format IcingaWeb2 Director expects
     *
     * @return array of objects
     *
     */

    public function scanResourceGroup($ignored)
    {
        // this somewhat breaks the meta because resource groups
        // are bound to subscriptions, so the resource group name
        // can be ignored.
        // in this special case, we have to do our own query.

        $subs = $this->getSubscriptions();

        foreach( $subs as $subscr )
        {
            $objects[] = (object) [
                'name'                => $subscr->displayName,
                'subscriptionId'      => $subscr->subscriptionId,
                'id'                  => $subscr->id,
                'state'               => $subscr->state,
                'locationPlacementId' => $subscr->subscriptionPolicies->locationPlacementId,
                'quotaId'             => $subscr->subscriptionPolicies->quotaId,
                'spendingLimit'       => $subscr->subscriptionPolicies->spendingLimit,
            ];
        }

        return $objects;
    }   
}

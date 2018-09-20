<?php

namespace Icinga\Module\Azure;

use Icinga\Exception\ConfigurationError;
use Icinga\Module\Azure\Constants;
use Icinga\Module\Azure\Token;

use restclient\restclient;


/**
 * Class Api
 *
 * This is your main entry point when working with this library
 */


class Api
{
    /** @var token stores the current token object if initialized */
    private $token;

    /**
     * Api constructor.
     *
     * @param string $tenant
     * @param string $subscription
     * @param string $client
     * @param string $secret
     */
    public function __construct( $tenant_id, $subscription_id,
                                 $client_id, $client_secret )
    {
        // get bearer token for API access with given credentials 
        $this->token = new Token( tenant,
                                  subscription_id,
                                  client_id,
                                  client_secret );
    }

}

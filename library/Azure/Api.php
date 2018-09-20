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
    /** @var token */
    private $token;

    /** @var string tenant_id */
    private $tenant_id;
    
    /** @var string subscription_id */
    private $subscription_id;

    /** @var string client_id */
    private $client_id;

    /** @var string client_secret */
    private $client_secret;

    /**
     * Api constructor.
     *
     * @param string $tenant
     * @param string $subscription
     * @param string $client
     * @param string $secret
     */
    public function __construct($tenant, $subscription, $client, $secret)
    {
        $this->tenant_id       = $tenant;
        $this->subscription_id = $subscription;
        $this->client_id       = $client;
        $this->client_secret   = $secret;

        $this->token = null;
        
        $this->getToken();
    }

    private function getToken()
    {
        if (($this->token = null) or ($this->token->expired()))
        {
            $this->token = new Token( $this->tenant,
                                      $this->subscription_id,
                                      $this->client_id,
                                      $this->client_secret );
        }
        return $this->token->getBearer();
    }

}


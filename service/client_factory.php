<?php

declare(strict_types=1);

/**
 *
 * Spamtroll Anti-Spam extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Spamtroll
 * @license   GPL-2.0-only
 *
 */

namespace spamtroll\phpbb\service;

use Spamtroll\Sdk\Client;
use Spamtroll\Sdk\ClientConfig;
use Spamtroll\Sdk\Http\HttpClientInterface;

/**
 * Builds {@see \Spamtroll\Sdk\Client} instances from the live phpBB
 * configuration, so settings changes in the ACP take effect on the next
 * request without a service-container rebuild.
 */
class client_factory
{
    /** @var \phpbb\config\config */
    protected $config;

    protected HttpClientInterface $http;

    /**
     * @param \phpbb\config\config $config
     */
    public function __construct($config, HttpClientInterface $http)
    {
        $this->config = $config;
        $this->http = $http;
    }

    public function build(): Client
    {
        $base_url = (string) $this->config['spamtroll_api_url'];
        if ($base_url === '') {
            $base_url = ClientConfig::DEFAULT_BASE_URL;
        }

        $timeout = (int) $this->config['spamtroll_timeout'];
        if ($timeout < 1) {
            $timeout = ClientConfig::DEFAULT_TIMEOUT;
        }

        $sdk_config = new ClientConfig(
            $base_url,
            $timeout,
            ClientConfig::DEFAULT_MAX_RETRIES,
            ClientConfig::DEFAULT_RETRY_BASE_DELAY_MS,
            'spamtroll-phpbb/0.1.0'
        );

        return new Client((string) $this->config['spamtroll_api_key'], $sdk_config, $this->http);
    }
}

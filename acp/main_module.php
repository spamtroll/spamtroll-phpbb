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

namespace spamtroll\phpbb\acp;

use spamtroll\phpbb\service\client_factory;
use Spamtroll\Sdk\Exception\SpamtrollException;

/**
 * ACP "Settings" form for the Spamtroll extension.
 *
 * Mounted by phpBB through {@see main_info} and instantiated as a regular
 * service so dependencies can be injected. The class is intentionally
 * thin — it just maps form fields to config rows and exposes a
 * "Test connection" action that hits `GET /scan/status` via the SDK.
 */
class main_module
{
    public string $u_action = '';
    public string $page_title = '';
    public string $tpl_name = '';

    /** @var \phpbb\config\config */
    protected $config;

    protected client_factory $factory;

    /** @var \phpbb\language\language */
    protected $language;

    /** @var \phpbb\request\request_interface */
    protected $request;

    /** @var \phpbb\template\template */
    protected $template;

    /** @var \phpbb\user */
    protected $user;

    /**
     * Field name => default value. Drives both rendering and persistence.
     *
     * @var array<string, string|int>
     */
    private const FIELDS = [
        'spamtroll_api_key' => '',
        'spamtroll_api_url' => 'https://api.spamtroll.io/api/v1',
        'spamtroll_timeout' => 5,
        'spamtroll_spam_threshold' => '0.7',
        'spamtroll_suspicious_threshold' => '0.4',
        'spamtroll_check_post' => 1,
        'spamtroll_check_pm' => 1,
        'spamtroll_check_registration' => 1,
        'spamtroll_log_retention_days' => 30,
    ];

    /**
     * @param \phpbb\config\config $config
     * @param \phpbb\language\language $language
     * @param \phpbb\request\request_interface $request
     * @param \phpbb\template\template $template
     * @param \phpbb\user $user
     */
    public function __construct($config, client_factory $factory, $language, $request, $template, $user)
    {
        $this->config = $config;
        $this->factory = $factory;
        $this->language = $language;
        $this->request = $request;
        $this->template = $template;
        $this->user = $user;
    }

    public function main(string $id, string $mode): void
    {
        $this->page_title = 'ACP_SPAMTROLL_SETTINGS';
        $this->tpl_name = 'acp_spamtroll_settings';

        if (is_object($this->language) && method_exists($this->language, 'add_lang_ext')) {
            $this->language->add_lang_ext('spamtroll/phpbb', 'acp/spamtroll');
        }

        $action = $this->request->variable('action', '');
        $form_key = 'acp_spamtroll_settings';
        if (function_exists('add_form_key')) {
            add_form_key($form_key);
        }

        if ($action === 'test') {
            $this->handle_test_connection();
        } elseif ($this->request->is_set_post('submit')) {
            if (function_exists('check_form_key') && !check_form_key($form_key)) {
                $this->template->assign_var('S_ERROR', $this->translate('FORM_INVALID'));
            } else {
                $this->save_form();
                $this->template->assign_var('S_SUCCESS', $this->translate('CONFIG_UPDATED'));
            }
        }

        $this->render_form();
    }

    private function save_form(): void
    {
        foreach (self::FIELDS as $key => $default) {
            if (is_int($default)) {
                $value = (int) $this->request->variable($key, (int) $default);
            } else {
                $value = $this->request->variable($key, (string) $default, true);
            }
            $this->config->set($key, (string) $value);
        }
    }

    private function render_form(): void
    {
        foreach (self::FIELDS as $key => $default) {
            $current = $this->config[$key] ?? $default;
            $this->template->assign_var(strtoupper($key), $current);
        }
        $this->template->assign_var('U_ACTION', $this->u_action);
        $this->template->assign_var('U_TEST_ACTION', $this->u_action . '&amp;action=test');
    }

    private function handle_test_connection(): void
    {
        try {
            $client = $this->factory->build();
            if (!$client->isConfigured()) {
                $this->template->assign_var('S_TEST_ERROR', $this->translate('TEST_NO_KEY'));
                return;
            }
            $response = $client->testConnection();
            if ($response->isConnectionValid()) {
                $this->template->assign_var('S_TEST_OK', $this->translate('TEST_OK'));
            } else {
                $msg = $response->error ?? ('HTTP ' . $response->httpCode);
                $this->template->assign_var('S_TEST_ERROR', $this->translate('TEST_FAIL') . ': ' . $msg);
            }
        } catch (SpamtrollException $e) {
            $this->template->assign_var('S_TEST_ERROR', $this->translate('TEST_FAIL') . ': ' . $e->getMessage());
        }
    }

    private function translate(string $key): string
    {
        if (is_object($this->language) && method_exists($this->language, 'lang')) {
            $translated = $this->language->lang($key);
            if (is_string($translated) && $translated !== '' && $translated !== $key) {
                return $translated;
            }
        }
        return $key;
    }
}

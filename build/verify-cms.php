<?php

declare(strict_types=1);

// Guard the real-core smoke check against accidentally touching a production site.
$site = rtrim($argv[1] ?? '', '/') . '/';
if (!is_file($site . 'config.php') ||
    !str_contains(file_get_contents($site . 'config.php'), 'spamtroll_fixture_phpbb')) {
    throw new RuntimeException('Expected the isolated phpBB publication fixture.');
}
define('IN_PHPBB', true);
$phpbb_root_path = $site;
$phpEx = 'php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require $site . 'common.php';
if ($config['sitename'] !== 'Spamtroll disposable publication verification' || $config['email_enable']) {
    throw new RuntimeException('Expected isolated fixture with mail disabled.');
}
if (!$phpbb_container->get('ext.manager')->is_enabled('spamtroll/phpbb')) {
    throw new RuntimeException('Extension is not enabled.');
}
$scanner = $phpbb_container->get('spamtroll.phpbb.scanner');
if (!$scanner instanceof \spamtroll\phpbb\service\scanner || !class_exists(\Spamtroll\Sdk\Client::class)) {
    throw new RuntimeException('Native container did not load scanner and bundled SDK.');
}
$adminRows = $db->sql_query("SELECT * FROM " . $table_prefix . "users WHERE username='fixture'");
$user->data = $db->sql_fetchrow($adminRows);
$db->sql_freeresult($adminRows);
$config->set('spamtroll_quota_skipped_log', json_encode([
    'days' => [gmdate('Y-m-d') => 1], 'last_at' => time(),
    'last_usage' => ['current' => 200, 'limit' => 200, 'plan' => '<script>fixture</script>'],
]));
$module = new \spamtroll\phpbb\acp\main_module();
$module->main('fixture', 'settings');
$notice = $template->retrieve_var('S_QUOTA_SKIPPED_MESSAGE');
if (!is_string($notice) || str_contains($notice, '<script>') || !str_contains($notice, '&lt;script&gt;')) {
    throw new RuntimeException('Native ACP quota notice must escape remote plan text.');
}
$config->delete('spamtroll_quota_skipped_log');
$dispatcher = $phpbb_container->get('dispatcher');
$registration = $dispatcher->trigger_event('core.ucp_register_data_after', [
    'submit' => true, 'data' => ['username' => 'fixture', 'email' => 'fixture@example.invalid'],
    'cp_data' => [], 'error' => [],
]);
if ($registration['error'] !== []) {
    throw new RuntimeException('Native missing-key registration must remain allowed.');
}
$db->sql_query('SELECT COUNT(*) FROM ' . $table_prefix . 'spamtroll_log');
$db->sql_freeresult();
if (!is_file($site . 'ext/spamtroll/phpbb/license.txt')) {
    throw new RuntimeException('Installed GPL text missing.');
}
echo 'Real phpBB ', PHPBB_VERSION, ': enabled native scanner/SDK, registration dispatch, ACP quota HTML escaping, log table and GPL license verified; API key empty and email disabled.', PHP_EOL;

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

namespace spamtroll\phpbb\tests\acp;

use PHPUnit\Framework\TestCase;
use spamtroll\phpbb\acp\main_module;

/**
 * ACP module construction (audit K4).
 *
 * @covers \spamtroll\phpbb\acp\main_module
 */
final class main_module_test extends TestCase
{
    public function test_can_be_built_the_way_phpbb_builds_modules(): void
    {
        // includes/functions_module.php:598-600 does exactly this:
        //     $class_name = $this->p_name;
        //     $this->module = new $class_name($this);
        // and never touches $phpbb_container. A constructor with required
        // arguments therefore threw ArgumentCountError on every visit to
        // ACP → Spamtroll Settings.
        $class_name = main_module::class;
        $module = new $class_name(new \stdClass());

        self::assertInstanceOf(main_module::class, $module);
    }

    public function test_the_properties_phpbb_writes_to_are_declared(): void
    {
        $module = new main_module();

        // functions_module.php:634/667/673 assign u_action, :677 assigns
        // module_path; creating those dynamically is deprecated on PHP 8.2+.
        $module->u_action = 'index.php?i=-spamtroll-phpbb-acp-main_module&amp;mode=settings';
        $module->module_path = 'ext/spamtroll/phpbb/acp/';

        self::assertSame('ext/spamtroll/phpbb/acp/', $module->module_path);
        self::assertSame('', $module->page_title);
        self::assertSame('', $module->tpl_name);
    }

    public function test_no_constructor_is_declared(): void
    {
        $reflection = new \ReflectionClass(main_module::class);

        self::assertNull(
            $reflection->getConstructor(),
            'phpBB passes its module manager positionally; a declared constructor would have to accept it'
        );
    }
}

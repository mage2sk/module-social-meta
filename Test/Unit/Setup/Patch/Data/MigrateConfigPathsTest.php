<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Test\Unit\Setup\Patch\Data;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Panth\SocialMeta\Setup\Patch\Data\MigrateConfigPaths;
use PHPUnit\Framework\TestCase;

class MigrateConfigPathsTest extends TestCase
{
    public function testRewritesLegacyPrefixInPlace(): void
    {
        $captured = [];
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('quote')->willReturnCallback(static fn ($value): string => "'" . $value . "'");
        $connection->method('quoteInto')->willReturnCallback(
            static fn ($text, $value): string => str_replace('?', "'" . $value . "'", $text)
        );
        $connection->method('update')->willReturnCallback(
            static function ($table, array $bind, $where) use (&$captured): int {
                $captured = [$table, $bind, $where];
                return 2;
            }
        );

        $setup = $this->createStub(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn ($name): string => 'pfx_' . $name);

        $patch = new MigrateConfigPaths($setup);

        $this->assertSame($patch, $patch->apply());
        $this->assertSame('pfx_core_config_data', $captured[0]);
        $this->assertInstanceOf(\Zend_Db_Expr::class, $captured[1]['path']);
        $this->assertSame(
            "REPLACE(path, 'panth_seo/social/', 'panth_social_meta/social/')",
            (string) $captured[1]['path']
        );
        $this->assertSame("path LIKE 'panth_seo/social/%'", $captured[2]);
    }

    public function testHasNoDependenciesOrAliases(): void
    {
        $this->assertSame([], MigrateConfigPaths::getDependencies());
        $this->assertSame([], (new MigrateConfigPaths($this->createStub(ModuleDataSetupInterface::class)))->getAliases());
    }
}

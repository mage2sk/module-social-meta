<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Test\Unit\Setup\Patch\Data;

use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Panth\SocialMeta\Setup\Patch\Data\AddOgAttributes;
use PHPUnit\Framework\TestCase;

class AddOgAttributesTest extends TestCase
{
    public function testAddsMissingAttributesAndAssignsThemToSeoGroup(): void
    {
        $existing = [
            'catalog_product' => ['og_title' => 11],
            'catalog_category' => ['og_image' => 31],
            4 => ['og_title' => 11, 'og_description' => 12, 'og_image' => 0],
        ];
        $added = [];
        $groupsAdded = [];
        $assigned = [];
        $groupLookups = [];

        $eavSetup = $this->createStub(EavSetup::class);
        $eavSetup->method('getAttributeId')->willReturnCallback(
            static fn ($entity, $code) => $existing[$entity][$code] ?? false
        );
        $eavSetup->method('addAttribute')->willReturnCallback(
            function ($entity, $code, array $attr) use (&$added, $eavSetup) {
                $added[] = [$entity, $code, $attr['type'], $attr['input'], $attr['sort_order']];
                return $eavSetup;
            }
        );
        $eavSetup->method('getEntityTypeId')->willReturn(4);
        $eavSetup->method('getAttributeGroupId')->willReturnCallback(
            static function ($typeId, $setId, $name) use (&$groupLookups) {
                $groupLookups[] = [$typeId, $setId, $name];
                if ((int) $setId === 9 && count(array_filter($groupLookups, static fn ($l) => $l[1] === 9)) === 1) {
                    return false;
                }
                return (int) $setId === 9 ? 21 : 20;
            }
        );
        $eavSetup->method('addAttributeGroup')->willReturnCallback(
            function ($typeId, $setId, $name, $sort) use (&$groupsAdded, $eavSetup) {
                $groupsAdded[] = [$typeId, $setId, $name, $sort];
                return $eavSetup;
            }
        );
        $eavSetup->method('addAttributeToGroup')->willReturnCallback(
            function ($typeId, $setId, $groupId, $attrId) use (&$assigned, $eavSetup) {
                $assigned[] = [$setId, $groupId, $attrId];
                return $eavSetup;
            }
        );

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())
            ->method('fetchCol')
            ->with('SELECT attribute_set_id FROM eav_attribute_set WHERE entity_type_id = ?', [4])
            ->willReturn([4, 9]);

        $setup = $this->createStub(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);

        $factory = $this->createMock(EavSetupFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->with(['setup' => $setup])
            ->willReturn($eavSetup);

        $patch = new AddOgAttributes($setup, $factory);

        $this->assertSame($patch, $patch->apply());
        $this->assertSame([
            ['catalog_product', 'og_description', 'text', 'textarea', 65],
            ['catalog_product', 'og_image', 'varchar', 'text', 70],
            ['catalog_category', 'og_title', 'varchar', 'text', 60],
            ['catalog_category', 'og_description', 'text', 'textarea', 65],
        ], $added);
        $this->assertSame([[4, 9, 'Search Engine Optimization', 65]], $groupsAdded);
        $this->assertSame([[4, 20, 11], [4, 20, 12], [9, 21, 11], [9, 21, 12]], $assigned);
    }

    public function testHasNoDependenciesOrAliases(): void
    {
        $patch = new AddOgAttributes(
            $this->createStub(ModuleDataSetupInterface::class),
            $this->createStub(EavSetupFactory::class)
        );

        $this->assertSame([], AddOgAttributes::getDependencies());
        $this->assertSame([], $patch->getAliases());
    }
}
